<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Role;

final readonly class IssuedToken
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public Role $role,
    ) {
    }
}
