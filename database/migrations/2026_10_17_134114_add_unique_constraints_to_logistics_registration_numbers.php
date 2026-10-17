<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->unique(
                'sec_registration_number',
                'logistics_sec_registration_number_unique'
            );

            $table->unique(
                'tin',
                'logistics_tin_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('logistics', function (Blueprint $table) {
            $table->dropUnique(
                'logistics_sec_registration_number_unique'
            );

            $table->dropUnique(
                'logistics_tin_unique'
            );
        });
    }
};
