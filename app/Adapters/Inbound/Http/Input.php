<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http;

use App\Domain\DomainError;
use App\Domain\Uuid;
use Illuminate\Http\Request;

/** Reads and type-checks request input. Anything malformed becomes VALIDATION_ERROR (422) with the field name. */
final class Input
{
    /** @return array<string, mixed> */
    public static function body(Request $request): array
    {
        $data = $request->json()->all();
        if ($data === [] && trim($request->getContent()) !== '' && !is_array(json_decode($request->getContent(), true))) {
            throw DomainError::invalid('body', 'debe ser un objeto JSON');
        }

        return $data;
    }

    /** @param array<string, mixed> $d */
    public static function string(array $d, string $key): string
    {
        $v = $d[$key] ?? null;
        if (!is_string($v) || trim($v) === '') {
            throw DomainError::invalid($key, 'es obligatorio y debe ser texto');
        }

        return $v;
    }

    /** @param array<string, mixed> $d */
    public static function int(array $d, string $key): int
    {
        $v = $d[$key] ?? null;
        if (!is_int($v)) {
            throw DomainError::invalid($key, 'es obligatorio y debe ser un número entero');
        }

        return $v;
    }

    /** @param array<string, mixed> $d */
    public static function uuid(array $d, string $key): string
    {
        $v = $d[$key] ?? null;
        if (!is_string($v) || !Uuid::isValid($v)) {
            throw DomainError::invalid($key, 'debe ser un UUID válido');
        }

        return strtolower($v);
    }

    public static function queryString(Request $request, string $key): ?string
    {
        $v = $request->query($key);
        if ($v === null) {
            return null;
        }
        if (!is_string($v)) {
            throw DomainError::invalid($key, 'debe ser texto');
        }

        return $v;
    }

    public static function queryUuid(Request $request, string $key): ?string
    {
        $v = self::queryString($request, $key);
        if ($v === null || $v === '') {
            return null;
        }
        if (!Uuid::isValid($v)) {
            throw DomainError::invalid($key, 'debe ser un UUID válido');
        }

        return strtolower($v);
    }

    public static function queryInt(Request $request, string $key): ?int
    {
        $v = self::queryString($request, $key);
        if ($v === null || $v === '') {
            return null;
        }
        if (preg_match('/^-?\d{1,9}$/', $v) !== 1) {
            throw DomainError::invalid($key, 'debe ser un número entero');
        }

        return (int) $v;
    }
}
