<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wishlist_items', 'price_minor')) {
            Schema::table('wishlist_items', function (Blueprint $table) {
                $table->unsignedInteger('price_minor')
                    ->nullable();
            });
        }

        /*
         * Backfill existing legacy prices.
         *
         * Example:
         * 399.00 -> 39900
         */
        if (
            Schema::hasColumn('wishlist_items', 'price') &&
            Schema::hasColumn('wishlist_items', 'price_minor')
        ) {
            DB::table('wishlist_items')
                ->whereNull('price_minor')
                ->orderBy('id')
                ->chunkById(100, function ($items) {
                    foreach ($items as $item) {
                        DB::table('wishlist_items')
                            ->where('id', $item->id)
                            ->update([
                                'price_minor' =>
                                    (int) round(
                                        ((float) $item->price) * 100
                                    ),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wishlist_items', 'price_minor')) {
            Schema::table('wishlist_items', function (Blueprint $table) {
                $table->dropColumn('price_minor');
            });
        }
    }
};