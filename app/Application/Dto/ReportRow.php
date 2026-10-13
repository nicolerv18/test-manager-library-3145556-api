<?php

declare(strict_types=1);

namespace App\Application\Dto;

/** One row of the most-borrowed report, grouped by the FROZEN book title and category name (ADR-004). */
final readonly class ReportRow
{
    public function __construct(
        public string $bookId,
        public string $bookTitle,
        public string $categoryName,
        public int $timesBorrowed,
    ) {
    }
}
