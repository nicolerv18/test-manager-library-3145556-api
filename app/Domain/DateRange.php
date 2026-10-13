<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A closed range of UTC instants [start, end] (clarification C-01).
 *
 * Inputs are a date (YYYY-MM-DD) or a UTC instant (ISO 8601). Both ends are inclusive: a date-only `to` covers that whole day.
 * Queries use `start <= x < endExclusive`, so `endExclusive` is one instant after the inclusive end.
 */
final readonly class DateRange
{
    private function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $endExclusive,
    ) {
    }

    public static function parse(string $from, string $to, string $fromField = 'from', string $toField = 'to'): self
    {
        $start = self::instant($from, $fromField, false);
        $end = self::instant($to, $toField, true);

        if ($end <= $start) {
            throw DomainError::invalidDateRange();
        }

        return new self($start, $end);
    }

    /** Open-ended helpers for optional filters: a missing end becomes null in the caller. */
    public static function instant(string $value, string $field, bool $isEnd): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $utc);
            if ($day === false || $day->format('Y-m-d') !== $value) {
                throw DomainError::invalid($field, 'fecha inválida; use AAAA-MM-DD');
            }

            return $isEnd ? $day->modify('+1 day') : $day;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            throw DomainError::invalid($field, 'use una fecha AAAA-MM-DD o un instante ISO 8601');
        }

        try {
            $instant = (new DateTimeImmutable($value))->setTimezone($utc);
        } catch (\Exception) {
            throw DomainError::invalid($field, 'instante inválido');
        }

        // An inclusive instant end must still include the instant itself.
        return $isEnd ? $instant->modify('+1 microsecond') : $instant;
    }
}
