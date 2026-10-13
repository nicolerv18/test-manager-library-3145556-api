<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * The single error type raised by the domain and the application layers.
 *
 * `errorCode` is the machine code of docs/api-contract.md §2. Only the HTTP adapter maps it to a status code and renders the
 * contract body; nothing here knows about HTTP. Messages are shown to end users, so they stay in Spanish (RN-6).
 */
final class DomainError extends \RuntimeException
{
    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const FORBIDDEN_ROLE = 'FORBIDDEN_ROLE';
    public const NOT_FOUND = 'NOT_FOUND';
    public const USERNAME_TAKEN = 'USERNAME_TAKEN';
    public const ISBN_TAKEN = 'ISBN_TAKEN';
    public const DOCUMENT_TAKEN = 'DOCUMENT_TAKEN';
    public const BOOK_HAS_OPEN_LOANS = 'BOOK_HAS_OPEN_LOANS';
    public const BOOK_NOT_AVAILABLE = 'BOOK_NOT_AVAILABLE';
    public const MEMBER_HAS_OVERDUE = 'MEMBER_HAS_OVERDUE';
    public const ITEM_ALREADY_RETURNED = 'ITEM_ALREADY_RETURNED';
    public const MEMBER_LIMIT_EXCEEDED = 'MEMBER_LIMIT_EXCEEDED';
    public const DUPLICATE_BOOK_IN_LOAN = 'DUPLICATE_BOOK_IN_LOAN';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const INVALID_DATE_RANGE = 'INVALID_DATE_RANGE';

    /**
     * @param list<array{field: string, issue: string}> $details
     */
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        // CA-01.2: never reveal whether the username or the password failed.
        return new self(self::INVALID_CREDENTIALS, 'Usuario o contraseña incorrectos.');
    }

    public static function unauthenticated(): self
    {
        return new self(self::UNAUTHENTICATED, 'Se requiere un token válido.');
    }

    public static function forbiddenRole(): self
    {
        return new self(self::FORBIDDEN_ROLE, 'No tiene permisos para realizar esta operación.');
    }

    /** @param list<array{field: string, issue: string}> $details */
    public static function notFound(string $what = 'El recurso', array $details = []): self
    {
        return new self(self::NOT_FOUND, "{$what} no existe.", $details);
    }

    public static function usernameTaken(): self
    {
        return new self(self::USERNAME_TAKEN, 'Ya existe un usuario con ese nombre.');
    }

    public static function isbnTaken(): self
    {
        return new self(self::ISBN_TAKEN, 'Ya existe un libro activo con ese ISBN.');
    }

    public static function documentTaken(): self
    {
        return new self(self::DOCUMENT_TAKEN, 'Ya existe un socio con ese documento.');
    }

    public static function bookHasOpenLoans(string $title): self
    {
        return new self(self::BOOK_HAS_OPEN_LOANS, "El libro «{$title}» tiene préstamos abiertos y no se puede dar de baja.");
    }

    public static function bookNotAvailable(string $title, string $field = 'items[0].book_id'): self
    {
        return new self(
            self::BOOK_NOT_AVAILABLE,
            "No quedan ejemplares disponibles de «{$title}».",
            [['field' => $field, 'issue' => 'sin ejemplares']],
        );
    }

    public static function memberHasOverdue(): self
    {
        return new self(self::MEMBER_HAS_OVERDUE, 'El socio tiene libros vencidos y no puede pedir otro hasta devolverlos.');
    }

    public static function itemAlreadyReturned(): self
    {
        return new self(self::ITEM_ALREADY_RETURNED, 'Esta línea del préstamo ya fue devuelta.');
    }

    public static function memberLimitExceeded(int $current, int $requested, int $max): self
    {
        return new self(
            self::MEMBER_LIMIT_EXCEEDED,
            "El socio superaría el máximo de {$max} libros sin devolver.",
            [['field' => 'items', 'issue' => "tiene {$current} sin devolver y pide {$requested}; máximo {$max}"]],
        );
    }

    public static function duplicateBookInLoan(int $index): self
    {
        return new self(
            self::DUPLICATE_BOOK_IN_LOAN,
            'No se puede repetir el mismo libro en un préstamo.',
            [['field' => "items[{$index}].book_id", 'issue' => 'libro repetido']],
        );
    }

    /** @param list<array{field: string, issue: string}> $details */
    public static function validation(array $details, string $message = 'Hay datos inválidos en la petición.'): self
    {
        return new self(self::VALIDATION_ERROR, $message, $details);
    }

    public static function invalid(string $field, string $issue): self
    {
        return self::validation([['field' => $field, 'issue' => $issue]]);
    }

    public static function invalidDateRange(): self
    {
        return new self(self::INVALID_DATE_RANGE, 'La fecha final no puede ser anterior a la inicial.');
    }
}
