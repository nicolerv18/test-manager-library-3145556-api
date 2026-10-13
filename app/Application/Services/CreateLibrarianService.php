<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\DomainError;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\Uuid;

/** HU-02. The `admin` role is provisioned by the deployment; nobody grants it at runtime (CA-02.3). */
final class CreateLibrarianService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    public function execute(string $username, string $password, string $role): User
    {
        if ($role !== Role::Librarian->value) {
            throw DomainError::invalid('role', 'solo se admite el rol "librarian"');
        }
        if ($password === '') {
            throw DomainError::invalid('password', 'es obligatoria');
        }

        $user = User::create(Uuid::v4(), $username, $this->hasher->hash($password), Role::Librarian);
        $this->users->insert($user); // USERNAME_TAKEN if repeated (CA-02.2)

        return $user;
    }
}
