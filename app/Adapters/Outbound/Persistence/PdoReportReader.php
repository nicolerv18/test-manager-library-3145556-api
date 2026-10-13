<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\ReportRow;
use App\Application\Ports\ReportReader;
use App\Domain\DateRange;

/** Aggregates over the FROZEN loan_item values only (ADR-004): the report never touches `book`, `category` nor `member`. */
final class PdoReportReader implements ReportReader
{
    use PdoSupport;

    public function mostBorrowed(DateRange $range): array
    {
        $rows = $this->run(
            'SELECT i.book_id, i.book_title, i.category_name, count(*) AS times
             FROM library.loan_item i JOIN library.loan l ON l.id = i.loan_id
             WHERE l.loaned_at >= :from AND l.loaned_at < :to
             GROUP BY i.book_id, i.book_title, i.category_name
             ORDER BY times DESC, i.book_title ASC, i.book_id',
            ['from' => self::ts($range->start), 'to' => self::ts($range->endExclusive)],
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): ReportRow => new ReportRow($r['book_id'], $r['book_title'], $r['category_name'], (int) $r['times']), $rows);
    }
}
