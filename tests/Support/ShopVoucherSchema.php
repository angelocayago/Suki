<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Extend only disposable memory fixtures; never invokes application migrations. */
class ShopVoucherSchema
{
    public static function create(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) throw new \RuntimeException('Memory-only voucher fixtures required.');
        if (DB::connection()->getDatabaseName() !== ':memory:') throw new \RuntimeException('Non-memory connection refused.');
        if (! Schema::hasTable('vouchers')) DB::statement('CREATE TABLE vouchers (id INTEGER PRIMARY KEY, code TEXT UNIQUE, scope TEXT, seller_id INTEGER, discount_type TEXT, discount_minor INTEGER DEFAULT 0, discount_bps INTEGER DEFAULT 0, minimum_spend_minor INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, starts_at TEXT, expires_at TEXT, usage_limit INTEGER, used_count INTEGER DEFAULT 0, archived_at TEXT, created_at TEXT, updated_at TEXT)');
        foreach (['name'=>'TEXT','description'=>'TEXT','audience'=>'TEXT','max_discount_minor'=>'INTEGER','per_buyer_limit'=>'INTEGER','followers_notified_at'=>'TEXT'] as $column=>$type) if (! Schema::hasColumn('vouchers',$column)) DB::statement('ALTER TABLE vouchers ADD COLUMN '.$column.' '.$type);
        if (! Schema::hasTable('voucher_claims')) DB::statement('CREATE TABLE voucher_claims (id INTEGER PRIMARY KEY, voucher_id INTEGER NOT NULL, buyer_id INTEGER NOT NULL, claimed_at TEXT, UNIQUE(voucher_id,buyer_id))');
        if (! Schema::hasTable('voucher_redemptions')) DB::statement('CREATE TABLE voucher_redemptions (id INTEGER PRIMARY KEY, voucher_id INTEGER, code_snapshot TEXT, discount_minor INTEGER)');
        foreach (['order_id'=>'INTEGER','seller_order_id'=>'INTEGER','buyer_id'=>'INTEGER','seller_id_snapshot'=>'INTEGER','scope_snapshot'=>'TEXT','discount_type_snapshot'=>'TEXT','discount_value_snapshot'=>'INTEGER','created_at'=>'TEXT','updated_at'=>'TEXT'] as $column=>$type) if (! Schema::hasColumn('voucher_redemptions',$column)) DB::statement('ALTER TABLE voucher_redemptions ADD COLUMN '.$column.' '.$type);
        if (! Schema::hasTable('buyer_store_follows')) DB::statement('CREATE TABLE buyer_store_follows (id INTEGER PRIMARY KEY, buyer_id INTEGER, seller_id INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(buyer_id,seller_id))');
        if (! Schema::hasTable('notification_outbox')) DB::statement('CREATE TABLE notification_outbox (id TEXT PRIMARY KEY, user_id INTEGER, title TEXT, message TEXT, context TEXT, available_at TEXT, processed_at TEXT, attempts INTEGER DEFAULT 0, last_error TEXT, created_at TEXT, updated_at TEXT)');
    }
}
