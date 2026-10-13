<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\CategoryRepository;
use App\Domain\Category;

final class PdoCategoryRepository implements CategoryRepository
{
    use PdoSupport;

    public function all(): array
    {
        $rows = $this->run('SELECT id, name FROM library.category ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): Category => new Category($r['id'], $r['name']), $rows);
    }

    public function find(string $id): ?Category
    {
        if (!self::isUuid($id)) {
            return null;
        }
        $r = $this->run('SELECT id, name FROM library.category WHERE id = :id', ['id' => $id])->fetch(\PDO::FETCH_ASSOC);

        return $r ? new Category($r['id'], $r['name']) : null;
    }

    private static function isUuid(string $v): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v) === 1;
    }
}
