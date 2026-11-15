<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    private const KEYS = [
        ['rider_cod_remittances', 'shipment_id', 'rider_cod_remittances_shipment_id_unique'],
        ['rider_shipping_fee_earnings', 'seller_order_id', 'rider_shipping_fee_earnings_seller_order_id_unique'],
        ['rider_shipping_fee_earnings', 'shipment_id', 'rider_shipping_fee_earnings_shipment_id_unique'],
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();
        if (!in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Reviewed PostgreSQL/SQLite required for COD/Rider uniqueness.');
        }

        DB::transaction(function () use ($driver) {
            if ($driver === 'pgsql') {
                DB::statement("SET LOCAL lock_timeout = '2s'");
                DB::statement("SET LOCAL statement_timeout = '10s'");
                // Ordinary transactional indexes: readers continue, writers must be paused.
                // Acquire both locks before checking data, closing the check/build race.
                DB::statement('LOCK TABLE public.rider_cod_remittances, public.rider_shipping_fee_earnings IN SHARE MODE');
            }

            foreach (self::KEYS as [$table, $column, $name]) {
                $qualified = $driver === 'pgsql' ? 'public.'.$table : $table;
                if ($driver === 'pgsql') {
                    $shape = DB::selectOne("SELECT c.relkind, a.attnotnull, format_type(a.atttypid,a.atttypmod) AS type
                        FROM pg_class c JOIN pg_attribute a ON a.attrelid=c.oid
                        WHERE c.oid=to_regclass(?) AND a.attname=? AND a.attnum>0 AND NOT a.attisdropped", [$qualified, $column]);
                    if (!$shape || $shape->relkind !== 'r' || !$shape->attnotnull || $shape->type !== 'bigint') {
                        throw new RuntimeException('Unexpected COD/Rider table or key shape: '.$table.'.'.$column);
                    }
                    $collision = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_class WHERE relnamespace='public'::regnamespace AND relname=?)
                        OR EXISTS (SELECT 1 FROM pg_constraint WHERE connamespace='public'::regnamespace AND conname=?) AS present", [$name, $name]);
                    if ($collision->present) {
                        throw new RuntimeException('COD/Rider uniqueness name collision: '.$name);
                    }
                } else {
                    if (!Schema::hasColumn($table, $column)) {
                        throw new RuntimeException('Missing COD/Rider prerequisite: '.$table.'.'.$column);
                    }
                    if (DB::table('sqlite_master')->where('name', $name)->exists()) {
                        throw new RuntimeException('COD/Rider uniqueness name collision: '.$name);
                    }
                }

                // An equivalent preexisting protection is schema drift too: review it,
                // rather than silently adopting, replacing, or dropping another index.
                foreach (Schema::getIndexes($qualified) as $index) {
                    if ($index['unique'] && $index['columns'] === [$column]) {
                        throw new RuntimeException('Existing COD/Rider unique identity requires review: '.$table.'.'.$column);
                    }
                }
                if (DB::table($qualified)->whereNull($column)->exists()
                    || DB::table($qualified)->select($column)->groupBy($column)->havingRaw('COUNT(*) > 1')->exists()) {
                    throw new RuntimeException('Conflicting COD/Rider identities; no records were changed: '.$table.'.'.$column);
                }
            }

            // All three preconditions pass before any index is created. Normal CREATE
            // INDEX rolls back atomically; CONCURRENTLY/IF NOT EXISTS are intentional omissions.
            foreach (self::KEYS as [$table, $column, $name]) {
                $qualified = $driver === 'pgsql' ? 'public."'.$table.'"' : '"'.$table.'"';
                DB::statement('CREATE UNIQUE INDEX "'.$name.'" ON '.$qualified.' ("'.$column.'")');
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain COD/Rider financial identity protections; reversal requires separate owner review.');
    }
};
