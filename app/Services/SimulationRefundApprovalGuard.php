<?php

namespace App\Services;

use App\Models\{ReturnRefundRequest, SellerOrder};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/** Cohort eligibility and reverse obligation coverage; never posts money. */
final class SimulationRefundApprovalGuard
{
    public function requireReady(): void
    {
        foreach (['simulation_settlement_mutex','simulation_settlements','simulation_commitments','simulation_refund_scopes','buyer_refund_fundings','return_refund_accounting'] as $table) {
            if (!Schema::hasTable($table)) $this->invalid('Simulation approval coordination schema is unavailable.');
        }
        if (!Schema::hasColumn('return_refund_requests','workflow_mode')) $this->invalid('Simulation review-mode schema is unavailable.');
        $names=['sim_refund_scope_register','sim_refund_request_update','sim_refund_request_delete','sim_refund_scope_update','sim_refund_scope_delete'];
        foreach (['accounting','ledger','funding'] as $kind) foreach (['insert','update','delete'] as $op) $names[]='sim_refund_'.$kind.'_'.$op;
        if (DB::getDriverName()==='pgsql') {
            $count=DB::table('pg_trigger as t')->join('pg_class as c','c.oid','=','t.tgrelid')->join('pg_namespace as n','n.oid','=','c.relnamespace')
                ->where('n.nspname','public')->where('t.tgenabled','O')->whereIn('t.tgname',$names)->count();
            $coverage=DB::table('pg_trigger as t')->join('pg_class as c','c.oid','=','t.tgrelid')->join('pg_namespace as n','n.oid','=','c.relnamespace')
                ->where('n.nspname','public')->where('t.tgenabled','O')->where('t.tgname','sim_refund_coverage')->where('t.tgdeferrable',true)->where('t.tginitdeferred',true)
                ->whereIn('c.relname',['return_refund_requests','return_refund_accounting','seller_wallet_entries','buyer_refund_fundings','simulation_commitments','simulation_refund_scopes','simulation_settlement_mutex','simulation_settlements'])->count();
            if ($coverage!==8) $this->invalid('Simulation approval commit-time constraints are unavailable.');
        } elseif (DB::getDriverName()==='sqlite') {
            $count=DB::table('sqlite_master')->where('type','trigger')->whereIn('name',$names)->count();
        } else $count=0;
        if ($count!==count($names)) $this->invalid('Simulation approval database guards are unavailable.');
    }

    public function assertMode(SellerOrder $purchase, string $mode): bool
    {
        if (!Schema::hasTable('simulation_settlements')) return false;
        $book=DB::table('simulation_settlements')->where('seller_order_id',$purchase->id)->orWhere('order_id',$purchase->order_id)->first();
        if (!$book) return false;
        if ($mode!==ReturnRefundRequest::SELLER_REVIEW) $this->invalid('Simulation purchases support only the owning Seller review workflow.');
        $this->requireReady();
        if ((int)$book->seller_order_id!==(int)$purchase->id || (int)$book->order_id!==(int)$purchase->order_id
            || (int)$book->seller_id!==(int)$purchase->seller_id || (int)$book->buyer_id!==(int)$purchase->order->buyer_id) $this->invalid('Simulation purchase ownership is inconsistent.');
        return true;
    }

    public function assertCoordinated(SellerOrder $purchase, ReturnRefundRequest $request): void
    {
        if (!$this->assertMode($purchase,$request->workflow_mode ?? ReturnRefundRequest::FINANCIAL)) return;
        if (config('simulation_settlement.enabled')!==true || DB::transactionLevel()<1
            || (int)DB::table('simulation_settlement_mutex')->where('id',1)->value('operation_open')!==1) $this->invalid('Simulation refund obligations require the shared funding coordinator.');
    }

    public function verifyCoverage(): void
    {
        if (!Schema::hasTable('simulation_refund_scopes')) {
            if (DB::table('simulation_settlements')->exists()) $this->invalid('Simulation approval coverage schema is unavailable.');
            return;
        }
        if (DB::selectOne('SELECT EXISTS ('.self::violations(Schema::hasTable('simulation_wallet_backings')).') AS invalid')->invalid) $this->invalid('An enrolled refund obligation lacks its exact coordinated commitment.');
    }

    /** PG deferred constraints and SQLite's coordinator use the same predicate.
     * Registering request identity separately avoids SQLite triggers referencing
     * native tables that historical migration tests rebuild.
     */
    public static function violations(bool $walletCredits=false): string
    {
        $sql="SELECT x.return_refund_request_id FROM simulation_refund_scopes x
            JOIN simulation_settlements s ON s.id=x.simulation_settlement_id
            LEFT JOIN return_refund_requests r ON r.id=x.return_refund_request_id
            LEFT JOIN order_items i ON i.id=x.order_item_id
            LEFT JOIN sellers seller ON seller.id=s.seller_id
            LEFT JOIN return_refund_accounting a ON a.return_refund_request_id=x.return_refund_request_id
            LEFT JOIN seller_wallet_entries w ON w.return_refund_request_id=x.return_refund_request_id AND w.type='REFUND_ADJUSTMENT'
            LEFT JOIN buyer_refund_fundings f ON f.return_refund_request_id=x.return_refund_request_id
            LEFT JOIN simulation_commitments c ON c.return_refund_request_id=x.return_refund_request_id
            WHERE (r.status='ADMIN_APPROVED' OR a.id IS NOT NULL OR w.id IS NOT NULL OR f.return_refund_request_id IS NOT NULL OR c.id IS NOT NULL)
            AND (r.id IS NULL OR r.status<>'ADMIN_APPROVED' OR COALESCE(r.workflow_mode,'FINANCIAL')<>'SELLER_REVIEW' OR x.workflow_mode<>'SELLER_REVIEW'
                OR s.issued_minor<>s.cap_minor OR s.issued_minor<1 OR r.seller_order_id<>s.seller_order_id OR r.order_id<>s.order_id OR r.seller_id<>s.seller_id OR r.buyer_id<>s.buyer_id
                OR r.order_item_id<>x.order_item_id OR i.id IS NULL OR i.seller_order_id<>s.seller_order_id OR i.order_id<>s.order_id OR r.quantity<>i.quantity
                OR r.seller_recommendation IS NULL OR r.seller_recommendation<>'APPROVE' OR r.reviewed_by IS NULL OR r.reviewed_by<>seller.user_id OR r.decided_at IS NULL
                OR a.id IS NULL OR w.id IS NULL OR f.return_refund_request_id IS NULL OR c.id IS NULL
                OR a.refund_status<>'REFUND_DUE' OR w.seller_id<>s.seller_id OR w.seller_order_id<>s.seller_order_id OR w.status<>'AVAILABLE' OR w.amount_minor<>-a.seller_adjustment_minor
                OR w.payout_id IS NOT NULL OR w.source_wallet_entry_id IS NOT NULL
                OR f.seller_wallet_entry_id<>w.id OR f.seller_id<>s.seller_id OR f.buyer_id<>s.buyer_id OR f.buyer_refund_minor<>r.refund_minor
                OR f.merchandise_minor<>i.unit_price_minor*i.quantity OR f.seller_discount_minor<>i.seller_funded_discount_minor OR f.platform_discount_minor<>i.platform_funded_discount_minor OR f.commission_reversal_minor<>i.commission_minor
                OR a.merchandise_minor<>f.merchandise_minor OR a.seller_adjustment_minor<>f.seller_adjustment_minor OR a.commission_reversal_minor<>f.commission_reversal_minor
                OR a.seller_voucher_minor<>f.seller_discount_minor OR a.platform_voucher_minor<>f.platform_discount_minor
                OR c.simulation_settlement_id<>s.id OR c.kind<>'REFUND' OR c.status NOT IN ('PARTIAL','RESERVED')
                OR c.seller_minor>f.seller_adjustment_minor OR c.platform_minor>f.buyer_refund_minor-f.seller_adjustment_minor
                OR (c.seller_minor<f.seller_adjustment_minor AND s.seller_available_minor>0)
                OR (c.platform_minor<f.buyer_refund_minor-f.seller_adjustment_minor AND s.platform_available_minor>0)
                OR (c.status='PARTIAL' AND f.status<>'APPROVED_AWAITING_FUNDING')
                OR (c.status='RESERVED' AND (c.seller_minor+c.platform_minor<>f.buyer_refund_minor OR f.status<>'FUNDED_READY_TO_CREDIT'
                    OR f.funding_source_type IS NULL OR f.funding_source_type<>'SIMULATION_SETTLEMENT' OR f.funding_source_reference IS NULL
                    OR f.funding_source_reference<>('SIM-' || CAST(s.id AS TEXT)) OR f.verified_by<>s.issued_by)))
            UNION ALL SELECT r.id FROM return_refund_requests r JOIN simulation_settlements s ON s.seller_order_id=r.seller_order_id
                LEFT JOIN simulation_refund_scopes x ON x.return_refund_request_id=r.id WHERE x.return_refund_request_id IS NULL AND (r.status='ADMIN_APPROVED'
                    OR EXISTS (SELECT 1 FROM return_refund_accounting a WHERE a.return_refund_request_id=r.id)
                    OR EXISTS (SELECT 1 FROM seller_wallet_entries w WHERE w.return_refund_request_id=r.id AND w.type='REFUND_ADJUSTMENT')
                    OR EXISTS (SELECT 1 FROM buyer_refund_fundings f WHERE f.return_refund_request_id=r.id)
                    OR EXISTS (SELECT 1 FROM simulation_commitments c WHERE c.return_refund_request_id=r.id))";
        return $walletCredits ? str_replace("c.status NOT IN ('PARTIAL','RESERVED')","c.status NOT IN ('PARTIAL','RESERVED','WALLET_BACKED')",$sql) : $sql;
    }

    private function invalid(string $message): never { throw ValidationException::withMessages(['simulation'=>$message]); }
}
