<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\IssuedToken;
use App\Application\Ports\Clock;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Domain\DomainError;
use App\Domain\User;

/** HU-01. Never reveals whether the username or the password failed (CA-01.2). */
final class LoginService
{
    private ?string $decoyHash = null;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $username, string $password): IssuedToken
    {
        $user = $this->users->findByUsername(User::normalizeUsername($username));

        if ($user === null || $password === '') {
            // Spend the same hashing time as a real check so the response time does not reveal which usernames exist.
            $this->hasher->verify($password, $this->decoyHash ??= $this->hasher->hash('decoy-'.bin2hex(random_bytes(8))));
            throw DomainError::invalidCredentials();
        }
        if (!$this->hasher->verify($password, $user->passwordHash)) {
            throw DomainError::invalidCredentials();
        }

        return $this->tokens->issue($user, $this->clock->now());
    }
}
