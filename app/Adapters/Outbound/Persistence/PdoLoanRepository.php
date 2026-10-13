<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\LoanFilter;
use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Application\Ports\LoanRepository;
use App\Domain\DomainError;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\LoanStatus;
use DateTimeImmutable;

final class PdoLoanRepository implements LoanRepository
{
    use PdoSupport;

    /**
     * One transaction: withdraw one copy of every book with an atomic conditional UPDATE (ascending book id, so two concurrent loans
     * never deadlock), then write the loan and its lines. If any book has no copy left, everything rolls back (ADR-002).
     */
    public function insert(Loan $loan): void
    {
        $this->pdo->beginTransaction();
        try {
            $items = $loan->items();
            $order = array_keys($items);
            usort($order, static fn (int $a, int $b): int => strcmp($items[$a]->bookId, $items[$b]->bookId));
            foreach ($order as $index) {
                $st = $this->run(
                    'UPDATE library.book SET available_copies = available_copies - 1 WHERE id = :id AND deleted_at IS NULL AND available_copies > 0',
                    ['id' => $items[$index]->bookId],
                );
                if ($st->rowCount() === 0) {
                    throw DomainError::bookNotAvailable($items[$index]->bookTitle, "items[{$index}].book_id");
                }
            }
            $this->run(
                'INSERT INTO library.loan (id, member_id, loaned_by_user_id, loaned_at) VALUES (:id, :m, :u, :at)',
                ['id' => $loan->id, 'm' => $loan->memberId, 'u' => $loan->loanedByUserId, 'at' => self::ts($loan->loanedAt)],
            );
            foreach ($items as $i) {
                $this->run(
                    'INSERT INTO library.loan_item (id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at)
                     VALUES (:id, :l, :b, :t, :i, :c, :d, NULL)',
                    ['id' => $i->id, 'l' => $loan->id, 'b' => $i->bookId, 't' => $i->bookTitle, 'i' => $i->isbn, 'c' => $i->categoryName, 'd' => self::ts($i->dueAt)],
                );
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function find(string $id): ?Loan
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $id) !== 1) {
            return null;
        }
        $r = $this->run('SELECT id, member_id, loaned_by_user_id, loaned_at FROM library.loan WHERE id = :id', ['id' => $id])->fetch(\PDO::FETCH_ASSOC);

        return $r ? $this->hydrate([$r])[0] : null;
    }

    public function search(LoanFilter $filter, DateTimeImmutable $now, Paging $paging): Page
    {
        $where = ' WHERE 1=1';
        $p = [];
        if ($filter->from !== null) {
            $where .= ' AND l.loaned_at >= :from';
            $p['from'] = self::ts($filter->from);
        }
        if ($filter->toExclusive !== null) {
            $where .= ' AND l.loaned_at < :to';
            $p['to'] = self::ts($filter->toExclusive);
        }
        if ($filter->memberId !== null) {
            $where .= ' AND l.member_id::text = :mid';
            $p['mid'] = strtolower($filter->memberId);
        }
        // Status is computed from the lines, never stored (CA-08.3).
        $having = match ($filter->status) {
            LoanStatus::Closed => ' HAVING count(i.id) > 0 AND count(i.id) FILTER (WHERE i.returned_at IS NULL) = 0',
            LoanStatus::Overdue => ' HAVING count(i.id) FILTER (WHERE i.returned_at IS NULL AND i.due_at < :now) > 0',
            LoanStatus::Open => ' HAVING (count(i.id) = 0 OR count(i.id) FILTER (WHERE i.returned_at IS NULL) > 0)
                                   AND count(i.id) FILTER (WHERE i.returned_at IS NULL AND i.due_at < :now) = 0',
            null => '',
        };
        if ($filter->status === LoanStatus::Overdue || $filter->status === LoanStatus::Open) {
            $p['now'] = self::ts($now);
        }
        $base = 'FROM library.loan l LEFT JOIN library.loan_item i ON i.loan_id = l.id'.$where.' GROUP BY l.id'.$having;

        $total = (int) $this->run("SELECT count(*) FROM (SELECT l.id {$base}) x", $p)->fetchColumn();
        $rows = $this->run(
            "SELECT l.id, l.member_id, l.loaned_by_user_id, l.loaned_at {$base} ORDER BY l.loaned_at DESC, l.id".self::limit($paging),
            $p,
        )->fetchAll(\PDO::FETCH_ASSOC);

        return new Page($this->hydrate($rows), $paging->page, $paging->size, $total);
    }

    /**
     * Marks the line returned only if it still is not (so a double click never restocks twice) and puts the copy back
     * only while the stock is below the total, all in one transaction (ADR-002).
     */
    public function persistReturn(string $loanId, string $itemId, DateTimeImmutable $now): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->run(
                'UPDATE library.loan_item SET returned_at = :now WHERE id = :id AND loan_id = :l AND returned_at IS NULL RETURNING book_id',
                ['now' => self::ts($now), 'id' => $itemId, 'l' => $loanId],
            );
            $bookId = $st->fetchColumn();
            if ($bookId === false) {
                $exists = $this->run('SELECT 1 FROM library.loan_item WHERE id = :id AND loan_id = :l', ['id' => $itemId, 'l' => $loanId])->fetchColumn();
                throw $exists ? DomainError::itemAlreadyReturned() : DomainError::notFound('La línea del préstamo');
            }
            $this->run('UPDATE library.book SET available_copies = available_copies + 1 WHERE id = :b AND available_copies < total_copies', ['b' => $bookId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        $rows = $this->run(
            'SELECT l.id AS loan_id, i.id AS item_id, i.book_title, m.full_name, i.due_at
             FROM library.loan_item i
             JOIN library.loan l ON l.id = i.loan_id
             JOIN library.member m ON m.id = l.member_id
             WHERE i.returned_at IS NULL AND i.due_at < :now
             ORDER BY i.due_at ASC, i.id',
            ['now' => self::ts($now)],
        )->fetchAll(\PDO::FETCH_ASSOC);

        $out = array_map(static function (array $r) use ($now): OverdueLine {
            $due = self::parse($r['due_at']);
            $days = intdiv($now->getTimestamp() - $due->getTimestamp(), 86400);

            return new OverdueLine($r['loan_id'], $r['item_id'], $r['book_title'], $r['full_name'], $due, $days);
        }, $rows);

        return $out;
    }

    /**
     * @param list<array<string, string>> $loanRows
     * @return list<Loan>
     */
    private function hydrate(array $loanRows): array
    {
        if ($loanRows === []) {
            return [];
        }
        $ids = array_column($loanRows, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $itemRows = $this->run(
            "SELECT id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at FROM library.loan_item WHERE loan_id IN ({$in}) ORDER BY book_title, id",
            $ids,
        )->fetchAll(\PDO::FETCH_ASSOC);
        $byLoan = [];
        foreach ($itemRows as $r) {
            $byLoan[$r['loan_id']][] = new LoanItem(
                $r['id'], $r['book_id'], $r['book_title'], $r['isbn'], $r['category_name'], self::parse($r['due_at']), self::parse($r['returned_at']),
            );
        }

        return array_map(
            static fn (array $r): Loan => Loan::restore($r['id'], $r['member_id'], $r['loaned_by_user_id'], self::parse($r['loaned_at']), $byLoan[$r['id']] ?? []),
            $loanRows,
        );
    }
}
