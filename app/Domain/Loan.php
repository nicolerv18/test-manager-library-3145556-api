<?php

declare(strict_types=1);

namespace App\Domain;

use DateInterval;
use DateTimeImmutable;

/**
 * Aggregate root `Loan`. Defends: 1 to 3 lines, no repeated book, due date at 14 days, immutable except for returns.
 * A registered loan is never edited nor deleted (CA-06.7); the only mutation is a line's `returned_at`.
 */
final class Loan
{
    public const MAX_ITEMS = 3;
    public const LOAN_DAYS = 14;

    /** @var list<LoanItem> */
    private array $items;

    /** @param list<LoanItem> $items */
    private function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly DateTimeImmutable $loanedAt,
        array $items,
    ) {
        $this->items = $items;
    }

    public static function register(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $now): self
    {
        return new self($id, $memberId, $loanedByUserId, $now, []);
    }

    /** @param list<LoanItem> $items */
    public static function restore(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt, array $items): self
    {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, $items);
    }

    /**
     * Withdraws one copy of the book and adds the line in the same operation (CA-06.3, CA-06.6):
     * if the book has no copies nothing is added.
     */
    public function addItem(Book $book, string $itemId): LoanItem
    {
        if (count($this->items) >= self::MAX_ITEMS) {
            throw DomainError::invalid('items', 'un préstamo admite entre 1 y '.self::MAX_ITEMS.' libros');
        }
        foreach ($this->items as $existing) {
            if ($existing->bookId === $book->id) {
                throw DomainError::duplicateBookInLoan(count($this->items));
            }
        }

        $book->withdraw(); // throws BOOK_NOT_AVAILABLE before the line exists

        $item = new LoanItem(
            $itemId,
            $book->id,
            $book->title(),
            $book->isbn->value,
            $book->category()->name,
            $this->loanedAt->add(new DateInterval('P'.self::LOAN_DAYS.'D')),
        );
        $this->items[] = $item;

        return $item;
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function item(string $itemId): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }
        throw DomainError::notFound('La línea del préstamo');
    }

    /** Returns one line (CA-07.1). The persistence adapter restocks the book atomically in the same transaction. */
    public function returnItem(string $itemId, DateTimeImmutable $now): LoanItem
    {
        $item = $this->item($itemId);
        $item->markReturned($now);

        return $item;
    }

    /** Computed, never stored (CA-08.3). A loan with no lines yet is considered open. */
    public function status(DateTimeImmutable $now): LoanStatus
    {
        $pending = array_filter($this->items, static fn (LoanItem $i): bool => !$i->isReturned());
        if ($this->items !== [] && $pending === []) {
            return LoanStatus::Closed;
        }
        foreach ($pending as $item) {
            if ($item->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
        }

        return LoanStatus::Open;
    }

    public function unreturnedCount(): int
    {
        return count(array_filter($this->items, static fn (LoanItem $i): bool => !$i->isReturned()));
    }
}
