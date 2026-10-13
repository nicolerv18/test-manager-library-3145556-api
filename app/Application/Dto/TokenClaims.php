<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Role;

final readonly class TokenClaims
{
    public function __construct(
        public string $userId,
        public Role $role,
    ) {
    }
}
