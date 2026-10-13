<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\IssuedToken;
use App\Application\Dto\TokenClaims;
use App\Domain\User;
use DateTimeImmutable;

interface TokenIssuer
{
    /** Signs an 8-hour access token carrying only `sub` and `role` (contract §4). */
    public function issue(User $user, DateTimeImmutable $now): IssuedToken;

    /**
     * @throws \App\Domain\DomainError UNAUTHENTICATED when the token is malformed, tampered with or expired
     */
    public function verify(string $token, DateTimeImmutable $now): TokenClaims;
}
