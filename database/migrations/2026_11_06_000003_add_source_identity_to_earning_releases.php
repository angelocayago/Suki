<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (!in_array($driver, ['pgsql','sqlite'], true)) throw new RuntimeException('Only PostgreSQL and SQLite are supported.');
        if (!Schema::hasColumn('seller_wallet_entries','return_refund_request_id')
            || Schema::hasColumn('seller_wallet_entries','source_wallet_entry_id')
            || DB::table('seller_wallet_entries')->where('type','EARNING_RELEASE')->exists()) {
            throw new RuntimeException('Unexpected earning-release history or schema. Stop for read-only preflight; no backfill is permitted.');
        }
        $indexes = collect(Schema::getIndexes('seller_wallet_entries'))->keyBy('name');
        foreach (['seller_wallet_refund_request_unique'=>['return_refund_request_id'],
            'seller_wallet_legacy_identity_unique'=>['seller_id','seller_order_id','type']] as $name=>$columns) {
            if (!($indexes[$name]['unique'] ?? false) || $indexes[$name]['columns'] !== $columns) {
                throw new RuntimeException('Phase 2 wallet identity migration must be verified first.');
            }
        }
        Schema::table('seller_wallet_entries', function (Blueprint $table) {
            $table->foreignId('source_wallet_entry_id')->nullable()->constrained('seller_wallet_entries')->restrictOnDelete();
            $table->unique('source_wallet_entry_id','seller_wallet_release_source_unique');
        });
        // SQLite's FK table rebuild reproduces index columns but not partial
        // predicates. Restore the approved Phase 2 predicate explicitly.
        if ($driver === 'sqlite') $this->restoreLegacyPredicate();
        // Row shape is enforced by both drivers; cross-row source ownership,
        // amount, completion and settlement are revalidated by the service.
        $shape = "((type <> 'EARNING_RELEASE' AND source_wallet_entry_id IS NULL) OR (type = 'EARNING_RELEASE' AND source_wallet_entry_id IS NOT NULL AND source_wallet_entry_id <> id AND seller_order_id IS NOT NULL AND return_refund_request_id IS NULL AND status = 'POSTED' AND amount_minor > 0))";
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE seller_wallet_entries ADD CONSTRAINT seller_wallet_release_shape CHECK ('.$shape.')');
        } else {
            $newShape = preg_replace('/\b(type|source_wallet_entry_id|id|seller_order_id|return_refund_request_id|status|amount_minor)\b/', 'NEW.$1', $shape);
            foreach (['INSERT','UPDATE'] as $operation) {
                // AFTER sees the generated ID; BEFORE INSERT can expose -1 for
                // NEW.id and incorrectly accept an auto-increment self reference.
                // RAISE(ABORT) still rolls back the entire offending statement.
                DB::unprepared('CREATE TRIGGER seller_wallet_release_shape_'.strtolower($operation).' AFTER '.$operation.' ON seller_wallet_entries WHEN NOT '.$newShape." BEGIN SELECT RAISE(ABORT, 'Invalid earning release shape'); END");
            }
        }
    }

    public function down(): void
    {
        if (DB::table('seller_wallet_entries')->whereNotNull('source_wallet_entry_id')->exists()
            || DB::table('seller_wallet_entries')->where('type','EARNING_RELEASE')->exists()) {
            throw new RuntimeException('Release entries exist. Retain financial schema and disable writers; destructive rollback is forbidden.');
        }
        if (DB::getDriverName() === 'pgsql') DB::statement('ALTER TABLE seller_wallet_entries DROP CONSTRAINT seller_wallet_release_shape');
        else foreach (['insert','update'] as $operation) DB::statement('DROP TRIGGER seller_wallet_release_shape_'.$operation);
        Schema::table('seller_wallet_entries', function (Blueprint $table) {
            $table->dropUnique('seller_wallet_release_source_unique');
            $table->dropForeign(['source_wallet_entry_id']);
            $table->dropColumn('source_wallet_entry_id');
        });
        if (DB::getDriverName() === 'sqlite') $this->restoreLegacyPredicate();
    }

    private function restoreLegacyPredicate(): void
    {
        DB::statement('DROP INDEX seller_wallet_legacy_identity_unique');
        DB::statement('CREATE UNIQUE INDEX seller_wallet_legacy_identity_unique ON seller_wallet_entries (seller_id, seller_order_id, type) WHERE return_refund_request_id IS NULL');
    }
};
