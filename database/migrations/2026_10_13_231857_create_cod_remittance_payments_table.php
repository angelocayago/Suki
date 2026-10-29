<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_remittance_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cod_remittance_id')
                ->constrained('cod_remittances')
                ->restrictOnDelete();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');

            $table->timestamps();

            $table->unique(
                ['cod_remittance_id', 'payment_id'],
                'cod_remittance_payment_unique'
            );

            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_remittance_payments');
    }
};
