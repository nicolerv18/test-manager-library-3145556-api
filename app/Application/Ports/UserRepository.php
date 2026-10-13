<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\User;

interface UserRepository
{
    /** The username is looked up already normalized (lowercase, trimmed). */
    public function findByUsername(string $username): ?User;

    /** @throws \App\Domain\DomainError USERNAME_TAKEN */
    public function insert(User $user): void;
}
