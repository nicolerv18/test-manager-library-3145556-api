<?php

declare(strict_types=1);

namespace App\Domain;

/** A 13-digit ISBN (CA-03.2). Value object: no identity, no table. */
final readonly class Isbn
{
    private function __construct(public string $value)
    {
    }

    public static function of(string $value): self
    {
        $value = trim($value);
        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw DomainError::invalid('isbn', 'debe tener exactamente 13 dígitos');
        }

        return new self($value);
    }
}
