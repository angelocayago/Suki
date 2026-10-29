<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_remittances', function (Blueprint $table) {
            $table->id();

            $table->string('reference')->unique();

            $table->foreignId('rider_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('logistic_id')
                ->constrained('logistics')
                ->restrictOnDelete();

            $table->unsignedBigInteger('total_minor');

            $table->string('status', 20)->default('pending');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['logistic_id', 'status']);
            $table->index(['rider_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_remittances');
    }
};
