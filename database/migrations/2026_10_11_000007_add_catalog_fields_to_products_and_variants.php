<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->json('shipping_options')->nullable()->after('description');
            $table->string('legacy_reference', 100)->nullable()->after('shipping_options');
            $table->unique(['seller_id', 'legacy_reference'], 'products_seller_legacy_reference_unique');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('length_mm')->nullable()->after('weight_grams');
            $table->unsignedInteger('width_mm')->nullable()->after('length_mm');
            $table->unsignedInteger('height_mm')->nullable()->after('width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['length_mm', 'width_mm', 'height_mm']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_seller_legacy_reference_unique');
            $table->dropColumn(['brand', 'shipping_options', 'legacy_reference']);
        });
    }
};
