<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('seller_discount_minor')->default(0);
            $table->unsignedBigInteger('platform_discount_minor')->default(0);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('seller_discount_minor')->default(0);
            $table->unsignedBigInteger('platform_discount_minor')->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('seller_discount_minor')->default(0);
            $table->unsignedBigInteger('platform_discount_minor')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'discount_minor',
                'seller_discount_minor',
                'platform_discount_minor',
            ]);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'discount_minor',
                'seller_discount_minor',
                'platform_discount_minor',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'discount_minor',
                'seller_discount_minor',
                'platform_discount_minor',
            ]);
        });
    }
};
