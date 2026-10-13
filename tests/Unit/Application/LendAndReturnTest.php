<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Services\CreateBookService;
use App\Application\Services\CreateMemberService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\LendBooksService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\DomainError;
use App\Domain\Uuid;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FixedClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCategoryRepository;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMemberRepository;
use Tests\Fakes\InMemoryStore;
use Tests\Support\AssertsDomainErrors;

final class LendAndReturnTest extends TestCase
{
    use AssertsDomainErrors;

    private InMemoryStore $store;
    private FixedClock $clock;
    private InMemoryBookRepository $books;
    private InMemoryMemberRepository $members;
    private InMemoryLoanRepository $loans;
    private LendBooksService $lend;
    private ReturnItemService $return;
    private string $user;
    private int $isbnSeq = 0;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->clock = new FixedClock('2026-10-01T10:00:00Z');
        $this->books = new InMemoryBookRepository($this->store);
        $this->members = new InMemoryMemberRepository($this->store);
        $this->loans = new InMemoryLoanRepository($this->store);
        $this->lend = new LendBooksService($this->members, $this->books, $this->loans, $this->clock);
        $this->return = new ReturnItemService($this->loans, $this->clock);
        $this->user = Uuid::v4();
        $this->isbnSeq = 0;
    }

    private function newBook(int $copies = 2): string
    {
        $svc = new CreateBookService($this->books, new InMemoryCategoryRepository($this->store));
        $isbn = '978000000'.str_pad((string) (++$this->isbnSeq), 4, '0', STR_PAD_LEFT);

        return $svc->execute('Libro '.$this->isbnSeq, 'Autor', $isbn, $this->store->categories[0]->id, $copies)->id;
    }

    private function newMember(): string
    {
        return (new CreateMemberService($this->members))->execute('Ana Pérez', 'D'.substr(str_replace('-', '', Uuid::v4()), 0, 20))->id;
    }

    public function test_lending_registers_loan_and_reduces_stock(): void
    {
        $m = $this->newMember();
        $b = $this->newBook(2);
        $loan = $this->lend->execute($m, [$b], $this->user);
        $this->assertCount(1, $loan->items());
        $this->assertSame(1, $this->books->find($b)->availableCopies());
    }

    public function test_zero_or_four_books_is_a_validation_error(): void
    {
        $m = $this->newMember();
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $this->lend->execute($m, [], $this->user));
        $ids = [$this->newBook(), $this->newBook(), $this->newBook(), $this->newBook()];
        $this->assertDomainError(DomainError::VALIDATION_ERROR, fn () => $this->lend->execute($m, $ids, $this->user));
    }

    public function test_duplicate_book_is_rejected_before_anything_is_looked_up(): void
    {
        $b = $this->newBook();
        $e = $this->assertDomainError(DomainError::DUPLICATE_BOOK_IN_LOAN, fn () => $this->lend->execute(Uuid::v4(), [$b, $b], $this->user));
        $this->assertSame('items[1].book_id', $e->details[0]['field']);
    }

    public function test_unknown_member_and_unknown_book_are_404(): void
    {
        $this->assertDomainError(DomainError::NOT_FOUND, fn () => $this->lend->execute(Uuid::v4(), [$this->newBook()], $this->user));
        $m = $this->newMember();
        $this->assertDomainError(DomainError::NOT_FOUND, fn () => $this->lend->execute($m, [Uuid::v4()], $this->user));
    }

    public function test_deleted_book_cannot_be_lent(): void
    {
        $m = $this->newMember();
        $b = $this->newBook();
        (new DeleteBookService($this->books, $this->clock))->execute($b);
        $this->assertDomainError(DomainError::NOT_FOUND, fn () => $this->lend->execute($m, [$b], $this->user));
    }

    public function test_fourth_unreturned_book_exceeds_the_limit(): void
    {
        $m = $this->newMember();
        $this->lend->execute($m, [$this->newBook(), $this->newBook()], $this->user);
        $e = $this->assertDomainError(DomainError::MEMBER_LIMIT_EXCEEDED, fn () => $this->lend->execute($m, [$this->newBook(), $this->newBook()], $this->user));
        $this->assertSame(2, $e->details[0]['current'] ?? 2);
    }

    public function test_returning_a_book_frees_a_slot_of_the_limit(): void
    {
        $m = $this->newMember();
        $loan = $this->lend->execute($m, [$this->newBook(), $this->newBook(), $this->newBook()], $this->user);
        $this->return->execute($loan->id, $loan->items()[0]->id);
        $loan2 = $this->lend->execute($m, [$this->newBook()], $this->user);
        $this->assertCount(1, $loan2->items());
    }

    public function test_overdue_member_cannot_borrow_until_returning(): void
    {
        $m = $this->newMember();
        $loan = $this->lend->execute($m, [$this->newBook()], $this->user);
        $this->clock->advanceDays(15);
        $this->assertDomainError(DomainError::MEMBER_HAS_OVERDUE, fn () => $this->lend->execute($m, [$this->newBook()], $this->user));
        $this->return->execute($loan->id, $loan->items()[0]->id);
        $this->assertCount(1, $this->lend->execute($m, [$this->newBook()], $this->user)->items());
    }

    public function test_no_stock_means_nothing_is_saved_for_any_line(): void
    {
        $m = $this->newMember();
        $m2 = $this->newMember();
        $last = $this->newBook(1);
        $other = $this->newBook(3);
        $this->lend->execute($m2, [$last], $this->user);
        $this->assertDomainError(DomainError::BOOK_NOT_AVAILABLE, fn () => $this->lend->execute($m, [$other, $last], $this->user));
        $this->assertSame(3, $this->books->find($other)->availableCopies());
        $this->assertSame(0, $this->members->unreturnedCount($m));
    }

    public function test_return_restocks_and_second_return_conflicts(): void
    {
        $m = $this->newMember();
        $b = $this->newBook(1);
        $loan = $this->lend->execute($m, [$b], $this->user);
        $this->assertSame(0, $this->books->find($b)->availableCopies());
        $this->return->execute($loan->id, $loan->items()[0]->id);
        $this->assertSame(1, $this->books->find($b)->availableCopies());
        $this->assertDomainError(DomainError::ITEM_ALREADY_RETURNED, fn () => $this->return->execute($loan->id, $loan->items()[0]->id));
        $this->assertSame(1, $this->books->find($b)->availableCopies());
    }

    public function test_return_unknown_loan_is_404(): void
    {
        $this->assertDomainError(DomainError::NOT_FOUND, fn () => $this->return->execute(Uuid::v4(), Uuid::v4()));
    }

    public function test_overdue_listing_shows_days_late_and_only_unreturned_lines(): void
    {
        $m = $this->newMember();
        $loan = $this->lend->execute($m, [$this->newBook(), $this->newBook()], $this->user);
        $this->clock->advanceDays(17);
        $svc = new OverdueService($this->loans, $this->clock);
        $lines = $svc->execute();
        $this->assertCount(2, $lines);
        $this->assertSame(3, $lines[0]->daysLate);
        $this->return->execute($loan->id, $loan->items()[0]->id);
        $this->assertCount(1, $svc->execute());
    }

    public function test_book_with_open_loan_cannot_be_deleted(): void
    {
        $m = $this->newMember();
        $b = $this->newBook();
        $loan = $this->lend->execute($m, [$b], $this->user);
        $del = new DeleteBookService($this->books, $this->clock);
        $this->assertDomainError(DomainError::BOOK_HAS_OPEN_LOANS, fn () => $del->execute($b));
        $this->return->execute($loan->id, $loan->items()[0]->id);
        $del->execute($b);
        $this->assertNull($this->books->find($b));
    }
}
