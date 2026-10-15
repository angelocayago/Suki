<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('commission_bps')->default(0)->after('is_active');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('commission_bps_snapshot')->default(0)->after('unit_price_minor');
            $table->unsignedInteger('commission_minor')->default(0)->after('commission_bps_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['commission_bps_snapshot', 'commission_minor']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('commission_bps');
        });
    }
};
