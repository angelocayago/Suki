<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_refund_workflows', function (Blueprint $table) {
            $table->foreignId('logistics_provider_id')->nullable()->after('return_refund_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_hub_id')->nullable()->after('logistics_provider_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->string('return_reference', 100)->nullable()->unique()->after('return_hub_id');
            $table->string('logistics_state', 40)->nullable()->index()->after('parcel_status');
            $table->foreignId('hub_received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('hub_received_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('seller_received_at')->nullable();
            $table->foreignId('seller_received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->boolean('stock_restored')->default(false);
            $table->timestamp('stock_restored_at')->nullable();
        });
    }
    public function down(): void { throw new RuntimeException('Return hub workflow data must be retained; destructive rollback is refused.'); }
};
