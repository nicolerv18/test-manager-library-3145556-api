<?php

declare(strict_types=1);

namespace App\Domain;

/** A number of copies that must be at least 1 (CA-03.3). */
final readonly class Quantity
{
    private function __construct(public int $value)
    {
    }

    public static function of(int $value, string $field = 'total_copies'): self
    {
        if ($value < 1) {
            throw DomainError::invalid($field, 'debe ser al menos 1');
        }

        return new self($value);
    }
}
