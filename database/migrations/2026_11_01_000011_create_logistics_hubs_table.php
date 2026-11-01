<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_hubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('region');
            $table->string('region_code', 20);
            $table->string('province')->nullable();
            $table->string('province_code', 20)->nullable();
            $table->string('city');
            $table->string('city_municipality_code', 20);
            $table->string('barangay');
            $table->string('barangay_code', 20);
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['logistics_provider_id', 'barangay_code'], 'logistics_hubs_provider_barangay_unique');
            $table->index(['logistics_provider_id', 'is_active']);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->foreignId('logistics_hub_id')->nullable()->after('logistics_provider_id')->constrained('logistics_hubs')->nullOnDelete();
            $table->index('logistics_hub_id');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropForeign(['logistics_hub_id']);
            $table->dropIndex(['logistics_hub_id']);
            $table->dropColumn('logistics_hub_id');
        });
        Schema::dropIfExists('logistics_hubs');
    }
};
