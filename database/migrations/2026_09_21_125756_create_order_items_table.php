<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {

            /*
             * New order architecture.
             *
             * Nullable muna habang ginagamit pa ng existing
             * SUKI code ang legacy order_items structure.
             */

            $table->foreignId('seller_order_id')
                ->nullable()
                ->constrained('seller_orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products');

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants');

            $table->string('variant_name')
                ->nullable();

            $table->unsignedInteger('unit_price_minor')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {

            $table->dropForeign([
                'seller_order_id',
            ]);

            $table->dropForeign([
                'product_id',
            ]);

            $table->dropForeign([
                'product_variant_id',
            ]);

            $table->dropColumn([
                'seller_order_id',
                'product_id',
                'product_variant_id',
                'variant_name',
                'unit_price_minor',
            ]);
        });
    }
};