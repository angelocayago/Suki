<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_history', function (Blueprint $table) {
            $table->unsignedBigInteger('price_minor')
                ->nullable()
                ->after('price');
        });

        // Convert existing peso prices to integer centavos.
        DB::table('product_price_history')
            ->whereNotNull('price')
            ->update([
                'price_minor' => DB::raw(
                    'CAST(ROUND(price * 100) AS BIGINT)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('product_price_history', function (Blueprint $table) {
            $table->dropColumn('price_minor');
        });
    }
};
