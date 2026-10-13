<?php

declare(strict_types=1);

namespace App\Application\Ports;

use DateTimeImmutable;

/** The current instant, always UTC (RN-6). A port so due dates can be tested without waiting 14 days (plan D-05). */
interface Clock
{
    public function now(): DateTimeImmutable;
}
