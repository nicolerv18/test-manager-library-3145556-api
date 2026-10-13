<?php

declare(strict_types=1);

namespace App\Domain;

/** A member's document: required, trimmed, at most 30 characters. Uniqueness is enforced by the database. */
final readonly class DocumentNumber
{
    private function __construct(public string $value)
    {
    }

    public static function of(string $value): self
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 30) {
            throw DomainError::invalid('document_number', 'es obligatorio y admite hasta 30 caracteres');
        }

        return new self($value);
    }
}
