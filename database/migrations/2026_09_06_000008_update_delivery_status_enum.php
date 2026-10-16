<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL-specific ENUM expansion.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_status ENUM('pending','assigned','assigned_to_rider','in_transit','out_for_delivery','delivered_to_sorting_center','ready_for_delivery_pickup','picked_up_from_sorting_center','delivered','delivery_failed','failed','on_the_way') DEFAULT 'pending'");
        }

        // Preserve legacy delivery status conversions.
        DB::table('orders')
            ->where('delivery_status', 'on_the_way')
            ->update(['delivery_status' => 'out_for_delivery']);

        DB::table('orders')
            ->where('delivery_status', 'failed')
            ->update(['delivery_status' => 'delivery_failed']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_status ENUM('pending','assigned','assigned_to_rider','in_transit','out_for_delivery','delivered_to_sorting_center','ready_for_delivery_pickup','picked_up_from_sorting_center','delivered','delivery_failed') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN delivery_status ENUM('pending','assigned','delivered_to_sorting_center','ready_for_delivery_pickup','picked_up_from_sorting_center','on_the_way','delivered','failed') DEFAULT 'pending'");
        }
    }
};
