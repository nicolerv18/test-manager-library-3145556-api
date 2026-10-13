<?php

declare(strict_types=1);

namespace App\Domain;

/** A person who borrows books. Does not log in; never deleted (CA-05.3). */
final readonly class Member
{
    public function __construct(
        public string $id,
        public string $fullName,
        public DocumentNumber $document,
    ) {
    }

    public static function create(string $id, string $fullName, DocumentNumber $document): self
    {
        $fullName = trim($fullName);
        if ($fullName === '' || mb_strlen($fullName) > 200) {
            throw DomainError::invalid('full_name', 'es obligatorio y admite hasta 200 caracteres');
        }

        return new self($id, $fullName, $document);
    }
}
