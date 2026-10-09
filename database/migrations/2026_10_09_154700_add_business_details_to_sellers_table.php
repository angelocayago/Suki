<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('business_category')
                ->nullable()
                ->after('slug');

            $table->string('seller_type')
                ->nullable()
                ->after('business_category');

            $table->string('tin', 12)
                ->nullable()
                ->unique()
                ->after('seller_type');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropUnique(['tin']);
            $table->dropColumn([
                'business_category',
                'seller_type',
                'tin',
            ]);
        });
    }
};
