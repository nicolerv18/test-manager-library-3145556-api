<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Domain\Book;

interface BookRepository
{
    /** Active (not soft-deleted) book by id, or null. */
    public function find(string $id): ?Book;

    /**
     * Active books by a batch of ids (access pattern Q3). Missing or deleted ids are simply absent.
     *
     * @param list<string> $ids
     * @return array<string, Book> keyed by id
     */
    public function findMany(array $ids): array;

    /** @return Page<Book> active books, case-insensitive partial match on title or author, ordered by title */
    public function search(?string $q, ?string $categoryId, Paging $paging): Page;

    /** @throws \App\Domain\DomainError ISBN_TAKEN when another active book has the same ISBN */
    public function insert(Book $book): void;

    /**
     * Persists title, author, category and total. The available copies move by the same difference ATOMICALLY in the engine,
     * so a concurrent loan is never overwritten. ISBN, cover and deletion are not touched.
     *
     * @throws \App\Domain\DomainError VALIDATION_ERROR when the change would leave available copies below zero
     */
    public function update(Book $book): void;

    public function setCoverKey(string $bookId, ?string $coverKey): void;

    public function softDelete(Book $book): void;

    /** True when any line of this book has not been returned yet (CA-03.5). */
    public function hasOpenLoans(string $bookId): bool;
}
