<?php

declare(strict_types=1);

namespace App\Providers;

use App\Adapters\Inbound\Console\BootstrapAdminCommand;
use App\Adapters\Outbound\Persistence\PdoBookRepository;
use App\Adapters\Outbound\Persistence\PdoCategoryRepository;
use App\Adapters\Outbound\Persistence\PdoLoanRepository;
use App\Adapters\Outbound\Persistence\PdoMemberRepository;
use App\Adapters\Outbound\Persistence\PdoReportReader;
use App\Adapters\Outbound\Persistence\PdoUserRepository;
use App\Adapters\Outbound\Security\BcryptPasswordHasher;
use App\Adapters\Outbound\Security\Hs256TokenIssuer;
use App\Adapters\Outbound\Storage\DiskCoverStorage;
use App\Adapters\Outbound\Time\DemoHeaderClock;
use App\Adapters\Outbound\Time\SystemClock;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\ReportReader;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Application\Services\LoginService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use PDO;

/**
 * The ONLY composition point: it binds every port to its adapter (constitution Article III). Nothing else in the application
 * chooses an implementation.
 */
final class PortBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PDO::class, static fn (): PDO => DB::connection()->getPdo());

        foreach ([
            BookRepository::class => PdoBookRepository::class,
            CategoryRepository::class => PdoCategoryRepository::class,
            LoanRepository::class => PdoLoanRepository::class,
            MemberRepository::class => PdoMemberRepository::class,
            ReportReader::class => PdoReportReader::class,
            UserRepository::class => PdoUserRepository::class,
        ] as $port => $adapter) {
            $this->app->bind($port, static fn ($app) => new $adapter($app->make(PDO::class)));
        }

        $this->app->singleton(PasswordHasher::class, static fn (): PasswordHasher => new BcryptPasswordHasher(12));
        $this->app->singleton(TokenIssuer::class, static fn (): TokenIssuer => new Hs256TokenIssuer((string) config('library.jwt_secret')));
        $this->app->singleton(CoverStorage::class, static fn (): CoverStorage => new DiskCoverStorage((string) config('library.covers_dir')));

        // Business "now": honors X-Demo-Now only in APP_ENV=demo (C-09).
        $this->app->bind(Clock::class, fn ($app): Clock => new DemoHeaderClock(
            config('app.env') === 'demo',
            static fn (): ?string => $app->bound('request') ? $app['request']->header('X-Demo-Now') : null,
        ));
        // Authentication always uses the real clock.
        $this->app->bind(LoginService::class, fn ($app): LoginService => new LoginService(
            $app->make(UserRepository::class),
            $app->make(PasswordHasher::class),
            $app->make(TokenIssuer::class),
            new SystemClock(),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapAdminCommand::class]);
        }
    }
}
