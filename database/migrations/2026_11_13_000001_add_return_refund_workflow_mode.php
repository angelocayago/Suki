<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_refund_requests', function (Blueprint $table) {
            $table->enum('workflow_mode', ['FINANCIAL', 'ADMIN_REVIEW'])->default('FINANCIAL');
        });
    }

    public function down(): void
    {
        if (DB::table('return_refund_requests')->where('workflow_mode', '<>', 'FINANCIAL')->exists()) {
            throw new RuntimeException('Retain the workflow discriminator while Admin-review requests exist.');
        }
        Schema::table('return_refund_requests', fn (Blueprint $table) => $table->dropColumn('workflow_mode'));
    }
};
