<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\StoredCover;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use App\Domain\Book;
use App\Domain\DomainError;

/**
 * HU-03 · covers (CA-03.4). JPG/PNG up to 2 MB; the type is decided by the CONTENT (magic bytes), never by the extension
 * nor by the declared Content-Type (contract §4). Retention order (data-model §7): the key is changed in the database first;
 * the old binary is deleted only afterwards.
 */
final class CoverService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly BookRepository $books,
        private readonly CoverStorage $storage,
    ) {
    }

    public function set(string $bookId, string $bytes): Book
    {
        $book = $this->books->find($bookId) ?? throw DomainError::notFound('El libro');
        $mime = self::detectMime($bytes);
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw DomainError::invalid('file', 'la portada debe pesar entre 1 byte y 2 MB');
        }
        if ($mime === null) {
            throw DomainError::invalid('file', 'solo se admiten imágenes JPG o PNG');
        }

        $oldKey = $book->coverKey();
        $newKey = $this->storage->store($bytes, $mime);
        $this->books->setCoverKey($bookId, $newKey);
        if ($oldKey !== null) {
            $this->storage->delete($oldKey);
        }

        return $this->books->find($bookId) ?? throw DomainError::notFound('El libro');
    }

    public function remove(string $bookId): Book
    {
        $book = $this->books->find($bookId) ?? throw DomainError::notFound('El libro');
        $oldKey = $book->coverKey();
        if ($oldKey !== null) {
            $this->books->setCoverKey($bookId, null); // first the database...
            $this->storage->delete($oldKey);          // ...then the binary
        }

        return $this->books->find($bookId) ?? throw DomainError::notFound('El libro');
    }

    public function read(string $bookId): StoredCover
    {
        $book = $this->books->find($bookId) ?? throw DomainError::notFound('El libro');
        $key = $book->coverKey();

        return ($key === null ? null : $this->storage->read($key)) ?? throw DomainError::notFound('La portada');
    }

    public static function detectMime(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        return null;
    }
}
