<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('application_data')->nullable()->after('application_type');
            $table->json('documents')->nullable()->after('application_data');
            $table->index(['user_id', 'application_type', 'status'], 'applications_owner_type_status_index');
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->string('business_category')->nullable()->after('name');
            $table->string('valid_id_path')->nullable()->after('banner_path');
            $table->string('business_permit_path')->nullable()->after('valid_id_path');
        });

        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->text('address')->nullable()->after('contact_phone');
            $table->string('government_id_path')->nullable()->after('address');
            $table->string('business_permit_path')->nullable()->after('government_id_path');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropColumn(['address', 'government_id_path', 'business_permit_path']);
        });

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['business_category', 'valid_id_path', 'business_permit_path']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_owner_type_status_index');
            $table->dropColumn(['application_data', 'documents']);
        });
    }
};
