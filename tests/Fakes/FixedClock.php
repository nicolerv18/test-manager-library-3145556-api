<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $iso = '2026-10-14T14:15:00Z')
    {
        $this->now = (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceDays(int $days): void
    {
        $this->now = $this->now->modify("+{$days} days");
    }

    public function set(string $iso): void
    {
        $this->now = (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone('UTC'));
    }
}
