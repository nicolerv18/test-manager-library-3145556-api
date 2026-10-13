<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\ReportRow;
use App\Application\Ports\ReportReader;
use App\Domain\DateRange;

/** HU-10 · most borrowed books (CA-10.1..CA-10.4). The role check (admin only, CA-10.5) belongs to the HTTP adapter. */
final class MostBorrowedReportService
{
    public function __construct(private readonly ReportReader $reader)
    {
    }

    /** @return list<ReportRow> */
    public function execute(string $from, string $to): array
    {
        return $this->reader->mostBorrowed(DateRange::parse($from, $to)); // INVALID_DATE_RANGE if inverted
    }
}
