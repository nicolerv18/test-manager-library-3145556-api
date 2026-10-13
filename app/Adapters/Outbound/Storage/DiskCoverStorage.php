<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Storage;

use App\Application\Dto\StoredCover;
use App\Application\Ports\CoverStorage;
use App\Domain\Uuid;

/** Stores covers as files named by an opaque key (`<uuid>.jpg|png`). The database keeps only the key (CA-03.4). */
final class DiskCoverStorage implements CoverStorage
{
    private const KEY = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(jpg|png)$/';

    public function __construct(private readonly string $directory)
    {
    }

    public function store(string $bytes, string $mime): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Cover directory is not writable.');
        }
        $key = Uuid::v4().($mime === 'image/png' ? '.png' : '.jpg');
        if (file_put_contents($this->path($key), $bytes) === false) {
            throw new \RuntimeException('Could not write the cover.');
        }

        return $key;
    }

    public function read(string $key): ?StoredCover
    {
        if (preg_match(self::KEY, $key) !== 1 || !is_file($this->path($key))) {
            return null;
        }
        $bytes = file_get_contents($this->path($key));

        return $bytes === false ? null : new StoredCover($bytes, str_ends_with($key, '.png') ? 'image/png' : 'image/jpeg');
    }

    public function delete(string $key): void
    {
        if (preg_match(self::KEY, $key) === 1 && is_file($this->path($key))) {
            @unlink($this->path($key));
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.$key; // key is validated: no traversal possible
    }
}
