<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    public int $verifyCalls = 0;

    public function hash(string $plain): string
    {
        return 'hashed::'.strrev($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        ++$this->verifyCalls;

        return $hash === $this->hash($plain);
    }
}
