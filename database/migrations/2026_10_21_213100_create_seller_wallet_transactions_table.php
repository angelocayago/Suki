<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_wallet_id')->constrained('seller_wallets')->cascadeOnDelete();
            $table->foreignId('seller_settlement_id')->nullable()->constrained('seller_settlements')->nullOnDelete();
            $table->string('type', 50);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('reference', 150)->nullable();
            $table->string('description', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['seller_wallet_id', 'created_at']);
            $table->unique(
                ['seller_settlement_id', 'type'],
                'seller_wallet_txn_settlement_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_wallet_transactions');
    }
};
