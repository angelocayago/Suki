<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_sorting_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistics_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $table->string('zone_code', 40);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();
            $table->unique(['logistics_provider_id', 'zone_code']);
            $table->index(['logistics_provider_id', 'is_active', 'priority']);
        });

        Schema::create('logistics_sorting_zone_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_sorting_zone_id')->constrained('logistics_sorting_zones')->cascadeOnDelete();
            $table->string('region_code', 20);
            $table->string('province_code', 20)->nullable();
            $table->string('city_municipality_code', 20);
            $table->string('barangay_code', 20)->nullable();
            $table->string('area_key', 100);
            $table->timestamps();
            $table->unique(['logistics_sorting_zone_id', 'area_key'], 'sorting_zone_area_unique');
            $table->index(['region_code', 'province_code', 'city_municipality_code', 'barangay_code'], 'sorting_zone_area_codes_index');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('sorting_zone_id')->nullable()->after('sorting_area')->constrained('logistics_sorting_zones')->nullOnDelete();
            $table->foreignId('logistics_hub_id')->nullable()->after('sorting_zone_id')->constrained('logistics_hubs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sorting_zone_id');
            $table->dropConstrainedForeignId('logistics_hub_id');
        });
        Schema::dropIfExists('logistics_sorting_zone_areas');
        Schema::dropIfExists('logistics_sorting_zones');
    }
};
