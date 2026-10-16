<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            // seller or platform
            $table->string('owner_type', 20);
            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('sellers')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code', 80)->unique();
            $table->text('description')->nullable();

            // fixed or percentage
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('discount_value_minor')->nullable();
            $table->unsignedSmallInteger('discount_percent_bps')->nullable();

            $table->unsignedBigInteger('minimum_spend_minor')->default(0);
            $table->unsignedBigInteger('maximum_discount_minor')->nullable();

            $table->string('product_scope', 20)->default('all');

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('usage_count')->default(0);

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->boolean('is_enabled')->default(true);

            $table->timestampsTz();

            $table->index(['owner_type', 'seller_id']);
            $table->index(['is_enabled', 'starts_at', 'ends_at']);
        });

        Schema::create('voucher_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_id')
                ->constrained('vouchers')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->unique(['voucher_id', 'product_id']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_id')
                ->constrained('vouchers')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('seller_order_id')
                ->nullable()
                ->constrained('seller_orders')
                ->restrictOnDelete();

            $table->string('voucher_code_snapshot', 80);
            $table->string('funded_by', 20);

            $table->unsignedBigInteger('discount_minor');

            // reserved, redeemed, reversed
            $table->string('status', 20)->default('reserved');

            $table->timestampTz('redeemed_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();

            $table->timestampsTz();

            $table->index(['voucher_id', 'user_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('promotion_campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->string('banner_path')->nullable();

            // automatic product sale
            $table->string('campaign_type', 30)->default('automatic');

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->boolean('is_enabled')->default(false);

            $table->timestampsTz();

            $table->index(['is_enabled', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained('promotion_campaigns')
                ->restrictOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // fixed or percentage
            $table->string('discount_type', 20);
            $table->unsignedBigInteger('discount_value_minor')->nullable();
            $table->unsignedSmallInteger('discount_percent_bps')->nullable();

            // seller or platform
            $table->string('funded_by', 20)->default('platform');

            $table->unsignedInteger('quantity_limit')->nullable();
            $table->unsignedInteger('quantity_used')->default(0);

            $table->timestampsTz();

            $table->unique(['campaign_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_products');
        Schema::dropIfExists('promotion_campaigns');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('voucher_products');
        Schema::dropIfExists('vouchers');
    }
};
