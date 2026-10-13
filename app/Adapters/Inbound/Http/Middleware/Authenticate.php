<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Middleware;

use App\Adapters\Outbound\Time\SystemClock;
use App\Application\Ports\TokenIssuer;
use App\Domain\DomainError;
use Closure;
use Illuminate\Http\Request;

/** Validates the Bearer token. Uses the real clock: the demo header must never affect token expiry. */
final class Authenticate
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $header = (string) $request->header('Authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m) !== 1) {
            throw DomainError::unauthenticated();
        }
        $claims = $this->tokens->verify($m[1], (new SystemClock())->now());
        $request->attributes->set('auth_user_id', $claims->userId);
        $request->attributes->set('auth_role', $claims->role);

        return $next($request);
    }
}
