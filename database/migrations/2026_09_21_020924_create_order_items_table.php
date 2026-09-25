<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('product_slug');
            $table->string('product_name');
            $table->string('image')->nullable();

            $table->json('variation')->nullable();

            $table->decimal('unit_price', 12, 2);

            $table->unsignedInteger('quantity')
                ->default(1);

            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            $table->index('product_slug');
            $table->index('seller_id');
            $table->index(['order_id', 'seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};