<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_assignment_capacity')->default(5)->after('availability');
        });
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->foreignId('main_sorting_hub_id')->nullable()->after('status')->constrained('logistics_hubs')->nullOnDelete();
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('destination_hub_id')->nullable()->after('logistics_hub_id')->constrained('logistics_hubs')->nullOnDelete();
            $table->string('routing_exception_reason')->nullable()->after('destination_hub_id');
            $table->index(['destination_hub_id', 'workflow_status']);
        });

        Schema::create('shipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistics_provider_id')->constrained()->cascadeOnDelete();
            $table->enum('assignment_type', ['PICKUP', 'HUB_TRANSFER', 'DELIVERY']);
            $table->enum('status', ['ASSIGNED', 'ACCEPTED', 'DECLINED', 'COMPLETED', 'CANCELLED'])->default('ASSIGNED');
            $table->foreignId('rider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('origin_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $table->foreignId('destination_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $table->foreignId('assigned_by')->constrained('users');
            $table->string('vehicle_reference')->nullable();
            $table->string('batch_reference')->nullable();
            $table->text('close_reason')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();
            $table->index(['shipment_id', 'assignment_type', 'status'], 'shipment_assignment_active_lookup');
            $table->index(['rider_id', 'status', 'assignment_type'], 'rider_assignment_queue_lookup');
            $table->index(['destination_hub_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_assignments');
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['destination_hub_id', 'workflow_status']);
            $table->dropConstrainedForeignId('destination_hub_id');
            $table->dropColumn('routing_exception_reason');
        });
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn('active_assignment_capacity');
        });
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('main_sorting_hub_id');
        });
    }
};
