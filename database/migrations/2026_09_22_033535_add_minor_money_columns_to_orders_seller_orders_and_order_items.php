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
            $table->unsignedBigInteger('subtotal_minor')->nullable();
            $table->unsignedBigInteger('tax_minor')->nullable();
            $table->unsignedBigInteger('shipping_minor')->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
            $table->unsignedBigInteger('amount_collected_minor')->nullable();
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal_minor')->nullable();
            $table->unsignedBigInteger('shipping_minor')->nullable();
            $table->unsignedBigInteger('total_minor')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('subtotal_minor')->nullable();
        });

        // Convert existing peso values to integer centavos.
        $moneyColumns = [
            'orders' => [
                'subtotal',
                'tax',
                'shipping',
                'total',
                'amount_collected',
            ],
            'seller_orders' => [
                'subtotal',
                'shipping',
                'total',
            ],
            'order_items' => [
                'price',
                'subtotal',
            ],
        ];

        foreach ($moneyColumns as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)
                    ->whereNotNull($column)
                    ->update([
                        "{$column}_minor" => DB::raw(
                            "CAST(ROUND({$column} * 100) AS BIGINT)"
                        ),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'price_minor',
                'subtotal_minor',
            ]);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_minor',
                'shipping_minor',
                'total_minor',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_minor',
                'tax_minor',
                'shipping_minor',
                'total_minor',
                'amount_collected_minor',
            ]);
        });
    }
};
