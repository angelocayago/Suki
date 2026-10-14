<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('current_latitude', 10, 7)->nullable()->after('tracking_code');
            $table->decimal('current_longitude', 10, 7)->nullable()->after('current_latitude');
            $table->timestamp('location_updated_at')->nullable()->after('current_longitude');
            $table->foreignId('location_rider_id')->nullable()->after('rider_id')->constrained('riders')->nullOnDelete();
            $table->index('location_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['location_rider_id']);
            $table->dropIndex(['location_updated_at']);
            $table->dropColumn(['current_latitude', 'current_longitude', 'location_updated_at', 'location_rider_id']);
        });
    }
};
