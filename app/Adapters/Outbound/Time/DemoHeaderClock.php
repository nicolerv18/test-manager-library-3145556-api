<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Time;

use App\Application\Ports\Clock;
use App\Domain\DomainError;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Demo mode (clarification C-09): honors `X-Demo-Now` ONLY when the environment is `demo`. Anywhere else the header is ignored
 * (not an error), so it cannot be abused in production.
 */
final class DemoHeaderClock implements Clock
{
    /** @param \Closure(): ?string $header returns the raw header value for the current request */
    public function __construct(private readonly bool $demoMode, private readonly \Closure $header, private readonly Clock $real = new SystemClock())
    {
    }

    public function now(): DateTimeImmutable
    {
        if (!$this->demoMode) {
            return $this->real->now();
        }
        $raw = ($this->header)();
        if ($raw === null || $raw === '') {
            return $this->real->now();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?Z$/', $raw) !== 1) {
            throw DomainError::invalid('X-Demo-Now', 'debe ser un instante UTC válido, por ejemplo 2026-09-20T10:00:00Z');
        }
        try {
            return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception) {
            throw DomainError::invalid('X-Demo-Now', 'debe ser un instante UTC válido');
        }
    }
}
