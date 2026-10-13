<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Book;
use App\Domain\Category;
use App\Domain\Loan;
use App\Domain\Member;
use App\Domain\User;

/** Shared state behind the in-memory repositories so cross-aggregate queries (unreturned lines, overdue) work like the real engine. */
final class InMemoryStore
{
    /** @var array<string, Book> */
    public array $books = [];
    /** @var array<string, Member> */
    public array $members = [];
    /** @var array<string, User> */
    public array $users = [];
    /** @var array<string, Loan> */
    public array $loans = [];
    /** @var list<Category> */
    public array $categories;

    public function __construct()
    {
        // The five seeded categories (data-model §9).
        $this->categories = [
            new Category('11111111-1111-4111-8111-111111111111', 'Novela'),
            new Category('22222222-2222-4222-8222-222222222222', 'Ciencia'),
            new Category('33333333-3333-4333-8333-333333333333', 'Tecnología'),
            new Category('44444444-4444-4444-8444-444444444444', 'Historia'),
            new Category('55555555-5555-4555-8555-555555555555', 'Infantil'),
        ];
    }
}
