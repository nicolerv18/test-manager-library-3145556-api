<?php

declare(strict_types=1);

namespace App\Application\Dto;

use DateTimeImmutable;

/** One overdue line of GET /loans/overdue. The member's name is shown here because it is an operational listing. */
final readonly class OverdueLine
{
    public function __construct(
        public string $loanId,
        public string $itemId,
        public string $bookTitle,
        public string $memberName,
        public DateTimeImmutable $dueAt,
        public int $daysLate,
    ) {
    }
}
