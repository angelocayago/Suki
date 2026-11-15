<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

/** Relational credit/backing validation, not an issuance or posting service. */
final class SimulationWalletCreditContract
{
    public const TABLES=['simulation_wallet_backings','buyer_wallet_entries','buyer_refund_fundings','simulation_commitments','simulation_settlements','simulation_settlement_mutex','return_refund_requests','return_refund_accounting','seller_wallet_entries','simulation_settlement_events','simulation_refund_scopes','payouts'];

    public function requireReady(): void
    {
        app(SimulationRefundApprovalGuard::class)->requireReady();
        if (!Schema::hasColumns('simulation_wallet_backings',['simulation_commitment_id','simulation_settlement_id','return_refund_request_id','buyer_id','buyer_wallet_entry_id','settlement_event_id','kind','amount_minor','seller_minor','platform_minor','source_identity','credited_by','created_at'])) $this->invalid('Simulation credit schema is unavailable.');
        $names=['sim_wallet_entry_insert','sim_wallet_entry_update','sim_wallet_entry_delete','sim_wallet_backing_insert','sim_wallet_backing_update','sim_wallet_backing_delete'];
        if (DB::getDriverName()==='pgsql') {
            $base=DB::table('pg_trigger as t')->join('pg_class as c','c.oid','=','t.tgrelid')->join('pg_namespace as n','n.oid','=','c.relnamespace')->where('n.nspname','public')->where('t.tgenabled','O');
            $count=(clone $base)->whereIn('t.tgname',$names)->count();
            $deferred=(clone $base)->where('t.tgname','sim_wallet_credit_validate')->whereIn('c.relname',self::TABLES)->where('t.tgdeferrable',true)->where('t.tginitdeferred',true)->count();
            if ($deferred!==count(self::TABLES)) $this->invalid('Simulation credit commit-time checks are unavailable.');
        } elseif (DB::getDriverName()==='sqlite') $count=DB::table('sqlite_master')->where('type','trigger')->whereIn('name',$names)->count();
        else $count=0;
        if ($count!==count($names)) $this->invalid('Simulation credit write guards are unavailable.');
    }

    public function verify(): void
    {
        if (!Schema::hasTable('simulation_wallet_backings') || !DB::table('simulation_settlements')->exists()) return;
        $this->requireReady();
        $sql=self::bookViolations(false).' UNION ALL '.self::creditViolations();
        if (DB::selectOne('SELECT EXISTS ('.$sql.') AS invalid')->invalid) $this->invalid('Simulation wallet backing, credit or conservation does not reconcile.');
    }

    /** Preserves W5I checks; only active refund reservations and the two distinct
     * consumption components change. Original Seller/platform attribution keeps
     * the consumed REFUND parent as provenance and never counts backing twice.
     */
    public static function bookViolations(bool $nativeLedger,bool $walletCredits=true): string
    {
        $sql="SELECT s.id FROM simulation_settlements s WHERE s.issued_minor > 0 AND (
            s.refund_reserved_minor <> COALESCE((SELECT SUM(c.seller_minor+c.platform_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='REFUND' AND c.status IN ('PARTIAL','RESERVED')),0)
            OR s.payout_reserved_minor <> COALESCE((SELECT SUM(c.seller_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='PAYOUT' AND c.status IN ('PENDING','APPROVED')),0)
            OR s.consumed_minor <> COALESCE((SELECT SUM(c.seller_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='PAYOUT' AND c.status='PAID'),0) + COALESCE((SELECT SUM(b.amount_minor) FROM simulation_wallet_backings b WHERE b.simulation_settlement_id=s.id),0)
            OR s.seller_claim_minor <> s.seller_available_minor + COALESCE((SELECT SUM(c.seller_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND (c.kind='REFUND' OR c.status IN ('PENDING','APPROVED','PAID'))),0)
            OR s.platform_claim_minor <> s.platform_available_minor + COALESCE((SELECT SUM(c.platform_minor) FROM simulation_commitments c WHERE c.simulation_settlement_id=s.id AND c.kind='REFUND'),0))
            UNION ALL SELECT s.id FROM simulation_settlements s JOIN payouts p ON p.seller_id=s.seller_id LEFT JOIN simulation_commitments c ON c.payout_id=p.id WHERE c.id IS NULL OR c.simulation_settlement_id<>s.id OR c.kind<>'PAYOUT' OR c.status<>p.status OR c.seller_minor<>p.amount_minor
            UNION ALL SELECT c.id FROM simulation_commitments c JOIN simulation_settlements s ON s.id=c.simulation_settlement_id LEFT JOIN payouts p ON p.id=c.payout_id WHERE c.kind='PAYOUT' AND (p.id IS NULL OR p.seller_id<>s.seller_id)";
        if ($nativeLedger) $sql.=" UNION ALL SELECT c.id FROM simulation_commitments c JOIN simulation_settlements s ON s.id=c.simulation_settlement_id LEFT JOIN buyer_refund_fundings f ON f.return_refund_request_id=c.return_refund_request_id LEFT JOIN return_refund_requests r ON r.id=c.return_refund_request_id WHERE c.kind='REFUND' AND (f.return_refund_request_id IS NULL OR r.id IS NULL OR r.seller_order_id<>s.seller_order_id OR r.buyer_id<>s.buyer_id OR r.seller_id<>s.seller_id OR r.status<>'ADMIN_APPROVED' OR f.seller_id<>s.seller_id OR f.buyer_id<>s.buyer_id OR c.seller_minor>f.seller_adjustment_minor OR c.platform_minor>f.buyer_refund_minor-f.seller_adjustment_minor OR (c.status='RESERVED' AND (c.seller_minor+c.platform_minor<>f.buyer_refund_minor OR f.status<>'FUNDED_READY_TO_CREDIT' OR f.funding_source_type<>'SIMULATION_SETTLEMENT')) OR (c.status='PARTIAL' AND f.status<>'APPROVED_AWAITING_FUNDING'))
            UNION ALL SELECT c.id FROM simulation_commitments c JOIN simulation_settlements s ON s.id=c.simulation_settlement_id LEFT JOIN payouts p ON p.id=c.payout_id WHERE c.kind='PAYOUT' AND (p.id IS NULL OR p.seller_id<>s.seller_id OR (SELECT COUNT(*) FROM seller_wallet_entries w WHERE w.payout_id=p.id AND w.seller_id=p.seller_id AND w.type='PAYOUT_RESERVATION' AND w.amount_minor=p.amount_minor AND w.status=CASE p.status WHEN 'PAID' THEN 'SETTLED' WHEN 'REJECTED' THEN 'RELEASED' ELSE 'RESERVED' END)<>1 OR (p.status='PAID' AND (SELECT COUNT(*) FROM seller_wallet_entries w WHERE w.payout_id=p.id AND w.seller_id=p.seller_id AND w.type='PAYOUT_COMPLETED' AND w.status='POSTED' AND w.amount_minor=p.amount_minor)<>1))
            UNION ALL SELECT id FROM simulation_settlement_mutex WHERE operation_open<>0";
        if (!$walletCredits) {
            $sql=str_replace(" AND c.kind='REFUND' AND c.status IN ('PARTIAL','RESERVED')"," AND c.kind='REFUND'",$sql);
            $sql=str_replace(" + COALESCE((SELECT SUM(b.amount_minor) FROM simulation_wallet_backings b WHERE b.simulation_settlement_id=s.id),0)",'',$sql);
        }
        return $sql;
    }

    public static function creditViolations(): string
    {
        return "SELECT b.simulation_commitment_id FROM simulation_wallet_backings b
            LEFT JOIN simulation_commitments c ON c.id=b.simulation_commitment_id
            LEFT JOIN simulation_settlements s ON s.id=b.simulation_settlement_id
            LEFT JOIN buyer_refund_fundings f ON f.return_refund_request_id=b.return_refund_request_id
            LEFT JOIN buyer_wallet_entries w ON w.id=b.buyer_wallet_entry_id
            LEFT JOIN simulation_settlement_events e ON e.id=b.settlement_event_id
            WHERE c.id IS NULL OR s.id IS NULL OR f.return_refund_request_id IS NULL OR w.id IS NULL OR e.id IS NULL
                OR b.kind<>'BUYER_REFUND_BACKING' OR b.currency<>'PHP' OR s.currency<>b.currency OR s.issued_minor<>s.cap_minor OR s.issued_minor<1
                OR c.kind<>'REFUND' OR c.status<>'WALLET_BACKED' OR c.simulation_settlement_id<>s.id OR c.return_refund_request_id<>b.return_refund_request_id
                OR c.seller_minor<>b.seller_minor OR c.platform_minor<>b.platform_minor OR b.amount_minor<>c.seller_minor+c.platform_minor
                OR b.buyer_id<>s.buyer_id OR b.buyer_id<>f.buyer_id OR f.status<>'CREDITED_TO_BUYER_WALLET' OR f.buyer_refund_minor<>b.amount_minor OR f.funded_minor<>b.amount_minor
                OR f.funding_source_type IS NULL OR f.funding_source_type<>'SIMULATION_SETTLEMENT' OR f.funding_source_reference IS NULL OR f.funding_source_reference<>('SIM-' || CAST(s.id AS TEXT))
                OR f.source_identity<>('BUYER-REFUND-RR-' || CAST(b.return_refund_request_id AS TEXT)) OR f.verified_by<>s.issued_by
                OR f.consumed_at IS NULL OR f.consumed_at<>b.created_at
                OR w.type<>'REFUND_CREDIT' OR w.status<>'POSTED' OR w.buyer_id<>b.buyer_id OR w.return_refund_request_id IS NULL OR w.return_refund_request_id<>b.return_refund_request_id
                OR w.amount_minor<>b.amount_minor OR w.order_id IS NOT NULL OR w.source_entry_id IS NOT NULL OR w.created_at<>b.created_at
                OR w.source_identity<>('SIM-REFUND-CREDIT-RR-' || CAST(b.return_refund_request_id AS TEXT))
                OR b.source_identity<>('SIM-WALLET-RR-' || CAST(b.return_refund_request_id AS TEXT))
                OR e.simulation_settlement_id<>s.id OR e.actor_id<>b.credited_by OR e.created_at<>b.created_at OR e.operation_key<>('REFUND-CREDIT-' || CAST(b.return_refund_request_id AS TEXT))
            UNION ALL SELECT c.id FROM simulation_commitments c LEFT JOIN simulation_wallet_backings b ON b.simulation_commitment_id=c.id WHERE c.status='WALLET_BACKED' AND b.simulation_commitment_id IS NULL
            UNION ALL SELECT f.return_refund_request_id FROM buyer_refund_fundings f JOIN simulation_refund_scopes x ON x.return_refund_request_id=f.return_refund_request_id LEFT JOIN simulation_wallet_backings b ON b.return_refund_request_id=f.return_refund_request_id WHERE f.status='CREDITED_TO_BUYER_WALLET' AND b.simulation_commitment_id IS NULL
            UNION ALL SELECT w.id FROM buyer_wallet_entries w JOIN simulation_refund_scopes x ON x.return_refund_request_id=w.return_refund_request_id LEFT JOIN simulation_wallet_backings b ON b.buyer_wallet_entry_id=w.id WHERE w.type='REFUND_CREDIT' AND b.simulation_commitment_id IS NULL";
    }

    private function invalid(string $message): never { throw ValidationException::withMessages(['wallet_funding'=>$message]); }
}
