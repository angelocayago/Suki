<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('business_category')
                ->constrained('categories')
                ->nullOnDelete();
            $table->string('seller_type', 50)->nullable()->after('category_id');
            $table->string('tin', 50)->nullable()->after('seller_type');
            $table->index(['category_id', 'status'], 'sellers_category_status_index');
        });

        // Only migrate an unambiguous legacy assignment. Multiple pivot rows
        // require an explicit admin decision and are deliberately left unset.
        DB::table('sellers')
            ->whereNull('category_id')
            ->orderBy('id')
            ->chunkById(100, function ($sellers): void {
                foreach ($sellers as $seller) {
                    $categoryIds = DB::table('category_seller')
                        ->where('seller_id', $seller->id)
                        ->pluck('category_id');

                    if ($categoryIds->count() === 1) {
                        DB::table('sellers')
                            ->where('id', $seller->id)
                            ->update(['category_id' => $categoryIds->first()]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropIndex('sellers_category_status_index');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['seller_type', 'tin']);
        });
    }
};
