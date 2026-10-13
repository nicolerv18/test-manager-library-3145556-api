<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Presenter;
use App\Application\Services\ListCategoriesService;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function index(ListCategoriesService $list): JsonResponse
    {
        return new JsonResponse(['items' => array_map([Presenter::class, 'category'], $list->execute())]);
    }
}
