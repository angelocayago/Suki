<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These expression indexes use PostgreSQL-specific REGEXP_REPLACE.
        // Skip them only when running SQLite-based automated tests.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            CREATE UNIQUE INDEX logistics_sec_normalized_unique
            ON logistics (
                UPPER(
                    REGEXP_REPLACE(
                        sec_registration_number,
                        '[[:space:]-]',
                        '',
                        'g'
                    )
                )
            )
            WHERE sec_registration_number IS NOT NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX logistics_tin_normalized_unique
            ON logistics (
                REGEXP_REPLACE(
                    tin,
                    '[[:space:]-]',
                    '',
                    'g'
                )
            )
            WHERE tin IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            DROP INDEX IF EXISTS logistics_tin_normalized_unique
        ");

        DB::statement("
            DROP INDEX IF EXISTS logistics_sec_normalized_unique
        ");
    }
};