<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_registration_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Business registration information
            $table->string('seller_type', 50);
            $table->string('tin', 20)->nullable();
            $table->string('dti_registration_number', 100)->nullable();
            $table->string('sec_registration_number', 100)->nullable();

            // Private Supabase document paths
            $table->string('dti_certificate')->nullable();
            $table->string('sec_certificate')->nullable();
            $table->string('bir_form_2303')->nullable();

            // Separate business pickup address
            $table->string('pickup_region', 150);
            $table->string('pickup_province', 150);
            $table->string('pickup_city', 150);
            $table->string('pickup_barangay', 150);
            $table->string('pickup_street');
            $table->string('pickup_postal_code', 4);

            $table->timestamps();

            $table->index('seller_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_registration_details');
    }
};
