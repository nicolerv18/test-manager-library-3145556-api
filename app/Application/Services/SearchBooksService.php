<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\Page;
use App\Application\Dto\Paging;
use App\Application\Ports\BookRepository;
use App\Domain\Book;

/** HU-04 · search (CA-04.1, CA-04.2). */
final class SearchBooksService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    /** @return Page<Book> */
    public function execute(?string $q, ?string $categoryId, ?int $page, ?int $size): Page
    {
        $q = $q === null ? null : trim($q);

        return $this->books->search($q === '' ? null : $q, $categoryId, Paging::of($page, $size));
    }
}
