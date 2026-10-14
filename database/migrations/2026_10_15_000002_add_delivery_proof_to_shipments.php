<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_events', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('proof_path')->nullable();
            $table->string('proof_mime', 50)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->string('proof_checksum', 64)->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users');
            $table->timestamp('proof_delivered_at')->nullable();
            $table->string('received_by', 150)->nullable();
            $table->text('delivery_note')->nullable();
            $table->unsignedInteger('cod_collected_minor')->nullable();
            $table->timestamp('cod_collected_at')->nullable();
        });
    }

    public function down(): void
    {
        // Keep system-event actors nullable so historical automatic completion events remain valid.
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['delivered_by']);
            $table->dropColumn(['proof_path', 'proof_mime', 'proof_size', 'proof_checksum', 'delivered_by', 'proof_delivered_at', 'received_by', 'delivery_note', 'cod_collected_minor', 'cod_collected_at']);
        });
    }
};
