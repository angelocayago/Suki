<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)
                ->default('buyer')
                ->after('id')
                ->index();

            $table->string('first_name')
                ->nullable()
                ->after('role');

            $table->string('last_name')
                ->nullable()
                ->after('first_name');

            $table->string('phone', 30)
                ->nullable()
                ->unique()
                ->after('email');

            $table->string('status', 20)
                ->default('active')
                ->after('phone')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);

            $table->dropColumn([
                'role',
                'first_name',
                'last_name',
                'phone',
                'status',
            ]);
        });
    }
};