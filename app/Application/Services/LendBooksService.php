<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Domain\DomainError;
use App\Domain\Loan;
use App\Domain\Uuid;

/**
 * HU-06 · register a loan. One use case, one reason to change (SRP).
 *
 * Validation order (contract §3.6, clarification C-02). The first failing check decides the response:
 *   2. shape: 1 to 3 items            -> VALIDATION_ERROR (422)
 *   3. no repeated book               -> DUPLICATE_BOOK_IN_LOAN (422)
 *   4. member exists                  -> NOT_FOUND (404)
 *   5. every book exists and is active-> NOT_FOUND (404)
 *   6. member has no overdue line     -> MEMBER_HAS_OVERDUE (409)
 *   7. unreturned + requested <= 3    -> MEMBER_LIMIT_EXCEEDED (422)
 *   8. stock, decided atomically on write -> BOOK_NOT_AVAILABLE (409), nothing is saved
 * (step 1, authentication and role, belongs to the HTTP adapter.)
 */
final class LendBooksService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly BookRepository $books,
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    /** @param list<string> $bookIds */
    public function execute(string $memberId, array $bookIds, string $registeredByUserId): Loan
    {
        // 2. shape
        $count = count($bookIds);
        if ($count < 1 || $count > Loan::MAX_ITEMS) {
            throw DomainError::invalid('items', 'debe traer entre 1 y '.Loan::MAX_ITEMS.' libros');
        }
        // 3. no repeated book
        $seen = [];
        foreach (array_values($bookIds) as $index => $bookId) {
            if (isset($seen[$bookId])) {
                throw DomainError::duplicateBookInLoan($index);
            }
            $seen[$bookId] = true;
        }
        // 4. member
        if ($this->members->find($memberId) === null) {
            throw DomainError::notFound('El socio', [['field' => 'member_id', 'issue' => 'no existe']]);
        }
        // 5. books (a deleted book is "not found")
        $found = $this->books->findMany($bookIds);
        foreach (array_values($bookIds) as $index => $bookId) {
            if (!isset($found[$bookId])) {
                throw DomainError::notFound('El libro', [['field' => "items[{$index}].book_id", 'issue' => 'no existe']]);
            }
        }

        $now = $this->clock->now();
        // 6. overdue
        if ($this->members->hasOverdue($memberId, $now)) {
            throw DomainError::memberHasOverdue();
        }
        // 7. limit
        $current = $this->members->unreturnedCount($memberId);
        if ($current + $count > Loan::MAX_ITEMS) {
            throw DomainError::memberLimitExceeded($current, $count, Loan::MAX_ITEMS);
        }

        // 8. build the aggregate (in-memory early rejection) and save it atomically
        $loan = Loan::register(Uuid::v4(), $memberId, $registeredByUserId, $now);
        foreach (array_values($bookIds) as $index => $bookId) {
            try {
                $loan->addItem($found[$bookId], Uuid::v4());
            } catch (DomainError $e) {
                if ($e->errorCode === DomainError::BOOK_NOT_AVAILABLE) {
                    throw DomainError::bookNotAvailable($found[$bookId]->title(), "items[{$index}].book_id");
                }
                throw $e;
            }
        }
        $this->loans->insert($loan);

        return $loan;
    }
}
