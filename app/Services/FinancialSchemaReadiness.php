<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only capabilities, not migration execution or permission to activate. */
class FinancialSchemaReadiness
{
    public function releaseColumnsExist(): bool
    {
        return Schema::hasColumns('seller_wallet_entries', ['return_refund_request_id','source_wallet_entry_id']);
    }

    public function inspect(): array
    {
        $issues = [];
        if (!in_array(DB::getDriverName(), ['sqlite','pgsql'], true)) return ['ready'=>false,'issues'=>['Unsupported database driver']];
        if (!$this->releaseColumnsExist()) return ['ready'=>false,'issues'=>['Both financial wallet identity columns are required']];
        foreach (['return_refund_requests'=>['return_refund_one_item_unique'=>['order_item_id']],
            'seller_wallet_entries'=>['seller_wallet_refund_request_unique'=>['return_refund_request_id'],
                'seller_wallet_legacy_identity_unique'=>['seller_id','seller_order_id','type'],
                'seller_wallet_release_source_unique'=>['source_wallet_entry_id']]] as $table=>$expected) {
            $indexes = collect(Schema::getIndexes($table))->keyBy('name');
            foreach ($expected as $name=>$columns) {
                if (!($indexes[$name]['unique'] ?? false) || $indexes[$name]['columns'] !== $columns) $issues[] = 'Missing or inconsistent '.$name;
            }
            if ($table === 'seller_wallet_entries' && isset($indexes['seller_wallet_entries_seller_id_seller_order_id_type_unique'])) $issues[] = 'Old wallet identity constraint remains';
        }
        $foreignKeys = collect(Schema::getForeignKeys('seller_wallet_entries'));
        foreach (['return_refund_request_id'=>'return_refund_requests','source_wallet_entry_id'=>'seller_wallet_entries'] as $column=>$table) {
            if (!$foreignKeys->contains(fn($fk)=>$fk['columns'] === [$column] && $fk['foreign_table'] === $table
                && $fk['foreign_columns'] === ['id'] && strtolower($fk['on_delete']) === 'restrict')) $issues[] = 'Missing restricted-delete FK for '.$column;
        }
        if (DB::getDriverName() === 'sqlite') {
            $index = DB::table('sqlite_master')->where('type','index')->where('name','seller_wallet_legacy_identity_unique')->value('sql');
            $predicate = preg_split('/\bWHERE\b/i', (string)$index, 2)[1] ?? '';
            foreach (['insert','update'] as $operation) {
                $trigger = DB::table('sqlite_master')->where('type','trigger')->where('name','seller_wallet_release_shape_'.$operation)->value('sql');
                if (!$trigger || !str_contains(strtolower($trigger),'after '.$operation) || !str_contains($this->normalize($trigger),'onseller_wallet_entrieswhennot'.$this->normalize($this->shape()))
                    || !str_contains(strtolower($trigger),'raise(abort')) $issues[] = 'Missing or inconsistent release '.$operation.' trigger';
            }
        } else {
            $predicate = DB::selectOne("SELECT pg_get_expr(i.indpred, i.indrelid) AS predicate FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid WHERE i.indrelid = 'seller_wallet_entries'::regclass AND c.relname = ?", ['seller_wallet_legacy_identity_unique'])?->predicate ?? '';
            $check = DB::selectOne("SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid = 'seller_wallet_entries'::regclass AND conname = ? AND contype = 'c' AND convalidated", ['seller_wallet_release_shape'])?->definition ?? '';
            if (!str_contains($this->normalize($check),$this->normalize($this->shape()))) $issues[] = 'Missing or inconsistent release CHECK';
        }
        if ($this->normalize($predicate) !== 'return_refund_request_idisnull') $issues[] = 'Inconsistent legacy partial-index predicate';
        return ['ready'=>$issues === [],'issues'=>$issues];
    }

    private function normalize(string $sql): string
    {
        $sql = preg_replace('/::(?:text|character varying|bigint)/i', '', strtolower($sql));
        return preg_replace('/[\s()"`]+/', '', str_replace('new.', '', $sql));
    }

    private function shape(): string
    {
        return "((type <> 'EARNING_RELEASE' AND source_wallet_entry_id IS NULL) OR (type = 'EARNING_RELEASE' AND source_wallet_entry_id IS NOT NULL AND source_wallet_entry_id <> id AND seller_order_id IS NOT NULL AND return_refund_request_id IS NULL AND status = 'POSTED' AND amount_minor > 0))";
    }
}
