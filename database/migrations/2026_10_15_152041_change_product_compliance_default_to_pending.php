<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE products ALTER COLUMN compliance_status SET DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE products ALTER COLUMN compliance_status SET DEFAULT 'approved'"
            );
        }
    }
};
