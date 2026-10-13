<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Domain\Book;
use App\Domain\DomainError;
use App\Domain\Isbn;
use App\Domain\Quantity;
use App\Domain\Uuid;

/** HU-03 · create (CA-03.1, CA-03.2). */
final class CreateBookService
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly CategoryRepository $categories,
    ) {
    }

    public function execute(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): Book
    {
        $category = $this->categories->find($categoryId)
            ?? throw DomainError::invalid('category_id', 'la categoría no existe');

        $book = Book::create(Uuid::v4(), $title, $author, Isbn::of($isbn), $category, Quantity::of($totalCopies));
        $this->books->insert($book); // ISBN_TAKEN if another active book has it

        return $book;
    }
}
