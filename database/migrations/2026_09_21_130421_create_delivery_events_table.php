<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipment_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('status');

            $table->unsignedInteger('attempt');

            $table->foreignId('user_id')
                ->constrained();

            $table->text('note')
                ->nullable();

            $table->string('photo_path')
                ->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->unique([
                'shipment_id',
                'status',
                'attempt',
            ]);

            $table->index('status');
            $table->index('user_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
    }
};