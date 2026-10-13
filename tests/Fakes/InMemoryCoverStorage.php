<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Dto\StoredCover;
use App\Application\Ports\CoverStorage;

final class InMemoryCoverStorage implements CoverStorage
{
    /** @var array<string, StoredCover> */
    public array $files = [];
    private int $seq = 0;

    public function store(string $bytes, string $mime): string
    {
        $key = 'cover-'.++$this->seq; // opaque key, never a path
        $this->files[$key] = new StoredCover($bytes, $mime);

        return $key;
    }

    public function read(string $key): ?StoredCover
    {
        return $this->files[$key] ?? null;
    }

    public function delete(string $key): void
    {
        unset($this->files[$key]);
    }
}
