<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\Uuid;

/**
 * Plan D-10: the deployment provisions the initial admin from ADMIN_USERNAME / ADMIN_PASSWORD, because only the hash port can
 * produce the hash. Idempotent: if the user already exists it is left untouched (the password is never overwritten).
 */
final class BootstrapAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /** @return bool true when the admin was created, false when it already existed */
    public function execute(string $username, string $password): bool
    {
        $username = User::normalizeUsername($username);
        if ($this->users->findByUsername($username) !== null) {
            return false;
        }

        $this->users->insert(User::create(Uuid::v4(), $username, $this->hasher->hash($password), Role::Admin));

        return true;
    }
}
