<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE products ALTER COLUMN compliance_status SET DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE products ALTER COLUMN compliance_status SET DEFAULT 'approved'"
        );
    }
};
