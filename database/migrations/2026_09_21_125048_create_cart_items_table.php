<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {

            /*
             * New cart architecture.
             *
             * Nullable muna para hindi masira ang existing
             * legacy cart rows.
             */

            $table->foreignId('cart_id')
                ->nullable()
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants');

            $table->boolean('selected')
                ->default(true);

            $table->index('product_variant_id');
            $table->index('selected');

            $table->unique([
                'cart_id',
                'product_variant_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {

            $table->dropUnique([
                'cart_id',
                'product_variant_id',
            ]);

            $table->dropIndex([
                'product_variant_id',
            ]);

            $table->dropIndex([
                'selected',
            ]);

            $table->dropForeign([
                'cart_id',
            ]);

            $table->dropForeign([
                'product_variant_id',
            ]);

            $table->dropColumn([
                'cart_id',
                'product_variant_id',
                'selected',
            ]);
        });
    }
};