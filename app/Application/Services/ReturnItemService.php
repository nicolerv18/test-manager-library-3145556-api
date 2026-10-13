<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Domain\DomainError;
use App\Domain\LoanItem;

/** HU-07 · return one line (CA-07.1..CA-07.4). */
final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->find($loanId) ?? throw DomainError::notFound('El préstamo');
        $now = $this->clock->now();

        $loan->returnItem($itemId, $now);                      // NOT_FOUND / ITEM_ALREADY_RETURNED, early
        $this->loans->persistReturn($loanId, $itemId, $now);   // atomic: returned_at + one copy back, never twice

        return $loan->item($itemId);
    }
}
