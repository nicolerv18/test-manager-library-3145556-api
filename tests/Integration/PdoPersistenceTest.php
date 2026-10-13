<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Adapters\Outbound\Persistence\PdoBookRepository;
use App\Adapters\Outbound\Persistence\PdoCategoryRepository;
use App\Adapters\Outbound\Persistence\PdoLoanRepository;
use App\Adapters\Outbound\Persistence\PdoMemberRepository;
use App\Adapters\Outbound\Persistence\PdoReportReader;
use App\Adapters\Outbound\Persistence\PdoUserRepository;
use App\Application\Dto\LoanFilter;
use App\Application\Dto\Paging;
use App\Application\Services\CreateBookService;
use App\Application\Services\CreateMemberService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ReturnItemService;
use App\Application\Services\UpdateBookService;
use App\Domain\DateRange;
use App\Domain\DomainError;
use App\Domain\LoanStatus;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FixedClock;
use Tests\Support\AssertsDomainErrors;

/**
 * Runs the real SQL against a real PostgreSQL. Needs TEST_DB_DSN (e.g. "pgsql:host=127.0.0.1;port=5432;dbname=lib") and TEST_DB_USER,
 * with database/sql/library.sql already applied. Skipped when not configured.
 */
final class PdoPersistenceTest extends TestCase
{
    use AssertsDomainErrors;

    private PDO $pdo;
    private string $dsn;
    private string $dbUser;
    private FixedClock $clock;
    private string $userId;
    private int $seq = 0;

    protected function setUp(): void
    {
        $this->dsn = (string) getenv('TEST_DB_DSN');
        if ($this->dsn === '') {
            $this->markTestSkipped('TEST_DB_DSN is not set');
        }
        $this->dbUser = (string) (getenv('TEST_DB_USER') ?: 'postgres');
        $this->pdo = new PDO($this->dsn, $this->dbUser, getenv('TEST_DB_PASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('TRUNCATE library.loan_item, library.loan, library.book, library.member, library."user" CASCADE');
        $this->clock = new FixedClock('2026-10-01T10:00:00Z');
        $this->userId = Uuid::v4();
        (new PdoUserRepository($this->pdo))->insert(User::create($this->userId, 'op', 'hash', Role::Librarian));
    }

    private function book(int $copies = 2, string $title = 'Libro'): string
    {
        $isbn = '978100000'.str_pad((string) (++$this->seq), 4, '0', STR_PAD_LEFT);

        return (new CreateBookService(new PdoBookRepository($this->pdo), new PdoCategoryRepository($this->pdo)))
            ->execute($title.' '.$this->seq, 'Autor', $isbn, '11111111-1111-4111-8111-111111111111', $copies)->id;
    }

    private function member(): string
    {
        return (new CreateMemberService(new PdoMemberRepository($this->pdo)))->execute('Ana Pérez', 'D'.substr(str_replace('-', '', Uuid::v4()), 0, 20))->id;
    }

    private function lend(): LendBooksService
    {
        return new LendBooksService(new PdoMemberRepository($this->pdo), new PdoBookRepository($this->pdo), new PdoLoanRepository($this->pdo), $this->clock);
    }

    public function test_lend_and_return_roundtrip_keeps_stock_and_frozen_values(): void
    {
        $b = $this->book(1, 'Original');
        $m = $this->member();
        $loan = $this->lend()->execute($m, [$b], $this->userId);
        $books = new PdoBookRepository($this->pdo);
        $this->assertSame(0, $books->find($b)->availableCopies());

        $repo = new PdoLoanRepository($this->pdo);
        $stored = $repo->find($loan->id);
        $this->assertSame('2026-10-15T10:00:00+00:00', $stored->items()[0]->dueAt->format('c'));

        $cat = (new PdoCategoryRepository($this->pdo))->find('22222222-2222-4222-8222-222222222222');
        (new UpdateBookService($books, new PdoCategoryRepository($this->pdo)))->execute($b, 'Cambiado', 'Otro', $cat->id, 1);
        $this->assertSame('Original 1', $repo->find($loan->id)->items()[0]->bookTitle);
        $this->assertSame('Novela', $repo->find($loan->id)->items()[0]->categoryName);

        (new ReturnItemService($repo, $this->clock))->execute($loan->id, $stored->items()[0]->id);
        $this->assertSame(1, $books->find($b)->availableCopies());
        $this->assertDomainError(DomainError::ITEM_ALREADY_RETURNED, fn () => (new ReturnItemService($repo, $this->clock))->execute($loan->id, $stored->items()[0]->id));
        $this->assertSame(1, $books->find($b)->availableCopies());
    }

    public function test_failed_line_rolls_back_the_other_lines(): void
    {
        $a = $this->book(3);
        $last = $this->book(1);
        $m1 = $this->member();
        $m2 = $this->member();
        $this->lend()->execute($m1, [$last], $this->userId);
        $this->assertDomainError(DomainError::BOOK_NOT_AVAILABLE, fn () => $this->lend()->execute($m2, [$a, $last], $this->userId));
        $this->assertSame(3, (new PdoBookRepository($this->pdo))->find($a)->availableCopies());
        $this->assertSame(0, (new PdoMemberRepository($this->pdo))->unreturnedCount($m2));
    }

    public function test_only_one_of_many_concurrent_loans_gets_the_last_copy(): void
    {
        $b = $this->book(1);
        $procs = [];
        for ($i = 0; $i < 8; ++$i) {
            $m = $this->member();
            $cmd = [PHP_BINARY, __DIR__.'/lend_worker.php', $this->dsn, $this->dbUser, $b, $m, $this->userId];
            $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $procs[] = [$p, $pipes];
        }
        $results = [];
        foreach ($procs as [$p, $pipes]) {
            $results[] = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            proc_close($p);
        }
        $this->assertSame(1, count(array_filter($results, static fn (string $r): bool => $r === 'OK')), implode(',', $results));
        $this->assertSame(7, count(array_filter($results, static fn (string $r): bool => $r === DomainError::BOOK_NOT_AVAILABLE)), implode(',', $results));
        $this->assertSame(0, (new PdoBookRepository($this->pdo))->find($b)->availableCopies());
    }

    public function test_status_filter_overdue_and_report(): void
    {
        $m = $this->member();
        $b1 = $this->book(2, 'Alfa');
        $b2 = $this->book(2, 'Beta');
        $this->lend()->execute($m, [$b1], $this->userId);
        $loans = new PdoLoanRepository($this->pdo);
        $later = new \DateTimeImmutable('2026-10-20T10:00:00Z');
        $this->assertCount(1, $loans->search(new LoanFilter(status: LoanStatus::Overdue), $later, Paging::of(1, 10))->items);
        $this->assertCount(0, $loans->search(new LoanFilter(status: LoanStatus::Open), $later, Paging::of(1, 10))->items);
        $this->assertCount(1, $loans->search(new LoanFilter(status: LoanStatus::Open), $this->clock->now(), Paging::of(1, 10))->items);
        $this->assertSame(5, $loans->overdueLines($later)[0]->daysLate);

        $m2 = $this->member();
        $this->lend()->execute($m2, [$b1, $b2], $this->userId);
        $rows = (new PdoReportReader($this->pdo))->mostBorrowed(DateRange::parse('2026-10-01', '2026-10-01'));
        $this->assertSame('Alfa 1', $rows[0]->bookTitle);
        $this->assertSame(2, $rows[0]->timesBorrowed);
    }

    public function test_search_treats_percent_literally_and_paginates(): void
    {
        $this->book(1, '100% Java');
        $this->book(1, 'Otro');
        $books = new PdoBookRepository($this->pdo);
        $this->assertSame(1, $books->search('100%', null, Paging::of(1, 10))->total);
        $this->assertSame(2, $books->search(null, null, Paging::of(1, 1))->total);
        $this->assertCount(1, $books->search(null, null, Paging::of(2, 1))->items);
    }

    public function test_isbn_unique_only_among_active_and_username_document_unique(): void
    {
        $books = new PdoBookRepository($this->pdo);
        $svc = new CreateBookService($books, new PdoCategoryRepository($this->pdo));
        $cat = '11111111-1111-4111-8111-111111111111';
        $first = $svc->execute('A', 'B', '9781999999999', $cat, 1);
        $this->assertDomainError(DomainError::ISBN_TAKEN, fn () => $svc->execute('C', 'D', '9781999999999', $cat, 1));
        (new \App\Application\Services\DeleteBookService($books, $this->clock))->execute($first->id);
        $this->assertSame('C', $svc->execute('C', 'D', '9781999999999', $cat, 1)->title());
        $this->assertDomainError(DomainError::USERNAME_TAKEN, fn () => (new PdoUserRepository($this->pdo))->insert(User::create(Uuid::v4(), 'OP', 'h', Role::Admin)));
    }

    public function test_lowering_total_below_lent_copies_is_rejected_by_the_engine_too(): void
    {
        $b = $this->book(2);
        $this->lend()->execute($this->member(), [$b], $this->userId);
        $this->lend()->execute($this->member(), [$b], $this->userId);
        $cat = '11111111-1111-4111-8111-111111111111';
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => (new UpdateBookService(new PdoBookRepository($this->pdo), new PdoCategoryRepository($this->pdo)))->execute($b, 'T', 'A', $cat, 1));
    }
}
