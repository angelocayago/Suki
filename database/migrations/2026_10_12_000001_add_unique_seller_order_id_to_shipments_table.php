<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('shipments')
            ->select('seller_order_id')
            ->whereNotNull('seller_order_id')
            ->groupBy('seller_order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Duplicate seller_order_id values exist in shipments. Resolve them before adding the unique constraint.');
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->unique('seller_order_id', 'shipments_seller_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique('shipments_seller_order_id_unique');
        });
    }
};
