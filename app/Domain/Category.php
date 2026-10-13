<?php

declare(strict_types=1);

namespace App\Domain;

/** One of the five fixed, seeded categories. Read-only. */
final readonly class Category
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
