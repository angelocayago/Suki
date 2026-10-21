<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('region_code', 20)->nullable()->after('region');
            $table->string('province_code', 20)->nullable()->after('province');
            $table->string('city_municipality_code', 20)->nullable()->after('city');
            $table->string('barangay_code', 20)->nullable()->after('barangay');
            $table->index(['city_municipality_code', 'barangay_code'], 'addresses_psgc_lookup_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('region_code', 20)->nullable()->after('province');
            $table->string('province_code', 20)->nullable()->after('region_code');
            $table->string('city_municipality_code', 20)->nullable()->after('municipality');
            $table->string('barangay_code', 20)->nullable()->after('barangay');
            $table->index(['city_municipality_code', 'barangay_code'], 'orders_psgc_snapshot_index');
        });

        Schema::create('rider_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logistics_provider_id')->constrained()->cascadeOnDelete();
            $table->string('region_code', 20);
            $table->string('province_code', 20)->nullable();
            $table->string('city_municipality_code', 20);
            $table->string('barangay_code', 20)->nullable();
            // Stable null-safe key means deactivation/reactivation never creates duplicate authority.
            $table->string('area_key', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['rider_id', 'logistics_provider_id', 'area_key'], 'rider_service_area_unique');
            $table->index(['logistics_provider_id', 'city_municipality_code', 'is_active'], 'rider_service_area_eligibility_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_service_areas');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_psgc_snapshot_index');
            $table->dropColumn(['region_code', 'province_code', 'city_municipality_code', 'barangay_code']);
        });
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_psgc_lookup_index');
            $table->dropColumn(['region_code', 'province_code', 'city_municipality_code', 'barangay_code']);
        });
    }
};
