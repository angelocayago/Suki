<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('compliance_status')
                ->default('approved')
                ->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE products DROP CONSTRAINT IF EXISTS compliance_status_check'
            );

            DB::statement("
                ALTER TABLE products
                ADD CONSTRAINT compliance_status_check
                CHECK (compliance_status IN (
                    'pending',
                    'approved',
                    'flagged',
                    'rejected',
                    'auto_flagged'
                ))
            ");
        }

        DB::table('products')
            ->where('compliance_status', 'pending')
            ->update(['compliance_status' => 'approved']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE products DROP CONSTRAINT IF EXISTS compliance_status_check'
            );
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('compliance_status')
                ->default('pending')
                ->change();
        });
    }
};
