<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            // Company registration
            $table->string('sec_registration_number')->nullable();
            $table->string('tin', 30)->nullable();

            // Business address
            $table->string('business_region')->nullable();
            $table->string('business_province')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_barangay')->nullable();
            $table->string('business_street')->nullable();
            $table->string('business_postal_code', 20)->nullable();

            // Authorized contact person
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_position')->nullable();

            // Operations
            $table->text('service_areas')->nullable();

            // Private document paths
            $table->string('sec_certificate')->nullable();
            $table->string('bir_form_2303')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->dropColumn([
                'sec_registration_number',
                'tin',
                'business_region',
                'business_province',
                'business_city',
                'business_barangay',
                'business_street',
                'business_postal_code',
                'contact_first_name',
                'contact_last_name',
                'contact_position',
                'service_areas',
                'sec_certificate',
                'bir_form_2303',
            ]);
        });
    }
};
