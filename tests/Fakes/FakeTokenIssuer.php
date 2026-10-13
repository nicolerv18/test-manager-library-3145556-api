<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Dto\IssuedToken;
use App\Application\Dto\TokenClaims;
use App\Application\Ports\TokenIssuer;
use App\Domain\DomainError;
use App\Domain\Role;
use App\Domain\User;
use DateTimeImmutable;

final class FakeTokenIssuer implements TokenIssuer
{
    public const TTL = 28800; // 8 hours (CA-01.1)

    public function issue(User $user, DateTimeImmutable $now): IssuedToken
    {
        return new IssuedToken('t.'.$user->id.'.'.$user->role->value.'.'.($now->getTimestamp() + self::TTL), self::TTL, $user->role);
    }

    public function verify(string $token, DateTimeImmutable $now): TokenClaims
    {
        $parts = explode('.', $token);
        if (count($parts) !== 4 || $parts[0] !== 't' || (int) $parts[3] < $now->getTimestamp() || Role::tryFrom($parts[2]) === null) {
            throw DomainError::unauthenticated();
        }

        return new TokenClaims($parts[1], Role::from($parts[2]));
    }
}
