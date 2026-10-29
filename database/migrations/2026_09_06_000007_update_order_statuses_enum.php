<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('placed', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested', 'pending', 'processing', 'shipped') DEFAULT 'placed'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check");
        }

        DB::table('orders')->where('status', 'pending')->update(['status' => 'placed']);
        DB::table('orders')->where('status', 'processing')->update(['status' => 'confirmed']);
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'ready_for_pickup']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('placed', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested') DEFAULT 'placed'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'placed'");

            DB::statement("
                ALTER TABLE orders
                ADD CONSTRAINT orders_status_check
                CHECK (status IN (
                    'placed', 'confirmed', 'preparing',
                    'ready_for_pickup', 'picked_up',
                    'at_sorting_center', 'sorted',
                    'assigned_to_rider', 'out_for_delivery',
                    'delivered', 'completed', 'delivery_failed',
                    'returned', 'cancelled',
                    'reschedule_requested', 'rescheduled',
                    'return_requested'
                ))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'reschedule_requested', 'rescheduled', 'return_requested') DEFAULT 'pending'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check");
        }

        DB::table('orders')->where('status', 'placed')->update(['status' => 'pending']);
        DB::table('orders')->where('status', 'confirmed')->update(['status' => 'processing']);
        DB::table('orders')->where('status', 'ready_for_pickup')->update(['status' => 'shipped']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending'");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'pending'");
        }
    }
};
