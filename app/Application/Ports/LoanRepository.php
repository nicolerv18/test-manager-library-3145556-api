<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Domain\Loan;
use DateTimeImmutable;

interface LoanRepository
{
    /**
     * Saves the loan and its lines and takes one copy per line, ALL IN ONE TRANSACTION (CA-06.3).
     * Each copy is taken with an atomic conditional update, books in ascending id order (ADR-002).
     *
     * @throws \App\Domain\DomainError BOOK_NOT_AVAILABLE when a book ran out; nothing is saved
     */
    public function insert(Loan $loan): void;

    public function find(string $id): ?Loan;

    /** @return Page<Loan> loans ordered by date descending; the status filter is computed against `$now` */
    public function search(LoanFilter $filter, DateTimeImmutable $now, Paging $paging): Page;

    /**
     * Persists the return of one line and gives the copy back, in one transaction. Decided atomically by the engine
     * (`UPDATE … WHERE returned_at IS NULL`), so it can never add stock twice (CA-07.2).
     *
     * @throws \App\Domain\DomainError ITEM_ALREADY_RETURNED, NOT_FOUND
     */
    public function persistReturn(string $loanId, string $itemId, DateTimeImmutable $now): void;

    /** @return list<OverdueLine> unreturned lines with `due_at` before `$now`, most days late first */
    public function overdueLines(DateTimeImmutable $now): array;
}
