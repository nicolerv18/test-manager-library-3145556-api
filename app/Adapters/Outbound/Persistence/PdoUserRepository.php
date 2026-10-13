<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\UserRepository;
use App\Domain\DomainError;
use App\Domain\Role;
use App\Domain\User;

final class PdoUserRepository implements UserRepository
{
    use PdoSupport;

    public function findByUsername(string $username): ?User
    {
        $r = $this->run('SELECT id, username, password_hash, role FROM library."user" WHERE username = :u', ['u' => $username])->fetch(\PDO::FETCH_ASSOC);

        return $r ? User::restore($r['id'], $r['username'], $r['password_hash'], Role::from($r['role'])) : null;
    }

    public function insert(User $user): void
    {
        try {
            $this->run(
                'INSERT INTO library."user" (id, username, password_hash, role) VALUES (:id, :u, :h, :r)',
                ['id' => $user->id, 'u' => $user->username, 'h' => $user->passwordHash, 'r' => $user->role->value],
            );
        } catch (\PDOException $e) {
            throw self::isUnique($e) ? DomainError::usernameTaken() : $e;
        }
    }
}
