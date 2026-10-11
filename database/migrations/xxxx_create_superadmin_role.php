<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {

            DB::table('roles')->insertOrIgnore([
                'name' => 'superadmin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        }
    }


    public function down(): void
    {
        DB::table('roles')
            ->where('name','superadmin')
            ->delete();
    }
};