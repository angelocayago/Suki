<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_wallet_id')->constrained('seller_wallets')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->bigInteger('amount_minor');

            $table->string('payment_method', 50);
            $table->string('account_name', 150);
            $table->string('account_number', 150);
            $table->string('bank_name', 150)->nullable();

            $table->string('status', 30)->default('pending');
            $table->string('transaction_reference', 150)->nullable()->unique();
            $table->text('seller_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_withdrawals');
    }
};
