<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\ReportRow;
use App\Domain\DateRange;

/** Read port for the aggregated report (ADR-004): one query, grouped by the frozen values. */
interface ReportReader
{
    /** @return list<ReportRow> ordered by times borrowed descending, then title */
    public function mostBorrowed(DateRange $range): array;
}
