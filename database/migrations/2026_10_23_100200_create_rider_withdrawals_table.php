<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('rider_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id');
            $table->unsignedBigInteger('rider_wallet_id');
            $table->bigInteger('amount_minor');
            $table->string('payment_method',40);
            $table->string('account_name',150);
            $table->string('account_number',150);
            $table->string('bank_name',150)->nullable();
            $table->string('status',30)->default('pending');
            $table->string('transaction_reference',200)->nullable();
            $table->text('rider_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();
            $table->foreign('rider_id')->references('id')->on('riders')->cascadeOnDelete();
            $table->foreign('rider_wallet_id')->references('id')->on('rider_wallets')->cascadeOnDelete();
            $table->index(['rider_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('rider_withdrawals'); }
};
