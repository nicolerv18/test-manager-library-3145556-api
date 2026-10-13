<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\DomainError;

/** Validated pagination: `page >= 1`, `1 <= size <= 50` (contract §1). Defaults: page 1, size 20. */
final readonly class Paging
{
    public const MAX_SIZE = 50;
    public const DEFAULT_SIZE = 20;

    private function __construct(public int $page, public int $size)
    {
    }

    public static function of(?int $page, ?int $size): self
    {
        $page ??= 1;
        $size ??= self::DEFAULT_SIZE;
        if ($page < 1) {
            throw DomainError::invalid('page', 'debe ser 1 o mayor');
        }
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw DomainError::invalid('size', 'debe estar entre 1 y '.self::MAX_SIZE);
        }

        return new self($page, $size);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->size;
    }
}
