<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE products
                ALTER COLUMN compliance_status SET DEFAULT 'approved'
            ");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE products
                MODIFY COLUMN compliance_status
                ENUM('pending', 'approved', 'flagged', 'rejected', 'auto_flagged')
                NOT NULL DEFAULT 'approved'
            ");
        }

        DB::table('products')
            ->where('compliance_status', 'pending')
            ->update(['compliance_status' => 'approved']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE products
                ALTER COLUMN compliance_status SET DEFAULT 'pending'
            ");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE products
                MODIFY COLUMN compliance_status
                ENUM('pending', 'approved', 'flagged', 'rejected', 'auto_flagged')
                NOT NULL DEFAULT 'pending'
            ");
        }
    }
};
