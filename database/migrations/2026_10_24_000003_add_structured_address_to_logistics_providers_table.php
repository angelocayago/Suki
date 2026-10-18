<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->string('region')->nullable()->after('address');
            $table->string('province')->nullable()->after('region');
            $table->string('city')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropColumn(['region', 'province', 'city', 'barangay']);
        });
    }
};
