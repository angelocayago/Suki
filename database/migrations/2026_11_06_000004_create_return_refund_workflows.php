<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('seller_wallet_entries','source_wallet_entry_id')) throw new RuntimeException('Apply the three financial migrations first.');
        Schema::create('return_refund_workflows', function (Blueprint $t) {
            $t->id();$t->foreignId('return_refund_request_id')->unique()->constrained()->restrictOnDelete();
            $t->text('destination')->nullable(); // encrypted application cast; never public JSON
            $t->string('parcel_status')->default('PENDING');
            $t->text('return_address');$t->string('courier',100)->nullable();$t->string('tracking_number',100)->nullable();
            $t->date('shipped_on')->nullable();$t->timestamp('received_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('inspection_result',30)->nullable();$t->text('inspection_note')->nullable();$t->timestamps();
            $t->index('parcel_status');
        });
    }
    public function down(): void
    {
        if (DB::table('return_refund_workflows')->exists()) throw new RuntimeException('Retain return workflow records; destructive rollback refused.');
        Schema::drop('return_refund_workflows');
    }
};
