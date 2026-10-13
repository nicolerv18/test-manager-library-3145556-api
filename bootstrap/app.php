<?php

use App\Adapters\Inbound\Http\ErrorResponder;
use App\Adapters\Inbound\Http\Middleware\Authenticate;
use App\Adapters\Inbound\Http\Middleware\RequireRole;
use App\Domain\DomainError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['jwt' => Authenticate::class, 'role' => RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every failure is rendered as the single error body of the contract (RNF-4).
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);
        $exceptions->dontReport(DomainError::class);
        $exceptions->render(fn (Throwable $e, Request $request) => ErrorResponder::render($e));
    })->create();
