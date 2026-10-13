<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Domain\Book;
use App\Domain\DomainError;

/** HU-04 · detail (CA-04.3): a missing or deleted book is 404. */
final class GetBookService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function execute(string $id): Book
    {
        return $this->books->find($id) ?? throw DomainError::notFound('El libro');
    }
}
