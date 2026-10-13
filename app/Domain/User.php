<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Internal operator. The username is stored lowercase and trimmed (CA-02.2). The domain only ever holds the HASH:
 * it never sees a plain password (constitution Article IX).
 */
final readonly class User
{
    private function __construct(
        public string $id,
        public string $username,
        public string $passwordHash,
        public Role $role,
    ) {
    }

    public static function create(string $id, string $username, string $passwordHash, Role $role): self
    {
        $username = mb_strtolower(trim($username));
        if ($username === '' || mb_strlen($username) > 120) {
            throw DomainError::invalid('username', 'es obligatorio y admite hasta 120 caracteres');
        }
        if ($passwordHash === '') {
            throw DomainError::invalid('password', 'es obligatoria');
        }

        return new self($id, $username, $passwordHash, $role);
    }

    /** Rebuild from storage. */
    public static function restore(string $id, string $username, string $passwordHash, Role $role): self
    {
        return new self($id, $username, $passwordHash, $role);
    }

    public static function normalizeUsername(string $username): string
    {
        return mb_strtolower(trim($username));
    }
}
