<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE products ALTER COLUMN compliance_status SET DEFAULT 'pending'"
            );
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->string('compliance_status')
                    ->default('pending')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE products ALTER COLUMN compliance_status SET DEFAULT 'approved'"
            );
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->string('compliance_status')
                    ->default('approved')
                    ->change();
            });
        }
    }
};
