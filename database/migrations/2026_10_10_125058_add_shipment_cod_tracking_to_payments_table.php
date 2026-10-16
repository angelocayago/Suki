<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('shipment_id')
                ->nullable()
                ->constrained('shipments')
                ->restrictOnDelete();

            $table->foreignId('collected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unique('shipment_id', 'payments_shipment_unique');
            $table->index('collected_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_shipment_unique');
            $table->dropForeign(['shipment_id']);
            $table->dropForeign(['collected_by']);
            $table->dropIndex(['collected_by']);

            $table->dropColumn([
                'shipment_id',
                'collected_by',
            ]);
        });
    }
};
