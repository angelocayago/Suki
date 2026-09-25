<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_order_id')
                ->unique()
                ->constrained();

            $table->foreignId('logistics_provider_id')
                ->constrained();

            $table->foreignId('rider_id')
                ->nullable()
                ->constrained();

            $table->string('tracking_code')
                ->unique();

            $table->enum('status', [
                'unassigned',
                'assigned',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'failed',
                'returned',
            ]);

            $table->unsignedInteger('fee_minor');

            $table->unsignedInteger('cod_amount_minor');

            $table->boolean('cod_collected');

            $table->unsignedInteger('attempts');

            $table->timestamps();

            $table->index('logistics_provider_id');
            $table->index('rider_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};