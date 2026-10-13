<?php

declare(strict_types=1);

// CLI helper for PdoPersistenceTest: tries to lend one given book to one given member through the REAL service and PDO adapters.
// Usage: php lend_worker.php <dsn> <user> <book_id> <member_id> <registered_by>
// Prints "OK" or the DomainError code.

$root = dirname(__DIR__, 2);
if (is_file($root.'/vendor/autoload.php')) {
    require $root.'/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $c) use ($root): void {
        if (str_starts_with($c, 'App\\') && is_file($f = $root.'/app/'.str_replace('\\', '/', substr($c, 4)).'.php')) {
            require_once $f;
        }
    });
}

[, $dsn, $user, $bookId, $memberId, $by] = $argv;
$pdo = new PDO($dsn, $user, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$lend = new App\Application\Services\LendBooksService(
    new App\Adapters\Outbound\Persistence\PdoMemberRepository($pdo),
    new App\Adapters\Outbound\Persistence\PdoBookRepository($pdo),
    new App\Adapters\Outbound\Persistence\PdoLoanRepository($pdo),
    new class implements App\Application\Ports\Clock {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
    },
);
try {
    $lend->execute($memberId, [$bookId], $by);
    echo 'OK';
} catch (App\Domain\DomainError $e) {
    echo $e->errorCode;
}
