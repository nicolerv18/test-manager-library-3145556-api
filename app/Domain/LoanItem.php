<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * One book inside a loan. Title, ISBN and category name are FROZEN at the instant of the loan (RN-4, Article VIII):
 * they never follow the live catalog.
 */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly string $isbn,
        public readonly string $categoryName,
        public readonly DateTimeImmutable $dueAt,
        private ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public function returnedAt(): ?DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    /** Overdue = not returned and `due_at` strictly earlier than now (CA-09.1). */
    public function isOverdue(DateTimeImmutable $now): bool
    {
        return !$this->isReturned() && $this->dueAt < $now;
    }

    /** Whole 24-hour periods elapsed since `due_at`, rounded down (clarification C-04). Zero when not overdue. */
    public function daysLate(DateTimeImmutable $now): int
    {
        if (!$this->isOverdue($now)) {
            return 0;
        }

        return intdiv($now->getTimestamp() - $this->dueAt->getTimestamp(), 86400);
    }

    /** CA-07.2: returning twice is a conflict and never adds stock twice. */
    public function markReturned(DateTimeImmutable $now): void
    {
        if ($this->isReturned()) {
            throw DomainError::itemAlreadyReturned();
        }
        $this->returnedAt = $now;
    }
}
