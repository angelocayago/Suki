<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number')->unique();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status')
                ->default('PLACED');

            $table->string('payment_method')
                ->nullable();

            $table->string('payment_status')
                ->default('UNPAID');

            $table->decimal('subtotal', 12, 2)
                ->default(0);

            $table->decimal('shipping_fee', 12, 2)
                ->default(0);

            $table->decimal('discount_amount', 12, 2)
                ->default(0);

            $table->decimal('total_amount', 12, 2)
                ->default(0);

            $table->string('voucher_code')
                ->nullable();

            $table->string('recipient_name');

            $table->string('recipient_phone');

            $table->string('province');

            $table->string('municipality');

            $table->string('barangay');

            $table->text('street_address');

            $table->text('delivery_notes')
                ->nullable();

            $table->timestamp('placed_at')
                ->nullable();

            $table->timestamp('delivered_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'buyer_id',
                'status',
            ]);

            $table->index([
                'seller_id',
                'status',
            ]);

            $table->index('status');

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};