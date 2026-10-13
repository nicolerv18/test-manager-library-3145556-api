<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\OverdueLine;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;

/** HU-09 · unreturned lines with `due_at` before now, most days late first (CA-09.1, CA-09.2). */
final class OverdueService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {
    }

    /** @return list<OverdueLine> */
    public function execute(): array
    {
        return $this->loans->overdueLines($this->clock->now());
    }
}
