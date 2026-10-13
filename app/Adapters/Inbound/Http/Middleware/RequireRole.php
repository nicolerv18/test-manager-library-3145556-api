<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http\Middleware;

use App\Domain\DomainError;
use App\Domain\Role;
use Closure;
use Illuminate\Http\Request;

/** `role:admin` or `role:admin,librarian`. Runs after Authenticate. A 403 never reveals whether the resource exists. */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $role = $request->attributes->get('auth_role');
        if (!$role instanceof Role || !in_array($role->value, $roles, true)) {
            throw DomainError::forbiddenRole();
        }

        return $next($request);
    }
}
