<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Current BiliHub shipment.rider_id points to users.id, not riders.id.
        // These rider-wallet tables are still empty at this point, so it is safe
        // to replace their FK targets without migrating wallet data.

        if (Schema::hasTable('rider_withdrawals')) {
            Schema::table('rider_withdrawals', function (Blueprint $table) {
                try {
                    $table->dropForeign(['rider_id']);
                } catch (\Throwable $e) {
                    // SQLite/minimal test schemas may not expose the FK the same way.
                }
            });
        }

        if (Schema::hasTable('rider_wallets')) {
            Schema::table('rider_wallets', function (Blueprint $table) {
                try {
                    $table->dropForeign(['rider_id']);
                } catch (\Throwable $e) {
                    //
                }
            });
        }

        if (Schema::hasTable('rider_wallets')) {
            Schema::table('rider_wallets', function (Blueprint $table) {
                $table->foreign('rider_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('rider_withdrawals')) {
            Schema::table('rider_withdrawals', function (Blueprint $table) {
                $table->foreign('rider_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // No automatic rollback to riders.id because the actual application
        // stores rider assignment as users.id.
    }
};
