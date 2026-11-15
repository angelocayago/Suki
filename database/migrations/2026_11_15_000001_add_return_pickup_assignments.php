<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_refund_workflows', function (Blueprint $table) {
            $table->unsignedInteger('virtual_shipping_fee_minor')->default(0)->after('return_reference');
            $table->timestamp('picked_up_at')->nullable()->after('logistics_state');
            $table->foreignId('picked_up_by')->nullable()->after('picked_up_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('delivered_to_seller_at')->nullable()->after('seller_received_at');
            $table->foreignId('delivered_to_seller_by')->nullable()->after('delivered_to_seller_at')->constrained('users')->restrictOnDelete();
        });

        Schema::create('return_pickup_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_refund_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('logistics_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->foreignId('rider_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['ASSIGNED', 'ACCEPTED', 'DECLINED', 'COMPLETED', 'CANCELLED'])->default('ASSIGNED');
            $table->text('close_reason')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['return_refund_request_id', 'status']);
            $table->index(['rider_id', 'status']);
            $table->index(['logistics_provider_id', 'return_hub_id', 'status']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Return pickup records are audit history and must not be destructively rolled back.');
    }
};
