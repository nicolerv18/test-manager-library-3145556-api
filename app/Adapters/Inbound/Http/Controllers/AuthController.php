<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Controllers;

use App\Adapters\Inbound\Http\Input;
use App\Application\Services\LoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function login(Request $request, LoginService $login): JsonResponse
    {
        $body = Input::body($request);
        $token = $login->execute(Input::string($body, 'username'), Input::string($body, 'password'));

        return new JsonResponse([
            'access_token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $token->expiresIn,
            'role' => $token->role->value,
        ]);
    }

    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
