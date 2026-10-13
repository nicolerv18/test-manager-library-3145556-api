<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\StoredCover;

/** Binary storage for covers. The database keeps only the opaque key (plan D-08). */
interface CoverStorage
{
    /** Stores the bytes and returns a new opaque key (never a path). */
    public function store(string $bytes, string $mime): string;

    public function read(string $key): ?StoredCover;

    public function delete(string $key): void;
}
