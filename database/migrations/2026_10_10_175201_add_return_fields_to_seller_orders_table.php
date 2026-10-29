<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('return_status', 20)
                ->default('none');

            $table->text('return_reason')
                ->nullable();

            $table->timestamp('return_requested_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'return_status',
                'return_reason',
                'return_requested_at',
            ]);
        });
    }
};
