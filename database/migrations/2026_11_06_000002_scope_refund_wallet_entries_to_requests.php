<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const OLD_IDENTITY = 'seller_wallet_entries_seller_id_seller_order_id_type_unique';

    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) throw new RuntimeException('Only PostgreSQL and SQLite are supported.');
        // Shared deployments require a separate authorized schema preflight.
        $identity = collect(Schema::getIndexes('seller_wallet_entries'))->firstWhere('name', self::OLD_IDENTITY);
        if (!$identity || !$identity['unique'] || $identity['columns'] !== ['seller_id', 'seller_order_id', 'type']
            || Schema::hasColumn('seller_wallet_entries', 'return_refund_request_id')) {
            throw new RuntimeException('Unexpected wallet schema. Stop and review the actual constraints before migration.');
        }
        Schema::table('seller_wallet_entries', function (Blueprint $table) {
            $table->foreignId('return_refund_request_id')->nullable()->constrained('return_refund_requests')->restrictOnDelete();
            $table->unique('return_refund_request_id', 'seller_wallet_refund_request_unique');
            $table->dropUnique(self::OLD_IDENTITY);
        });
        // Supported by both target PostgreSQL and isolated SQLite. NULL legacy
        // identities retain exactly their previous uniqueness; no rows are backfilled.
        DB::statement('CREATE UNIQUE INDEX seller_wallet_legacy_identity_unique ON seller_wallet_entries (seller_id, seller_order_id, type) WHERE return_refund_request_id IS NULL');
    }

    public function down(): void
    {
        if (DB::table('seller_wallet_entries')->whereNotNull('return_refund_request_id')->exists()) {
            throw new RuntimeException('Request-scoped financial entries exist. Disable the feature and retain the expanded schema; destructive rollback is forbidden.');
        }
        Schema::table('seller_wallet_entries', fn (Blueprint $table) => $table->unique(['seller_id', 'seller_order_id', 'type'], self::OLD_IDENTITY));
        Schema::table('seller_wallet_entries', function (Blueprint $table) {
            $table->dropIndex('seller_wallet_legacy_identity_unique');
            $table->dropUnique('seller_wallet_refund_request_unique');
            $table->dropForeign(['return_refund_request_id']);
            $table->dropColumn('return_refund_request_id');
        });
    }
};
