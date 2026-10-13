<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Adapters\Inbound\Http\Presenter;
use App\Application\Services\CreateLibrarianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController
{
    public function store(Request $request, CreateLibrarianService $create): JsonResponse
    {
        $b = Input::body($request);
        $user = $create->execute(Input::string($b, 'username'), Input::string($b, 'password'), Input::string($b, 'role'));

        return new JsonResponse(Presenter::user($user), 201);
    }
}
