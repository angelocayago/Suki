<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rider_cod_remittances', function (Blueprint $table) {
            $table->foreignId('logistics_hub_id')->nullable()->after('logistics_provider_id')->constrained('logistics_hubs')->restrictOnDelete();
        });
        Schema::table('logistics_cod_remittances', function (Blueprint $table) {
            $table->foreignId('logistics_hub_id')->nullable()->after('logistics_provider_id')->constrained('logistics_hubs')->restrictOnDelete();
        });
        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('logistics_provider_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('logistics_hub_id')->nullable()->constrained('logistics_hubs')->restrictOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('receiver_name', 150)->nullable();
            $table->text('proof_note')->nullable();
        });
        Schema::table('return_refund_accounting', function (Blueprint $table) {
            $table->foreignId('refund_paid_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('refund_paid_at')->nullable();
            $table->string('refund_paid_reference', 100)->nullable();
            $table->text('refund_paid_note')->nullable();
        });
    }
    public function down(): void { throw new RuntimeException('COD settlement and refund audit records must be retained.'); }
};
