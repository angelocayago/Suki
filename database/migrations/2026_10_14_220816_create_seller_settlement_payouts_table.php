<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_settlement_payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_settlement_id')
                ->unique()
                ->constrained('seller_settlements')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');

            $table->string('payment_method', 50);

            $table->string('transaction_reference', 150)
                ->unique();

            $table->timestamp('paid_at');

            $table->foreignId('verified_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('verified_at');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_settlement_payouts');
    }
};
