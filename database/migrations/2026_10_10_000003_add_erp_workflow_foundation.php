<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('workflow_status', 40)
                ->default('PLACED')
                ->after('status');

            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_for_pickup_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('at_sorting_center_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('assigned_to_rider_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['order_id', 'workflow_status']);
            $table->index(['seller_id', 'workflow_status']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('pickup_rider_id')
                ->nullable()
                ->after('logistics_provider_id')
                ->constrained('riders')
                ->nullOnDelete();

            $table->string('workflow_status', 40)
                ->default('READY_FOR_PICKUP')
                ->after('status');

            $table->string('sorting_area', 100)->nullable();
            $table->timestamp('pickup_accepted_at')->nullable();
            $table->timestamp('status_changed_at')->nullable();

            $table->index(['pickup_rider_id', 'workflow_status']);
            $table->index(['rider_id', 'workflow_status']);
            $table->index(['logistics_provider_id', 'workflow_status']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['pickup_rider_id', 'workflow_status']);
            $table->dropIndex(['rider_id', 'workflow_status']);
            $table->dropIndex(['logistics_provider_id', 'workflow_status']);
            $table->dropForeign(['pickup_rider_id']);
            $table->dropColumn([
                'pickup_rider_id',
                'workflow_status',
                'sorting_area',
                'pickup_accepted_at',
                'status_changed_at',
            ]);
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'workflow_status']);
            $table->dropIndex(['seller_id', 'workflow_status']);
            $table->dropColumn([
                'workflow_status',
                'status_changed_at',
                'confirmed_at',
                'preparing_at',
                'ready_for_pickup_at',
                'picked_up_at',
                'at_sorting_center_at',
                'sorted_at',
                'assigned_to_rider_at',
                'out_for_delivery_at',
                'completed_at',
            ]);
        });
    }
};
