<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Push domain-only invariants down to the database (T-18).
 * Add ck_book_total_positive and ck_user_role constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('ALTER TABLE library.book ADD CONSTRAINT ck_book_total_positive CHECK (total_copies >= 1)');
        DB::unprepared('ALTER TABLE library."user" ADD CONSTRAINT ck_user_role CHECK (role IN (\'admin\', \'librarian\'))');
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE library.book DROP CONSTRAINT IF EXISTS ck_book_total_positive');
        DB::unprepared('ALTER TABLE library."user" DROP CONSTRAINT IF EXISTS ck_user_role');
    }
};
