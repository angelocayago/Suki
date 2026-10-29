<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('proof_of_delivery')->nullable();
            $table->text('delivery_signature')->nullable();
            $table->string('delivered_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'proof_of_delivery',
                'delivery_signature',
                'delivered_to',
            ]);
        });
    }
};
