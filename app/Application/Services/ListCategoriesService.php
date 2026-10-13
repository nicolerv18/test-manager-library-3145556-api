<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\CategoryRepository;
use App\Domain\Category;

final class ListCategoriesService
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /** @return list<Category> */
    public function execute(): array
    {
        return $this->categories->all();
    }
}
