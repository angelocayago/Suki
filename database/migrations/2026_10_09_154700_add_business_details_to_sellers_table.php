<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sellers', 'business_category')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->string('business_category')
                    ->nullable()
                    ->after('slug');
            });
        }

        if (!Schema::hasColumn('sellers', 'seller_type')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->string('seller_type')
                    ->nullable()
                    ->after('business_category');
            });
        }

        if (!Schema::hasColumn('sellers', 'tin')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->string('tin', 12)
                    ->nullable()
                    ->unique()
                    ->after('seller_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sellers', 'tin')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropUnique(['tin']);
                $table->dropColumn('tin');
            });
        }

        if (Schema::hasColumn('sellers', 'seller_type')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('seller_type');
            });
        }

        if (Schema::hasColumn('sellers', 'business_category')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('business_category');
            });
        }
    }
};