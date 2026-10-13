<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * Aggregate root `Book`. Defends: available copies within [0, total], total >= 1, non-empty title.
 *
 * The in-memory checks give early, friendly rejections; the authoritative stock decision is made by the database
 * (atomic conditional UPDATE + `ck_book_available_range`, see ADR-002).
 */
final class Book
{
    private function __construct(
        public readonly string $id,
        private string $title,
        private string $author,
        public readonly Isbn $isbn,
        private Category $category,
        private int $totalCopies,
        private int $availableCopies,
        private ?string $coverKey,
        private ?DateTimeImmutable $deletedAt,
    ) {
    }

    public static function create(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        Category $category,
        Quantity $totalCopies,
    ): self {
        return new self(
            $id,
            self::cleanTitle($title),
            self::cleanAuthor($author),
            $isbn,
            $category,
            $totalCopies->value,
            $totalCopies->value, // CA-03.1: available starts equal to the total
            null,
            null,
        );
    }

    /** Rebuild from storage; trusts the database, which enforces the invariants. */
    public static function restore(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        Category $category,
        int $totalCopies,
        int $availableCopies,
        ?string $coverKey,
        ?DateTimeImmutable $deletedAt,
    ): self {
        return new self($id, $title, $author, $isbn, $category, $totalCopies, $availableCopies, $coverKey, $deletedAt);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function category(): Category
    {
        return $this->category;
    }

    public function totalCopies(): int
    {
        return $this->totalCopies;
    }

    public function availableCopies(): int
    {
        return $this->availableCopies;
    }

    public function coverKey(): ?string
    {
        return $this->coverKey;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /** Takes one copy out of circulation (CA-06.3). */
    public function withdraw(): void
    {
        if ($this->availableCopies <= 0) {
            throw DomainError::bookNotAvailable($this->title);
        }
        --$this->availableCopies;
    }

    /** Puts one returned copy back (CA-07.1). Never beyond the total (CA-07.3). */
    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw DomainError::invalid('book', 'los ejemplares disponibles no pueden superar el total');
        }
        ++$this->availableCopies;
    }

    /**
     * Replaces title, author, category and total (PUT /books/{id}). The ISBN is immutable (C-05).
     * Changing the total moves the available copies by the same difference and never leaves them below zero (CA-03.3).
     */
    public function revise(string $title, string $author, Category $category, Quantity $totalCopies): void
    {
        $newAvailable = $this->availableCopies + ($totalCopies->value - $this->totalCopies);
        if ($newAvailable < 0) {
            throw DomainError::invalid(
                'total_copies',
                'no puede ser menor que los ejemplares que están prestados ('.($this->totalCopies - $this->availableCopies).')',
            );
        }

        $this->title = self::cleanTitle($title);
        $this->author = self::cleanAuthor($author);
        $this->category = $category;
        $this->totalCopies = $totalCopies->value;
        $this->availableCopies = $newAvailable;
    }

    /** Soft delete (ADR-003). The caller has already checked there are no open loans. */
    public function softDelete(DateTimeImmutable $now): void
    {
        $this->deletedAt = $now;
    }

    public function attachCover(string $key): void
    {
        if (trim($key) === '') {
            throw DomainError::invalid('cover', 'la clave de portada no puede estar vacía');
        }
        $this->coverKey = $key;
    }

    /** Without a cover the field is null, never an empty string (CA-03.4). */
    public function removeCover(): void
    {
        $this->coverKey = null;
    }

    private static function cleanTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 250) {
            throw DomainError::invalid('title', 'es obligatorio y admite hasta 250 caracteres');
        }

        return $title;
    }

    private static function cleanAuthor(string $author): string
    {
        $author = trim($author);
        if ($author === '' || mb_strlen($author) > 200) {
            throw DomainError::invalid('author', 'es obligatorio y admite hasta 200 caracteres');
        }

        return $author;
    }
}
