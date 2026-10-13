<?php

declare(strict_types=1);

namespace App\Adapters\Outbound\Persistence;

use App\Application\Dto\Paging;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/** Small helpers shared by the PDO repositories. Everything is stored and read in UTC. */
trait PdoSupport
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $params */
    private function run(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue(is_int($k) ? $k + 1 : $k, $v, is_int($v) ? PDO::PARAM_INT : (is_bool($v) ? PDO::PARAM_BOOL : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR)));
        }
        $st->execute();

        return $st;
    }

    private static function ts(DateTimeImmutable $d): string
    {
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    private static function parse(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    /** Escapes LIKE wildcards so user text is always literal (C-06). */
    private static function like(string $q): string
    {
        return '%'.strtr(trim($q), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
    }

    private static function limit(Paging $p): string
    {
        return ' LIMIT '.$p->size.' OFFSET '.$p->offset();
    }

    private static function isUnique(\PDOException $e): bool
    {
        return ($e->errorInfo[0] ?? $e->getCode()) === '23505';
    }
}
