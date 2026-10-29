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
            // PostgreSQL uses VARCHAR with a CHECK constraint
            // for Laravel enum columns.

            DB::statement("
                ALTER TABLE shipments
                DROP CONSTRAINT IF EXISTS shipments_status_check
            ");

            DB::statement("
                ALTER TABLE shipments
                ALTER COLUMN status SET DEFAULT 'pending'
            ");

            DB::statement("
                ALTER TABLE shipments
                ADD CONSTRAINT shipments_status_check
                CHECK (status IN (
                    'pending',
                    'assigned',
                    'picked_up',
                    'in_transit',
                    'at_sorting_center',
                    'sorted',
                    'staged',
                    'delivered',
                    'cancelled',
                    'delayed',
                    'delivery_failed'
                ))
            ");

            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'at_sorting_center',
                'sorted',
                'staged',
                'delivered',
                'cancelled',
                'delayed',
                'delivery_failed',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE shipments
                DROP CONSTRAINT IF EXISTS shipments_status_check
            ");

            DB::statement("
                ALTER TABLE shipments
                ADD CONSTRAINT shipments_status_check
                CHECK (status IN (
                    'pending',
                    'assigned',
                    'picked_up',
                    'in_transit',
                    'delivered',
                    'cancelled',
                    'delayed'
                ))
            ");

            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'assigned',
                'picked_up',
                'in_transit',
                'delivered',
                'cancelled',
                'delayed',
            ])->default('pending')->change();
        });
    }
};
