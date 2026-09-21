<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            // Shipping option selected by buyer.
            $table->string('shipping_method')->nullable();

            // Additional address snapshot fields.
            $table->string('house_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('address_label')->nullable();

            // Seller processing timestamps.
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_for_pickup_at')->nullable();

            // Pickup rider timestamps.
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('at_sorting_center_at')->nullable();

            // Logistics timestamps.
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('assigned_to_rider_at')->nullable();

            // Delivery rider timestamp.
            $table->timestamp('out_for_delivery_at')->nullable();

            // Alternative delivery flow.
            $table->timestamp('delivery_failed_at')->nullable();
            $table->timestamp('returned_at')->nullable();

            // Buyer cancellation.
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropColumn([
                'shipping_method',
                'house_number',
                'postal_code',
                'address_label',

                'confirmed_at',
                'preparing_at',
                'ready_for_pickup_at',

                'picked_up_at',
                'at_sorting_center_at',

                'sorted_at',
                'assigned_to_rider_at',

                'out_for_delivery_at',

                'delivery_failed_at',
                'returned_at',

                'cancel_reason',
                'cancelled_at',
            ]);
        });
    }
};