<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\PasswordHasher;

/** bcrypt with cost 12 (constitution Article IX). */
final class BcryptPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly int $cost = 12)
    {
    }

    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
