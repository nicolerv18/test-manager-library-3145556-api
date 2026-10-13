<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Application\Ports\BookRepository;
use App\Domain\Book;
use App\Domain\Category;
use App\Domain\DomainError;
use App\Domain\Isbn;

final class PdoBookRepository implements BookRepository
{
    use PdoSupport;

    private const SELECT = 'SELECT b.id, b.title, b.author, b.isbn, b.category_id, c.name AS category_name, b.total_copies, b.available_copies, b.cover_key, b.deleted_at
        FROM library.book b JOIN library.category c ON c.id = b.category_id';

    public function find(string $id): ?Book
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $id) !== 1) {
            return null;
        }
        $r = $this->run(self::SELECT.' WHERE b.id = :id AND b.deleted_at IS NULL', ['id' => $id])->fetch(\PDO::FETCH_ASSOC);

        return $r ? $this->map($r) : null;
    }

    public function findMany(array $ids): array
    {
        $valid = array_values(array_filter($ids, static fn (string $i): bool => preg_match('/^[0-9a-f-]{36}$/i', $i) === 1));
        if ($valid === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($valid), '?'));
        $rows = $this->run(self::SELECT." WHERE b.id IN ({$in}) AND b.deleted_at IS NULL ORDER BY b.id", $valid)->fetchAll(\PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['id']] = $this->map($r);
        }

        return $out;
    }

    public function search(?string $q, ?string $categoryId, Paging $paging): Page
    {
        $where = ' WHERE b.deleted_at IS NULL';
        $p = [];
        if ($q !== null && trim($q) !== '') {
            $where .= ' AND (b.title ILIKE :q OR b.author ILIKE :q)';
            $p['q'] = self::like($q);
        }
        if ($categoryId !== null) {
            $where .= ' AND b.category_id::text = :cat';
            $p['cat'] = strtolower($categoryId);
        }
        $total = (int) $this->run('SELECT count(*) FROM library.book b'.$where, $p)->fetchColumn();
        $rows = $this->run(self::SELECT.$where.' ORDER BY lower(b.title), b.id'.self::limit($paging), $p)->fetchAll(\PDO::FETCH_ASSOC);

        return new Page(array_map(fn (array $r): Book => $this->map($r), $rows), $paging->page, $paging->size, $total);
    }

    public function insert(Book $book): void
    {
        try {
            $this->run(
                'INSERT INTO library.book (id, title, author, isbn, category_id, total_copies, available_copies, cover_key, deleted_at)
                 VALUES (:id, :t, :a, :i, :c, :tot, :av, :ck, NULL)',
                [
                    'id' => $book->id, 't' => $book->title(), 'a' => $book->author(), 'i' => $book->isbn->value,
                    'c' => $book->category()->id, 'tot' => $book->totalCopies(), 'av' => $book->availableCopies(), 'ck' => $book->coverKey(),
                ],
            );
        } catch (\PDOException $e) {
            throw self::isUnique($e) ? DomainError::isbnTaken() : $e;
        }
    }

    /**
     * Applies the DIFFERENCE of totals to the STORED available copies in one statement, so a concurrent loan is never overwritten
     * (ADR-002). `ck_book_available_range` is the last barrier.
     */
    public function update(Book $book): void
    {
        try {
            $st = $this->run(
                'UPDATE library.book SET title = :t, author = :a, category_id = :c,
                        available_copies = available_copies + (:tot - total_copies), total_copies = :tot
                 WHERE id = :id AND deleted_at IS NULL',
                ['t' => $book->title(), 'a' => $book->author(), 'c' => $book->category()->id, 'tot' => $book->totalCopies(), 'id' => $book->id],
            );
        } catch (\PDOException $e) {
            if (($e->errorInfo[0] ?? '') === '23514') { // check_violation
                throw DomainError::invalid('total_copies', 'no puede ser menor que los ejemplares que están prestados');
            }
            throw $e;
        }
        if ($st->rowCount() === 0) {
            throw DomainError::notFound('El libro');
        }
    }

    public function setCoverKey(string $bookId, ?string $coverKey): void
    {
        $this->run('UPDATE library.book SET cover_key = :k WHERE id = :id', ['k' => $coverKey, 'id' => $bookId]);
    }

    public function softDelete(Book $book): void
    {
        $this->run(
            'UPDATE library.book SET deleted_at = :d WHERE id = :id AND deleted_at IS NULL',
            ['d' => self::ts($book->deletedAt() ?? new \DateTimeImmutable()), 'id' => $book->id],
        );
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return (bool) $this->run('SELECT 1 FROM library.loan_item WHERE book_id = :b AND returned_at IS NULL LIMIT 1', ['b' => $bookId])->fetchColumn();
    }

    /** @param array<string, mixed> $r */
    private function map(array $r): Book
    {
        return Book::restore(
            $r['id'], $r['title'], $r['author'], Isbn::of($r['isbn']), new Category($r['category_id'], $r['category_name']),
            (int) $r['total_copies'], (int) $r['available_copies'], $r['cover_key'], self::parse($r['deleted_at']),
        );
    }
}
