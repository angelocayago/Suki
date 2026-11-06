<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) throw new RuntimeException('Only PostgreSQL and SQLite are supported.');
        // Deployment preflight MUST inspect historical duplicate item requests.
        // Stop rather than deleting, merging or choosing a historical request.
        if (DB::table('return_refund_requests')->select('order_item_id')->groupBy('order_item_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Historical OrderItem requests are duplicated; manual review is required before migration. No records were changed.');
        }
        Schema::table('return_refund_requests', fn (Blueprint $table) => $table->unique('order_item_id', 'return_refund_one_item_unique'));
    }

    public function down(): void
    {
        Schema::table('return_refund_requests', fn (Blueprint $table) => $table->dropUnique('return_refund_one_item_unique'));
    }
};
