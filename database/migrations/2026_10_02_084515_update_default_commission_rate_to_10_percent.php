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
        Schema::table('sellers', function (Blueprint $table) {
            
            $table->unsignedInteger('commission_bps')
                ->default(1000)
                ->change();

        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {

            $table->unsignedInteger('commission_bps')
                ->default(800)
                ->change();

        });
    }
};