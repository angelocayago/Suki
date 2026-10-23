<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('rider_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id')->unique();
            $table->bigInteger('available_balance_minor')->default(0);
            $table->bigInteger('pending_balance_minor')->default(0);
            $table->timestamps();
            $table->foreign('rider_id')->references('id')->on('riders')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('rider_wallets'); }
};
