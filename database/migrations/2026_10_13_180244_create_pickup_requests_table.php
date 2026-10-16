<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the table first.
        Schema::create('pickup_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnDelete();

            $table->foreignId('rider_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 20)
                ->default('offered');

            $table->timestamp('offered_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index(['shipment_id', 'status']);
            $table->index(['rider_id', 'status']);
        });

        // PostgreSQL: only one active offer per shipment.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
        CREATE UNIQUE INDEX pickup_requests_one_active_offer
        ON pickup_requests (shipment_id)
        WHERE status = 'offered'
    ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_requests');
    }
};
