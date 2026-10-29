<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('hub_picked_up_at')->nullable();
            $table->foreignId('hub_pickup_rider_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hub_pickup_rider_id');
            $table->dropColumn('hub_picked_up_at');
        });
    }
};
