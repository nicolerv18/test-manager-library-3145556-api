<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates the whole `library` schema from database/sql/library.sql (raw SQL on purpose: the same file is exercised against a real
 * PostgreSQL in the integration tests). Laravel's own `migrations` table stays in `public`; every query is schema-qualified (risk R-1).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared((string) file_get_contents(database_path('sql/library.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DROP SCHEMA IF EXISTS library CASCADE');
    }
};
