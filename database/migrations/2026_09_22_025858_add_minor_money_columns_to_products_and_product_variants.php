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
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('discounted_price_minor')->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedBigInteger('discounted_price_minor')->nullable();
        });

        // Convert existing peso values into integer centavos.
        foreach (['products', 'product_variants'] as $table) {
            DB::table($table)
                ->whereNotNull('price')
                ->update([
                    'price_minor' => DB::raw('CAST(ROUND(price * 100) AS BIGINT)'),
                ]);

            DB::table($table)
                ->whereNotNull('discounted_price')
                ->update([
                    'discounted_price_minor' => DB::raw(
                        'CAST(ROUND(discounted_price * 100) AS BIGINT)'
                    ),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'price_minor',
                'discounted_price_minor',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'price_minor',
                'discounted_price_minor',
            ]);
        });
    }
};
