<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_order_id')
                ->unique()
                ->constrained('seller_orders')
                ->restrictOnDelete();

            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->restrictOnDelete();

            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('commission_minor');
            $table->unsignedBigInteger('net_minor');

            $table->string('status', 30)->default('eligible');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('payout_reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_settlements');
    }
};
