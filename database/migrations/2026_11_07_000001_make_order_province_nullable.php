<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('province')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Preserve legitimate province-less orders rather than inventing a province.
        if (DB::table('orders')->whereNull('province')->exists()) {
            throw new RuntimeException('Cannot require orders.province while province-less orders exist.');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('province')->nullable(false)->change();
        });
    }
};
