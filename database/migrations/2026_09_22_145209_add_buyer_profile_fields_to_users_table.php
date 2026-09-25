<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('middle_initial', 10)
                ->nullable();

            $table->string('sex', 20)
                ->nullable();

            $table->date('birthday')
                ->nullable();

            $table->string('government_id')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'middle_initial',
                'sex',
                'birthday',
                'government_id',
            ]);
        });
    }
};