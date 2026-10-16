<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('reschedule_reason')->nullable()->after('failure_reason');
            $table->timestamp('reschedule_requested_at')->nullable()->after('reschedule_reason');
            $table->timestamp('rescheduled_at')->nullable()->after('reschedule_requested_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested', 'returned') DEFAULT 'pending'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'reschedule_reason',
                'reschedule_requested_at',
                'rescheduled_at',
            ]);
        });
    }
};
