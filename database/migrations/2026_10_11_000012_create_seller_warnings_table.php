<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_violation_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->timestamp('issued_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'issued_at']);
            $table->index(['seller_violation_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_warnings');
    }
};
