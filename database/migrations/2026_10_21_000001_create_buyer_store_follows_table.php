<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('buyer_store_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_id')->constrained();
            $table->timestamps();
            $table->unique(['buyer_id', 'seller_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('buyer_store_follows'); }
};
