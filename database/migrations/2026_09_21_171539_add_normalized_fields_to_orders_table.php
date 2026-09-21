<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'reference')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('reference')
                    ->nullable()
                    ->unique();
            });
        }

        if (! Schema::hasColumn('orders', 'total_minor')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('total_minor')
                    ->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_address')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->json('shipping_address')
                    ->nullable();
            });
        }

        /*
         * Backfill legacy orders into the normalized fields.
         *
         * Existing legacy columns remain untouched so the
         * current SUKI checkout/order flow keeps working.
         */
        DB::table('orders')
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {

                    $updates = [];

                    if (
                        empty($order->reference) &&
                        ! empty($order->order_number)
                    ) {
                        $updates['reference'] =
                            $order->order_number;
                    }

                    if (
                        $order->total_minor === null &&
                        $order->total_amount !== null
                    ) {
                        $updates['total_minor'] =
                            (int) round(
                                ((float) $order->total_amount) * 100
                            );
                    }

                    if ($order->shipping_address === null) {
                        $updates['shipping_address'] =
                            json_encode([
                                'recipient_name' =>
                                    $order->recipient_name,

                                'recipient_phone' =>
                                    $order->recipient_phone,

                                'province' =>
                                    $order->province,

                                'municipality' =>
                                    $order->municipality,

                                'barangay' =>
                                    $order->barangay,

                                'street_address' =>
                                    $order->street_address,

                                'house_number' =>
                                    $order->house_number,

                                'postal_code' =>
                                    $order->postal_code,

                                'address_label' =>
                                    $order->address_label,
                            ]);
                    }

                    if (! empty($updates)) {
                        DB::table('orders')
                            ->where('id', $order->id)
                            ->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'reference')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique(['reference']);
                $table->dropColumn('reference');
            });
        }

        if (Schema::hasColumn('orders', 'total_minor')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('total_minor');
            });
        }

        if (Schema::hasColumn('orders', 'shipping_address')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('shipping_address');
            });
        }
    }
};