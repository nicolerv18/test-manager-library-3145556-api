<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\LoanStatus;
use DateTimeImmutable;

/** Filters of GET /loans. `from` is inclusive and `toExclusive` exclusive, both UTC instants over `loaned_at`. */
final readonly class LoanFilter
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $toExclusive = null,
        public ?LoanStatus $status = null,
        public ?string $memberId = null,
    ) {
    }
}
