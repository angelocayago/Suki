<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('rider_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_wallet_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('type', 50);
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('balance_after_minor')->default(0);
            $table->string('reference', 150)->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->foreign('rider_wallet_id')->references('id')->on('rider_wallets')->cascadeOnDelete();
            $table->index(['rider_wallet_id','created_at']);
            $table->unique(['shipment_id','type'], 'rider_wallet_shipment_type_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('rider_wallet_transactions'); }
};
