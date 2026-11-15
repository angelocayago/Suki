<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/** Single-Seller payments consume existing immutable refund backings. */
final class AcademicWalletPaymentContract
{
    private static ?\WeakMap $sqliteGuards=null;
    public const TABLES = ['academic_wallet_payments', 'academic_wallet_allocations', 'academic_wallet_reversals', 'academic_wallet_settlements'];
    public const WATCHED = [...self::TABLES, 'buyer_wallet_entries', 'buyer_refund_fundings', 'simulation_wallet_backings',
        'orders', 'seller_orders', 'order_items', 'shipments', 'payments', 'seller_wallet_entries',
        'rider_cod_remittances', 'rider_shipping_fee_earnings', 'voucher_redemptions', 'simulation_settlement_mutex'];

    public function ready(): bool
    {
        if(DB::getDriverName()==='sqlite')self::registerSqliteGuards(DB::connection()->getPdo());
        foreach (self::TABLES as $table) if (!Schema::hasTable($table)) return false;
        if (DB::getDriverName() === 'pgsql') {
            return DB::table('pg_trigger as t')->join('pg_class as c', 'c.oid', '=', 't.tgrelid')
                ->whereRaw("c.relnamespace='public'::regnamespace")->where('t.tgname', 'academic_wallet_validate')
                ->where('t.tgenabled', 'O')->where('t.tgdeferrable', true)->where('t.tginitdeferred', true)->count() === count(self::WATCHED);
        }
        return DB::getDriverName() === 'sqlite' && DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'academic_wallet_validate')->exists();
    }

    /** Keep SQLite triggers independent of rebuildable native table names.
     * Missing registration/schema fails closed; older COD schema-repair fixtures
     * remain usable while no wallet order or allocated payment exists.
     */
    public static function registerSqliteGuards(\PDO $pdo): void
    {
        self::$sqliteGuards??=new \WeakMap();if(isset(self::$sqliteGuards[$pdo]))return;
        $pdo->sqliteCreateFunction('academic_wallet_invalid',static function()use($pdo):int{
            try{
                $payments=(int)$pdo->query('SELECT count(*) FROM academic_wallet_payments')->fetchColumn();
                $walletOrders=(int)$pdo->query("SELECT count(*) FROM orders WHERE payment_method='wallet'")->fetchColumn();
                if($payments===0&&$walletOrders===0){
                    return (int)$pdo->query("SELECT EXISTS (SELECT 1 FROM buyer_wallet_entries w JOIN simulation_wallet_backings b ON b.buyer_id=w.buyer_id WHERE w.type IN ('ORDER_PAYMENT','PAYMENT_REVERSAL'))")->fetchColumn();
                }
                return (int)$pdo->query('SELECT EXISTS ('.self::violations().')')->fetchColumn();
            }catch(\Throwable){return 1;}
        },0);
        $pdo->sqliteCreateFunction('academic_wallet_release_held',static function($id)use($pdo):int{
            try{$s=$pdo->prepare('SELECT EXISTS ('.self::settlementHold('?').')');$s->execute([(int)$id]);return(int)$s->fetchColumn();}catch(\Throwable){return 1;}
        },1);
        self::$sqliteGuards[$pdo]=true;
    }

    public function verify(): void
    {
        if (!$this->ready() || DB::selectOne('SELECT EXISTS ('.self::violations().') AS invalid')->invalid) {
            throw ValidationException::withMessages(['wallet' => 'Wallet payment backing, ownership or settlement does not reconcile.']);
        }
    }

    public static function settlementHold(string $paymentId): string
    {
        return "SELECT p.id FROM academic_wallet_payments p WHERE p.id=$paymentId AND (
            EXISTS (SELECT 1 FROM complaints c WHERE c.seller_order_id=p.seller_order_id AND c.status IN ('OPEN','UNDER_REVIEW','WAITING_RESPONSE'))
            OR EXISTS (SELECT 1 FROM return_refund_requests r WHERE r.seller_order_id=p.seller_order_id AND r.status NOT IN ('ADMIN_REJECTED','REJECTED','CANCELLED'))
        )";
    }

    /** Same cross-row predicate for PG deferred enforcement and SQLite close checks. */
    public static function violations(): string
    {
        return "SELECT p.id FROM academic_wallet_payments p
            LEFT JOIN orders o ON o.id=p.order_id LEFT JOIN seller_orders so ON so.id=p.seller_order_id
            LEFT JOIN buyer_wallet_entries w ON w.id=p.payment_entry_id
            LEFT JOIN payments n ON n.id=p.native_payment_id
            LEFT JOIN seller_wallet_entries e ON e.id=p.earning_entry_id
            LEFT JOIN academic_wallet_reversals r ON r.payment_id=p.id
            WHERE o.id IS NULL OR so.id IS NULL OR w.id IS NULL OR n.id IS NULL OR e.id IS NULL
                OR o.buyer_id IS NULL OR o.buyer_id<>p.buyer_id OR so.order_id<>o.id OR COALESCE(o.payment_method,'')<>'wallet'
                OR o.total_minor IS NULL OR o.total_minor<>p.total_minor OR p.total_minor<>p.seller_minor+p.platform_minor+p.shipping_minor
                OR (SELECT COUNT(*) FROM seller_orders x WHERE x.order_id=o.id)<>1
                OR EXISTS (SELECT 1 FROM simulation_settlements x WHERE x.seller_id=so.seller_id)
                OR p.total_minor<>(SELECT COALESCE(SUM(i.unit_price_minor*i.quantity-i.seller_funded_discount_minor-i.platform_funded_discount_minor),0) FROM order_items i WHERE i.seller_order_id=so.id)+so.shipping_fee_minor
                OR so.subtotal_minor<>(SELECT COALESCE(SUM(i.unit_price_minor*i.quantity),0) FROM order_items i WHERE i.seller_order_id=so.id)
                OR so.commission_minor<>(SELECT COALESCE(SUM(i.commission_minor),0) FROM order_items i WHERE i.seller_order_id=so.id)
                OR (SELECT COALESCE(SUM(v.discount_minor),0) FROM voucher_redemptions v WHERE v.seller_order_id=so.id AND v.scope_snapshot='SELLER')<>(SELECT COALESCE(SUM(i.seller_funded_discount_minor),0) FROM order_items i WHERE i.seller_order_id=so.id)
                OR (SELECT COALESCE(SUM(v.discount_minor),0) FROM voucher_redemptions v WHERE v.seller_order_id=so.id AND v.scope_snapshot='PLATFORM')<>(SELECT COALESCE(SUM(i.platform_funded_discount_minor),0) FROM order_items i WHERE i.seller_order_id=so.id)
                OR p.seller_minor<>so.subtotal_minor-so.commission_minor-(SELECT COALESCE(SUM(i.seller_funded_discount_minor),0) FROM order_items i WHERE i.seller_order_id=so.id)
                OR p.shipping_minor<>so.shipping_fee_minor OR p.shipping_minor<>5000
                OR NOT EXISTS (SELECT 1 FROM order_items i WHERE i.seller_order_id=so.id)
                OR EXISTS (SELECT 1 FROM order_items i JOIN sellers s ON s.id=so.seller_id WHERE i.seller_order_id=so.id AND (i.order_id<>o.id OR i.seller_id<>s.user_id OR i.quantity<1 OR i.unit_price_minor<0 OR i.commission_minor<0 OR i.seller_funded_discount_minor<0 OR i.platform_funded_discount_minor<0))
                OR p.total_minor<>(SELECT COALESCE(SUM(a.amount_minor),0) FROM academic_wallet_allocations a WHERE a.payment_id=p.id)
                OR w.type<>'ORDER_PAYMENT' OR w.status<>'POSTED' OR w.buyer_id<>p.buyer_id OR w.order_id IS NULL OR w.order_id<>o.id
                OR w.amount_minor<>-p.total_minor OR w.return_refund_request_id IS NOT NULL OR w.source_entry_id IS NOT NULL
                OR w.source_identity<>('WALLET-PAY-ORDER-' || CAST(o.id AS TEXT))
                OR n.order_id<>o.id OR n.method<>'wallet' OR n.amount_minor<>p.total_minor
                OR COALESCE(n.provider_ref,'')<>('WALLET-PAY-ORDER-' || CAST(o.id AS TEXT))
                OR (SELECT COUNT(*) FROM payments x WHERE x.order_id=o.id)<>1
                OR e.type<>'SALE_EARNING' OR e.seller_id<>so.seller_id OR e.seller_order_id IS NULL OR e.seller_order_id<>so.id OR e.amount_minor<>p.seller_minor
                OR e.return_refund_request_id IS NOT NULL OR e.source_wallet_entry_id IS NOT NULL OR e.payout_id IS NOT NULL
                OR (r.payment_id IS NULL AND (COALESCE(o.payment_status,'')<>'PAID' OR n.status<>'PAID' OR e.status<>'HELD' OR o.status='CANCELLED'))
                OR (r.payment_id IS NOT NULL AND (COALESCE(o.payment_status,'')<>'REFUNDED' OR n.status<>'REFUNDED' OR e.status<>'VOID' OR o.status<>'CANCELLED' OR o.cancelled_at IS NULL))
                OR NOT EXISTS (SELECT 1 FROM shipments sh WHERE sh.seller_order_id=so.id)
                OR EXISTS (SELECT 1 FROM shipments sh WHERE sh.seller_order_id=so.id AND (COALESCE(sh.cod_amount_minor,-1)<>0 OR sh.cod_collected OR sh.cod_collected_minor IS NOT NULL OR sh.cod_collected_at IS NOT NULL))
                OR EXISTS (SELECT 1 FROM rider_cod_remittances cr WHERE cr.seller_order_id=so.id)
                OR (SELECT COUNT(*) FROM rider_shipping_fee_earnings f WHERE f.seller_order_id=so.id)>1
                OR EXISTS (SELECT 1 FROM rider_shipping_fee_earnings f WHERE f.seller_order_id=so.id AND (f.amount_minor<>p.shipping_minor OR NOT EXISTS (SELECT 1 FROM shipments sh JOIN riders rd ON rd.user_id=sh.delivered_by WHERE sh.id=f.shipment_id AND sh.seller_order_id=so.id AND rd.id=f.rider_id AND rd.id=sh.rider_id AND rd.logistics_provider_id=sh.logistics_provider_id)))
            UNION ALL SELECT a.payment_id FROM academic_wallet_allocations a
                LEFT JOIN academic_wallet_payments p ON p.id=a.payment_id
                LEFT JOIN simulation_wallet_backings b ON b.simulation_commitment_id=a.simulation_commitment_id
                LEFT JOIN buyer_wallet_entries w ON w.id=b.buyer_wallet_entry_id
                LEFT JOIN buyer_refund_fundings f ON f.return_refund_request_id=b.return_refund_request_id
                WHERE p.id IS NULL OR b.simulation_commitment_id IS NULL OR w.id IS NULL OR f.return_refund_request_id IS NULL
                    OR a.amount_minor<=0 OR b.buyer_id<>p.buyer_id OR w.buyer_id<>p.buyer_id OR w.type<>'REFUND_CREDIT' OR w.status<>'POSTED'
                    OR b.amount_minor<>w.amount_minor OR f.status<>'CREDITED_TO_BUYER_WALLET' OR f.buyer_id<>p.buyer_id OR f.funded_minor<>b.amount_minor
            UNION ALL SELECT b.simulation_commitment_id FROM simulation_wallet_backings b
                WHERE (SELECT COALESCE(SUM(a.amount_minor),0) FROM academic_wallet_allocations a LEFT JOIN academic_wallet_reversals r ON r.payment_id=a.payment_id WHERE a.simulation_commitment_id=b.simulation_commitment_id AND r.payment_id IS NULL)>b.amount_minor
            UNION ALL SELECT r.payment_id FROM academic_wallet_reversals r
                LEFT JOIN academic_wallet_payments p ON p.id=r.payment_id LEFT JOIN buyer_wallet_entries w ON w.id=r.wallet_entry_id
                LEFT JOIN orders o ON o.id=p.order_id LEFT JOIN seller_orders so ON so.id=p.seller_order_id
                WHERE p.id IS NULL OR w.id IS NULL OR o.id IS NULL OR r.cancelled_by<>p.buyer_id
                    OR w.buyer_id<>p.buyer_id OR w.type<>'PAYMENT_REVERSAL' OR w.status<>'POSTED' OR w.amount_minor<>p.total_minor
                    OR w.order_id IS NULL OR w.order_id<>p.order_id OR w.source_entry_id IS NULL OR w.source_entry_id<>p.payment_entry_id OR w.return_refund_request_id IS NOT NULL
                    OR w.source_identity<>('WALLET-REVERSE-ORDER-' || CAST(p.order_id AS TEXT))
                    OR o.status<>'CANCELLED' OR so.status<>'cancelled' OR so.workflow_status<>'PLACED'
                    OR EXISTS (SELECT 1 FROM academic_wallet_settlements s WHERE s.payment_id=p.id)
                    OR EXISTS (SELECT 1 FROM shipments sh WHERE sh.seller_order_id=so.id AND (sh.status<>'unassigned' OR sh.workflow_status<>'PLACED' OR sh.rider_id IS NOT NULL OR sh.pickup_rider_id IS NOT NULL OR sh.proof_path IS NOT NULL))
            UNION ALL SELECT s.payment_id FROM academic_wallet_settlements s
                LEFT JOIN academic_wallet_payments p ON p.id=s.payment_id LEFT JOIN orders o ON o.id=p.order_id
                LEFT JOIN seller_orders so ON so.id=p.seller_order_id LEFT JOIN shipments sh ON sh.id=s.shipment_id
                LEFT JOIN seller_wallet_entries e ON e.id=s.release_entry_id
                WHERE p.id IS NULL OR o.id IS NULL OR so.id IS NULL OR sh.id IS NULL OR e.id IS NULL
                    OR o.status<>'COMPLETED' OR so.workflow_status<>'COMPLETED' OR so.status<>'completed' OR so.completed_at IS NULL OR o.completed_at IS NULL
                    OR sh.seller_order_id<>so.id OR sh.workflow_status<>'COMPLETED' OR sh.status<>'delivered'
                    OR sh.proof_path IS NULL OR sh.proof_delivered_at IS NULL OR sh.delivered_by IS NULL OR sh.proof_path<>s.proof_path OR sh.delivered_by<>s.delivered_by
                    OR NOT EXISTS (SELECT 1 FROM delivery_events d WHERE d.shipment_id=sh.id AND d.status='DELIVERED' AND d.user_id=sh.delivered_by)
                    OR NOT EXISTS (SELECT 1 FROM rider_shipping_fee_earnings f WHERE f.seller_order_id=so.id AND f.shipment_id=sh.id AND f.amount_minor=p.shipping_minor)
                    OR EXISTS (SELECT 1 FROM academic_wallet_reversals r WHERE r.payment_id=p.id)
                    OR e.type<>'EARNING_RELEASE' OR e.status<>'POSTED' OR e.amount_minor<>p.seller_minor OR e.seller_id<>so.seller_id OR e.seller_order_id IS NULL OR e.seller_order_id<>so.id OR e.source_wallet_entry_id IS NULL OR e.source_wallet_entry_id<>p.earning_entry_id
            UNION ALL SELECT w.id FROM buyer_wallet_entries w
                WHERE EXISTS (SELECT 1 FROM simulation_wallet_backings b WHERE b.buyer_id=w.buyer_id)
                    AND ((w.type='ORDER_PAYMENT' AND NOT EXISTS (SELECT 1 FROM academic_wallet_payments p WHERE p.payment_entry_id=w.id))
                      OR (w.type='PAYMENT_REVERSAL' AND NOT EXISTS (SELECT 1 FROM academic_wallet_reversals r WHERE r.wallet_entry_id=w.id)))
            UNION ALL SELECT e.id FROM seller_wallet_entries e JOIN academic_wallet_payments p ON p.seller_order_id=e.seller_order_id
                WHERE e.type='EARNING_RELEASE' AND NOT EXISTS (SELECT 1 FROM academic_wallet_settlements s WHERE s.release_entry_id=e.id)
            UNION ALL SELECT o.id FROM orders o WHERE o.payment_method='wallet' AND NOT EXISTS (SELECT 1 FROM academic_wallet_payments p WHERE p.order_id=o.id)
            UNION ALL SELECT id FROM simulation_settlement_mutex WHERE operation_open<>0";
    }
}
