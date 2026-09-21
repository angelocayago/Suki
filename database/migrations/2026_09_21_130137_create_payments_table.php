<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('method');

            $table->unsignedInteger('amount_minor');

            $table->string('status');

            $table->string('provider_ref')
                ->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('provider_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};