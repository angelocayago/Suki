<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_compliance_events', function (Blueprint $table) {
            $table->foreignId('account_status_event_id')
                ->nullable()
                ->after('actor_id')
                ->constrained('account_status_events')
                ->nullOnDelete();

            $table->index(
                ['seller_violation_id', 'account_status_event_id'],
                'compliance_events_violation_account_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('seller_compliance_events', function (Blueprint $table) {
            $table->dropIndex('compliance_events_violation_account_index');
            $table->dropConstrainedForeignId('account_status_event_id');
        });
    }
};
