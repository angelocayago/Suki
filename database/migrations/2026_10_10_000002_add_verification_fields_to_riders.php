<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->string('license_number')->nullable()->after('plate_no');
            $table->string('government_id_path')->nullable()->after('license_number');
            $table->string('drivers_license_path')->nullable()->after('government_id_path');
            $table->string('vehicle_document_path')->nullable()->after('drivers_license_path');
            $table->foreignId('approved_by')->nullable()->after('is_active')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'license_number',
                'government_id_path',
                'drivers_license_path',
                'vehicle_document_path',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
