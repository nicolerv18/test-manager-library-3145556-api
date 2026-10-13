<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Domain\DateRange;
use App\Domain\DomainError;
use App\Domain\Loan;
use App\Domain\LoanStatus;

/** HU-08 · list by date range, status and member, most recent first (CA-08.2). Dates follow clarification C-01. */
final class ListLoansService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    /** @return Page<Loan> */
    public function execute(?string $from, ?string $to, ?string $status, ?string $memberId, ?int $page, ?int $size): Page
    {
        $start = $from === null || $from === '' ? null : DateRange::instant($from, 'from', false);
        $end = $to === null || $to === '' ? null : DateRange::instant($to, 'to', true);
        if ($start !== null && $end !== null && $end <= $start) {
            throw DomainError::invalidDateRange();
        }

        $parsedStatus = null;
        if ($status !== null && $status !== '') {
            $parsedStatus = LoanStatus::tryFrom($status)
                ?? throw DomainError::invalid('status', 'debe ser open, overdue o closed');
        }

        return $this->loans->search(
            new LoanFilter($start, $end, $parsedStatus, $memberId === '' ? null : $memberId),
            $this->clock->now(),
            Paging::of($page, $size),
        );
    }
}
