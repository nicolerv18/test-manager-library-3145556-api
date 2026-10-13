<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Console;

use App\Application\Services\BootstrapAdminService;
use Illuminate\Console\Command;

/** `php artisan library:bootstrap-admin` — creates the first admin from ADMIN_USERNAME / ADMIN_PASSWORD. Idempotent (CA-02.3). */
final class BootstrapAdminCommand extends Command
{
    protected $signature = 'library:bootstrap-admin';
    protected $description = 'Create the first admin user from the environment if it does not exist yet';

    public function handle(BootstrapAdminService $bootstrap): int
    {
        $username = (string) config('library.admin_username');
        $password = (string) config('library.admin_password');
        if ($username === '' || $password === '') {
            $this->error('ADMIN_USERNAME and ADMIN_PASSWORD must be set.');

            return self::FAILURE;
        }
        $this->info($bootstrap->execute($username, $password) ? 'Admin user created.' : 'Admin user already exists; nothing changed.');

        return self::SUCCESS;
    }
}
