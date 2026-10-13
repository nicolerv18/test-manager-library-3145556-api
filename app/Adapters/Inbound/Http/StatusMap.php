<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http;

use App\Domain\DomainError;

/** Business error code -> HTTP status (api-contract §1 and §2). The only place that knows HTTP statuses. */
final class StatusMap
{
    private const MAP = [
        DomainError::INVALID_CREDENTIALS => 401,
        DomainError::UNAUTHENTICATED => 401,
        DomainError::FORBIDDEN_ROLE => 403,
        DomainError::NOT_FOUND => 404,
        DomainError::USERNAME_TAKEN => 409,
        DomainError::ISBN_TAKEN => 409,
        DomainError::DOCUMENT_TAKEN => 409,
        DomainError::BOOK_HAS_OPEN_LOANS => 409,
        DomainError::BOOK_NOT_AVAILABLE => 409,
        DomainError::MEMBER_HAS_OVERDUE => 409,
        DomainError::ITEM_ALREADY_RETURNED => 409,
        DomainError::MEMBER_LIMIT_EXCEEDED => 422,
        DomainError::DUPLICATE_BOOK_IN_LOAN => 422,
        DomainError::VALIDATION_ERROR => 422,
        DomainError::INVALID_DATE_RANGE => 422,
    ];

    public static function for(string $code): int
    {
        return self::MAP[$code] ?? 500;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::MAP);
    }
}
