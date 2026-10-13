<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Domain\DomainError;

/** HU-03 · soft delete (CA-03.5, ADR-003). */
final class DeleteBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $id): void
    {
        $book = $this->books->find($id) ?? throw DomainError::notFound('El libro');
        if ($this->books->hasOpenLoans($id)) {
            throw DomainError::bookHasOpenLoans($book->title());
        }

        $book->softDelete($this->clock->now());
        $this->books->softDelete($book);
    }
}
