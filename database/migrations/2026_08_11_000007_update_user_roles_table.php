<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->change();
        });

        // PostgreSQL supports DROP/ADD CONSTRAINT.
        // SQLite does not support these ALTER TABLE operations.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS role_check;");
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;");

            DB::statement("ALTER TABLE users ADD CONSTRAINT role_check
                CHECK (role IN ('customer','seller','admin','rider','logistic_owner','guest'));");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS role_check;");
        }
    }
};
