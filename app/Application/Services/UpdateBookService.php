<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\Quantity;

/** HU-03 · PUT replaces title, author, category and total; the ISBN is immutable (C-05). */
final class UpdateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function execute(string $id, string $title, string $author, string $categoryId, int $totalCopies): Book
    {
        $book = $this->books->find($id) ?? throw DomainError::notFound('El libro');
        $category = $this->categories->find($categoryId)
            ?? throw DomainError::invalid('category_id', 'la categoría no existe');

        $book->revise($title, $author, $category, Quantity::of($totalCopies)); // early rejection if available would go below 0
        $this->books->update($book);                                           // the engine applies the same difference atomically

        return $this->books->find($id) ?? throw DomainError::notFound('El libro');
    }
}
