<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Application\Dto\ReportRow;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\ReportReader;
use App\Application\Ports\UserRepository;
use App\Domain\Book;
use App\Domain\Category;
use App\Domain\DateRange;
use App\Domain\DomainError;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;
use DateTimeImmutable;

/**
 * Fakes of every outbound repository port, backed by one InMemoryStore. They return CLONES, like a real database returns fresh
 * rows, so a test only sees changes that were actually persisted.
 */
final class InMemoryCategoryRepository implements CategoryRepository
{
    public function __construct(private readonly InMemoryStore $s)
    {
    }

    public function all(): array
    {
        $all = $this->s->categories;
        usort($all, static fn (Category $a, Category $b): int => strcmp($a->name, $b->name));

        return $all;
    }

    public function find(string $id): ?Category
    {
        foreach ($this->s->categories as $c) {
            if ($c->id === $id) {
                return $c;
            }
        }

        return null;
    }
}

final class InMemoryBookRepository implements BookRepository
{
    public function __construct(private readonly InMemoryStore $s)
    {
    }

    public function find(string $id): ?Book
    {
        $b = $this->s->books[$id] ?? null;

        return $b === null || $b->isDeleted() ? null : clone $b;
    }

    public function findMany(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (($b = $this->find($id)) !== null) {
                $out[$id] = $b;
            }
        }

        return $out;
    }

    public function search(?string $q, ?string $categoryId, Paging $paging): Page
    {
        $rows = array_filter($this->s->books, static function (Book $b) use ($q, $categoryId): bool {
            return !$b->isDeleted()
                && ($categoryId === null || $b->category()->id === $categoryId)
                && ($q === null || mb_stripos($b->title(), $q) !== false || mb_stripos($b->author(), $q) !== false);
        });
        usort($rows, static fn (Book $a, Book $b): int => strcasecmp($a->title(), $b->title()));

        return new Page(array_map(static fn (Book $b): Book => clone $b, array_slice($rows, $paging->offset(), $paging->size)), $paging->page, $paging->size, count($rows));
    }

    public function insert(Book $book): void
    {
        foreach ($this->s->books as $b) {
            if (!$b->isDeleted() && $b->isbn->value === $book->isbn->value) {
                throw DomainError::isbnTaken();
            }
        }
        $this->s->books[$book->id] = clone $book;
    }

    public function update(Book $book): void
    {
        $stored = $this->s->books[$book->id] ?? throw DomainError::notFound('El libro');
        // Mimic the atomic engine update: apply the DIFFERENCE of totals to the STORED available copies.
        $delta = $book->totalCopies() - $stored->totalCopies();
        if ($stored->availableCopies() + $delta < 0) {
            throw DomainError::invalid('total_copies', 'deja ejemplares disponibles negativos');
        }
        $stored->revise($book->title(), $book->author(), $book->category(), \App\Domain\Quantity::of($book->totalCopies()));
    }

    public function setCoverKey(string $bookId, ?string $coverKey): void
    {
        $b = $this->s->books[$bookId];
        $coverKey === null ? $b->removeCover() : $b->attachCover($coverKey);
    }

    public function softDelete(Book $book): void
    {
        $this->s->books[$book->id]->softDelete($book->deletedAt() ?? new DateTimeImmutable());
    }

    public function hasOpenLoans(string $bookId): bool
    {
        foreach ($this->s->loans as $loan) {
            foreach ($loan->items() as $i) {
                if ($i->bookId === $bookId && !$i->isReturned()) {
                    return true;
                }
            }
        }

        return false;
    }
}

final class InMemoryMemberRepository implements MemberRepository
{
    public function __construct(private readonly InMemoryStore $s)
    {
    }

    public function find(string $id): ?Member
    {
        return $this->s->members[$id] ?? null;
    }

    public function insert(Member $member): void
    {
        foreach ($this->s->members as $m) {
            if ($m->document->value === $member->document->value) {
                throw DomainError::documentTaken();
            }
        }
        $this->s->members[$member->id] = $member;
    }

    public function search(?string $q, Paging $paging): Page
    {
        $rows = array_filter($this->s->members, static fn (Member $m): bool => $q === null
            || mb_stripos($m->fullName, $q) !== false || mb_stripos($m->document->value, $q) !== false);
        usort($rows, static fn (Member $a, Member $b): int => strcasecmp($a->fullName, $b->fullName));

        return new Page(array_slice($rows, $paging->offset(), $paging->size), $paging->page, $paging->size, count($rows));
    }

    public function unreturnedCount(string $memberId): int
    {
        $n = 0;
        foreach ($this->s->loans as $loan) {
            if ($loan->memberId === $memberId) {
                $n += $loan->unreturnedCount();
            }
        }

        return $n;
    }

    public function hasOverdue(string $memberId, DateTimeImmutable $now): bool
    {
        foreach ($this->s->loans as $loan) {
            if ($loan->memberId !== $memberId) {
                continue;
            }
            foreach ($loan->items() as $i) {
                if ($i->isOverdue($now)) {
                    return true;
                }
            }
        }

        return false;
    }
}

final class InMemoryUserRepository implements UserRepository
{
    public function __construct(private readonly InMemoryStore $s)
    {
    }

    public function findByUsername(string $username): ?User
    {
        foreach ($this->s->users as $u) {
            if ($u->username === $username) {
                return $u;
            }
        }

        return null;
    }

    public function insert(User $user): void
    {
        if ($this->findByUsername($user->username) !== null) {
            throw DomainError::usernameTaken();
        }
        $this->s->users[$user->id] = $user;
    }
}

final class InMemoryLoanRepository implements LoanRepository
{
    public function __construct(private readonly InMemoryStore $s)
    {
    }

    public function insert(Loan $loan): void
    {
        // All-or-nothing: check every book first (ascending id), then apply. Nothing is saved if one is missing.
        $ids = array_map(static fn ($i): string => $i->bookId, $loan->items());
        sort($ids);
        foreach ($ids as $id) {
            $book = $this->s->books[$id] ?? null;
            if ($book === null || $book->availableCopies() <= 0) {
                $title = $book?->title() ?? 'el libro';
                throw DomainError::bookNotAvailable($title);
            }
        }
        foreach ($ids as $id) {
            $this->s->books[$id]->withdraw();
        }
        $this->s->loans[$loan->id] = $loan;
    }

    public function find(string $id): ?Loan
    {
        return $this->s->loans[$id] ?? null;
    }

    public function search(LoanFilter $filter, DateTimeImmutable $now, Paging $paging): Page
    {
        $rows = array_filter($this->s->loans, static fn (Loan $l): bool => ($filter->from === null || $l->loanedAt >= $filter->from)
            && ($filter->toExclusive === null || $l->loanedAt < $filter->toExclusive)
            && ($filter->memberId === null || $l->memberId === $filter->memberId)
            && ($filter->status === null || $l->status($now) === $filter->status));
        usort($rows, static fn (Loan $a, Loan $b): int => $b->loanedAt <=> $a->loanedAt);

        return new Page(array_slice($rows, $paging->offset(), $paging->size), $paging->page, $paging->size, count($rows));
    }

    public function persistReturn(string $loanId, string $itemId, DateTimeImmutable $now): void
    {
        $loan = $this->s->loans[$loanId] ?? throw DomainError::notFound('El préstamo');
        $item = $loan->item($itemId);
        $this->s->books[$item->bookId]->restock(); // exactly once per line
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        $out = [];
        foreach ($this->s->loans as $loan) {
            foreach ($loan->items() as $i) {
                if ($i->isOverdue($now)) {
                    $member = $this->s->members[$loan->memberId];
                    $out[] = new OverdueLine($loan->id, $i->id, $i->bookTitle, $member->fullName, $i->dueAt, $i->daysLate($now));
                }
            }
        }
        usort($out, static fn (OverdueLine $a, OverdueLine $b): int => $b->daysLate <=> $a->daysLate);

        return $out;
    }
}

final class InMemoryReportReader implements ReportReader
{
    public function __construct(private readonly InMemoryStore $s)
    {
    }

    public function mostBorrowed(DateRange $range): array
    {
        $groups = [];
        foreach ($this->s->loans as $loan) {
            if ($loan->loanedAt < $range->start || $loan->loanedAt >= $range->endExclusive) {
                continue;
            }
            foreach ($loan->items() as $i) { // grouped by the FROZEN values, never by the live catalog
                $key = $i->bookId.'|'.$i->bookTitle.'|'.$i->categoryName;
                $groups[$key] = [$i, ($groups[$key][1] ?? 0) + 1];
            }
        }
        $rows = array_map(static fn (array $g): ReportRow => new ReportRow($g[0]->bookId, $g[0]->bookTitle, $g[0]->categoryName, $g[1]), array_values($groups));
        usort($rows, static fn (ReportRow $a, ReportRow $b): int => [$b->timesBorrowed, $a->bookTitle] <=> [$a->timesBorrowed, $b->bookTitle]);

        return $rows;
    }
}
