<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Payout;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerWalletEntry;
use App\Models\ReturnRefundRequest;
use App\Models\User;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SellerWalletService
{
    private const ACTIVE_COMPLAINT_STATUSES = ['OPEN', 'UNDER_REVIEW', 'WAITING_RESPONSE'];

    public function creditCompleted(SellerOrder $sellerOrder): void
    {
        DB::transaction(function () use ($sellerOrder): void {
            $order = SellerOrder::query()->with('items')->lockForUpdate()->findOrFail($sellerOrder->id);
            if ($this->status($order) !== OrderStatus::Completed->value || $order->status === 'cancelled') return;
            $sellerDiscount = (int) VoucherRedemption::query()->where('seller_order_id', $order->id)->where('scope_snapshot', 'SELLER')->sum('discount_minor');
            $earning = (int) $order->items->sum(fn ($item): int => ((int) $item->unit_price_minor * (int) $item->quantity) - (int) $item->commission_minor) - $sellerDiscount;
            // Legacy completed SellerOrders without an authoritative collected-COD
            // shipment retain their historical wallet behavior. A real COD custody
            // hold applies only once a shipment has recorded COD collection.
            $requiresCodReconciliation = (bool) ($order->shipment?->cod_collected);
            $reconciled = ! $requiresCodReconciliation || \App\Models\LogisticsCodRemittanceItem::query()->whereHas('riderRemittance', fn($q)=>$q->where('seller_order_id',$order->id)->where('shipment_id',$order->shipment->id)->where('status','ACCEPTED'))->whereHas('logisticsRemittance', fn($q)=>$q->where('status','ACCEPTED'))->exists();
            if ($requiresCodReconciliation && $reconciled) {
                $order->loadMissing(['seller','order','shipment']);
                try { $reconciled = app(ReturnRefundService::class)->verifiedCompletedEarning($order) === max(0,$earning); }
                catch (ValidationException $exception) { $reconciled = false; }
            }
            $heldForComplaint=$this->hasActiveComplaint($order->id); $heldForReturn=$this->hasActiveReturn($order->id);
            $holdDescription=$heldForComplaint&&$heldForReturn?'Held due to an active complaint and return/refund request':($heldForComplaint?'Held due to an active complaint':($heldForReturn?'Held due to an active return/refund request':(!$reconciled?'Held pending COD reconciliation':'Completed seller order earning')));
            SellerWalletEntry::firstOrCreate(['seller_id'=>$order->seller_id,'seller_order_id'=>$order->id,'type'=>'SALE_EARNING'], ['status'=>($heldForComplaint || $heldForReturn || ! $reconciled) ? 'HELD' : 'AVAILABLE','amount_minor'=>max(0, $earning),'reference'=>'SO-'.$order->id,'description'=>$holdDescription]);
        }, 3);
    }

    /** Append-only release. Callers must acquire Seller before any order locks. */
    public function reconcileSellerOrder(SellerOrder $sellerOrder): bool
    {
        return $this->reconcileSellerOrderResult($sellerOrder)['status'] === 'RELEASED';
    }

    public function releaseReadiness(): array
    {
        $schema = app(FinancialSchemaReadiness::class)->inspect();
        if (!$schema['ready']) return ['status'=>'SCHEMA_UNAVAILABLE','message'=>'Earning releases require financial schema review and migration. Retained held earnings must be reviewed and retried after deployment.','issues'=>$schema['issues']];
        if (config('returns.earning_release_enabled',false) !== true) return ['status'=>'ACTIVATION_DISABLED','message'=>'Earning releases are paused pending separate rollout approval. Held earnings are retained for reviewed reconciliation.','issues'=>[]];
        return ['status'=>'READY','message'=>'Earning reconciliation is enabled; each purchase still requires verified settlement and cleared holds.','issues'=>[]];
    }

    public function reconcileSellerOrderResult(SellerOrder $sellerOrder): array
    {
        $readiness = $this->releaseReadiness();
        if ($readiness['status'] !== 'READY') {
            Log::notice('Earning reconciliation deferred', ['seller_order_id'=>$sellerOrder->id,'status'=>$readiness['status'],'issues'=>$readiness['issues']]);
            return $readiness;
        }
        $released = $this->releaseCompletedEarning($sellerOrder);
        return ['status'=>$released?'RELEASED':'NO_RELEASE','message'=>$released?'Verified earning released.':'No release created: already reconciled, no held earning, or purchase/settlement/hold evidence is not eligible.','issues'=>[]];
    }

    private function releaseCompletedEarning(SellerOrder $sellerOrder): bool
    {
        return DB::transaction(function () use ($sellerOrder): bool {
            $reference = SellerOrder::findOrFail($sellerOrder->id);
            $seller = Seller::whereKey($reference->seller_id)->lockForUpdate()->firstOrFail();
            $parent = \App\Models\Order::whereKey($reference->order_id)->lockForUpdate()->firstOrFail();
            $order = SellerOrder::whereKey($reference->id)->lockForUpdate()->firstOrFail();
            if ((int)$order->seller_id !== (int)$seller->id || (int)$order->order_id !== (int)$parent->id) return false;
            $shipment = \App\Models\Shipment::where('seller_order_id',$order->id)->lockForUpdate()->first();
            $items = $order->items()->orderBy('id')->lockForUpdate()->get();
            $order->setRelation('seller',$seller)->setRelation('order',$parent)->setRelation('shipment',$shipment)->setRelation('items',$items);
            if ($this->hasActiveComplaint($order->id) || $this->hasActiveReturn($order->id)) return false;
            $sources = SellerWalletEntry::where('seller_order_id',$order->id)->where('type','SALE_EARNING')->orderBy('id')->lockForUpdate()->get();
            if ($sources->count() !== 1) return false;
            $entry = $sources->first();
            if ((int)$entry->seller_id !== (int)$seller->id || $entry->status !== 'HELD' || $entry->amount_minor < 1
                || $entry->source_wallet_entry_id || $entry->return_refund_request_id || $entry->payout_id) return false;
            if (SellerWalletEntry::where('source_wallet_entry_id',$entry->id)->exists()
                || SellerWalletEntry::where('seller_order_id',$order->id)->where('type','EARNING_RELEASE')->exists()) return false;
            try { $expected = app(ReturnRefundService::class)->verifiedCompletedEarning($order); }
            catch (ValidationException $exception) { return false; }
            if ($expected !== (int)$entry->amount_minor) return false;
            // Collection is not upstream settlement. The shared purchase proof
            // validates amounts/ownership; a valid accepted upstream row is required.
            if (!\App\Models\LogisticsCodRemittanceItem::whereHas('riderRemittance',fn($q)=>$q->where('seller_order_id',$order->id)->where('shipment_id',$shipment->id)->where('status','ACCEPTED'))
                ->whereHas('logisticsRemittance',fn($q)=>$q->where('status','ACCEPTED'))->exists()) return false;
            $adjustments = SellerWalletEntry::where('seller_order_id',$order->id)->where('type','REFUND_ADJUSTMENT')->orderBy('id')->lockForUpdate()->get();
            $debit = 0;
            foreach ($adjustments as $adjustment) {
                $requestId = $adjustment->return_refund_request_id;
                if (!$requestId && preg_match('/\ARR-([1-9][0-9]*)\z/',(string)$adjustment->reference,$match)) $requestId = (int)$match[1];
                $request = $requestId ? ReturnRefundRequest::financial()->find($requestId) : null;
                $accounting = $request ? DB::table('return_refund_accounting')->where('return_refund_request_id',$request->id)->first() : null;
                if (!$request || !$accounting || $request->status !== 'ADMIN_APPROVED' || (int)$request->seller_order_id !== (int)$order->id
                    || (int)$request->seller_id !== (int)$seller->id || (int)$adjustment->seller_id !== (int)$seller->id
                    || $adjustment->status !== 'AVAILABLE' || $adjustment->amount_minor > 0
                    || -$adjustment->amount_minor !== (int)$accounting->seller_adjustment_minor || -$adjustment->amount_minor > $expected-$debit) return false;
                $item = $items->firstWhere('id',$request->order_item_id);
                if (!$item || (int)$request->order_id !== (int)$parent->id || (int)$request->buyer_id !== (int)$parent->buyer_id
                    || (int)$request->quantity !== (int)$item->quantity || $adjustment->source_wallet_entry_id || $adjustment->payout_id
                    || $accounting->refund_status !== 'REFUND_DUE') return false;
                try { $allocation = app(ReturnRefundAllocationService::class)->calculate($item,(int)$item->quantity); }
                catch (ValidationException $exception) { return false; }
                foreach (['merchandise_minor'=>'gross_merchandise_minor','seller_adjustment_minor'=>'seller_adjustment_minor',
                    'commission_reversal_minor'=>'commission_reversed_minor','seller_voucher_minor'=>'seller_funded_discount_minor',
                    'platform_voucher_minor'=>'platform_funded_discount_minor'] as $field=>$snapshot) {
                    if ((int)$accounting->$field !== $allocation[$snapshot]) return false;
                }
                if ((int)$request->refund_minor !== $allocation['buyer_refund_due_minor']) return false;
                $debit -= $adjustment->amount_minor;
            }
            // An approved obligation must also have its own corresponding debit;
            // missing legacy accounting is never reconstructed or silently ignored.
            $approved = ReturnRefundRequest::financial()->where('seller_order_id',$order->id)->where('status','ADMIN_APPROVED')->pluck('id');
            foreach ($approved as $id) if (!$adjustments->contains(fn($row)=>(int)$row->return_refund_request_id===(int)$id || $row->reference==='RR-'.$id)) return false;
            SellerWalletEntry::create(['seller_id'=>$seller->id,'seller_order_id'=>$order->id,'source_wallet_entry_id'=>$entry->id,
                'type'=>'EARNING_RELEASE','status'=>'POSTED','amount_minor'=>$entry->amount_minor,'reference'=>'ER-'.$entry->id,
                'description'=>'Original completed earning released; refund adjustments remain separate']);
            return true;
        }, 3);
    }

    /** Fixed aliases only; never interpolate request input into SQL. */
    private function validReleaseSql(string $alias): string
    {
        return "$alias.type = 'EARNING_RELEASE' AND $alias.status = 'POSTED' AND $alias.return_refund_request_id IS NULL AND $alias.amount_minor > 0 AND EXISTS (SELECT 1 FROM seller_wallet_entries wallet_source WHERE wallet_source.id = $alias.source_wallet_entry_id AND wallet_source.type = 'SALE_EARNING' AND wallet_source.status = 'HELD' AND wallet_source.seller_id = $alias.seller_id AND wallet_source.seller_order_id = $alias.seller_order_id AND wallet_source.amount_minor = $alias.amount_minor AND wallet_source.source_wallet_entry_id IS NULL AND wallet_source.return_refund_request_id IS NULL AND wallet_source.payout_id IS NULL)";
    }

    public function validReleaseEntries(Seller $seller)
    {
        $query = SellerWalletEntry::where('seller_id',$seller->id);
        return app(FinancialSchemaReadiness::class)->inspect()['ready']
            ? $query->whereRaw($this->validReleaseSql('seller_wallet_entries')) : $query->whereRaw('1 = 0');
    }

    /** Read-only ledger summary. */
    public function summary(Seller $seller): array
    {
        return array_intersect_key($this->projection($seller->id),array_flip(['available_minor','held_minor','reserved_minor','paid_out_minor','total_earnings_minor']));
    }

    /** Current balances, independent of report activity-date/remittance filters. */
    public function reportSummary(?int $sellerId = null): array
    {
        return $this->projection($sellerId);
    }

    private function projection(?int $sellerId): array
    {
        // A disbursement has one identity: its owned, PAID payout. Match the
        // positive POSTED ledger entry to its amount; duplicate rows must not
        // deduct the same payout twice. No historical records are rewritten.
        $completed = DB::table('seller_wallet_entries as completed')
            ->when($sellerId !== null,fn($q)=>$q->where('completed.seller_id',$sellerId))
            ->where('completed.type', 'PAYOUT_COMPLETED')->where('completed.status', 'POSTED')
            ->where('completed.amount_minor', '>', 0)
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('payouts')
                    ->whereColumn('payouts.id', 'completed.payout_id')
                    ->whereColumn('payouts.seller_id', 'completed.seller_id')
                    ->whereColumn('payouts.amount_minor', 'completed.amount_minor')
                    ->where('payouts.status', 'PAID');
            })->groupBy('completed.payout_id')->selectRaw('MAX(completed.amount_minor) AS amount_minor');
        $paid = DB::query()->fromSub($completed, 'completed_payouts')->selectRaw('COALESCE(SUM(amount_minor), 0)');

        // Standard CASE aggregates plus the scalar subquery use one statement
        // snapshot on PostgreSQL and SQLite, without a join multiplying entries.
        $hasReleaseSchema = app(FinancialSchemaReadiness::class)->inspect()['ready'];
        $release = $hasReleaseSchema ? $this->validReleaseSql('seller_wallet_entries') : '1 = 0';
        $unreleased = $hasReleaseSchema ? 'NOT EXISTS (SELECT 1 FROM seller_wallet_entries wallet_release WHERE wallet_release.source_wallet_entry_id = seller_wallet_entries.id AND '.$this->validReleaseSql('wallet_release').')' : '1 = 1';
        $totals = DB::table('seller_wallet_entries')->when($sellerId !== null,fn($q)=>$q->where('seller_id',$sellerId))
            ->selectRaw("COALESCE(SUM(CASE WHEN (type IN ('SALE_EARNING', 'REFUND_ADJUSTMENT') AND status = 'AVAILABLE') OR ($release) THEN amount_minor ELSE 0 END), 0) AS available_credits_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'PAYOUT_RESERVATION' AND status = 'RESERVED' THEN amount_minor ELSE 0 END), 0) AS reserved_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'SALE_EARNING' AND status = 'HELD' AND ($unreleased) THEN amount_minor ELSE 0 END), 0) AS held_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'PAYOUT_COMPLETED' THEN amount_minor ELSE 0 END), 0) AS paid_out_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'SALE_EARNING' THEN amount_minor ELSE 0 END), 0) AS total_earnings_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'REFUND_ADJUSTMENT' AND status = 'AVAILABLE' THEN amount_minor ELSE 0 END), 0) AS refund_adjustments_minor")
            ->selectSub($paid, 'completed_minor')->first();

        return [
            'available_minor'=>(int) $totals->available_credits_minor - (int) $totals->reserved_minor - (int) $totals->completed_minor,
            'held_minor'=>(int) $totals->held_minor,
            'reserved_minor'=>(int) $totals->reserved_minor,
            'paid_out_minor'=>(int) $totals->paid_out_minor,
            'total_earnings_minor'=>(int) $totals->total_earnings_minor,
            'verified_paid_out_minor'=>(int) $totals->completed_minor,
            'refund_adjustments_minor'=>(int) $totals->refund_adjustments_minor,
        ];
    }

    public function request(Seller $seller, int $amountMinor): Payout
    {
        return DB::transaction(function () use ($seller, $amountMinor): Payout {
            if ($amountMinor < 1) throw ValidationException::withMessages(['amount' => 'Enter a positive payout amount.']);
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $this->assertNoActiveRefundRisk($seller->id);
            if ($amountMinor > $this->summary($seller)['available_minor']) throw ValidationException::withMessages(['amount' => 'Amount exceeds available balance.']);
            $payout = Payout::create(['seller_id'=>$seller->id,'amount_minor'=>$amountMinor,'status'=>'PENDING']);
            SellerWalletEntry::create(['seller_id'=>$seller->id,'payout_id'=>$payout->id,'type'=>'PAYOUT_RESERVATION','status'=>'RESERVED','amount_minor'=>$amountMinor,'reference'=>'PO-'.$payout->id,'description'=>'Payout request reservation']);
            return $payout;
        }, 3);
    }

    public function review(Payout $payout, User $admin, string $status, ?string $reason = null): Payout
    {
        return DB::transaction(function () use ($payout, $admin, $status, $reason): Payout {
            if (! $admin->isAdmin() || $admin->status !== 'active' || $admin->is_suspended) {
                throw ValidationException::withMessages(['payout' => 'Only an active admin may review payouts.']);
            }
            $sellerId = Payout::findOrFail($payout->id)->seller_id;
            Seller::whereKey($sellerId)->lockForUpdate()->firstOrFail();
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($status === 'APPROVED') $this->assertNoActiveRefundRisk($sellerId);
            if ($payout->status !== 'PENDING') throw ValidationException::withMessages(['payout' => 'Only pending payouts can be reviewed.']);
            if ($status === 'REJECTED' && ! filled($reason)) throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
            $payout->update(['status'=>$status,'reason'=>$status==='REJECTED' ? trim((string)$reason) : null,'reviewed_by'=>$admin->id,'reviewed_at'=>now()]);
            SellerWalletEntry::query()->where('payout_id',$payout->id)->where('type','PAYOUT_RESERVATION')->where('status','RESERVED')->update(['status'=>$status==='REJECTED' ? 'RELEASED' : 'RESERVED']);
            return $payout->fresh();
        }, 3);
    }

    public function markPaid(Payout $payout, User $admin, string $method, ?string $reference): Payout
    {
        return DB::transaction(function () use ($payout, $admin, $method, $reference): Payout {
            if (! $admin->isAdmin() || $admin->status !== 'active' || $admin->is_suspended) {
                throw ValidationException::withMessages(['payout' => 'Only an active admin may record payout completion.']);
            }
            $sellerId = Payout::findOrFail($payout->id)->seller_id;
            $seller = Seller::whereKey($sellerId)->lockForUpdate()->firstOrFail();
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status !== 'APPROVED') throw ValidationException::withMessages(['payout' => 'Only approved payouts can be paid.']);
            $this->assertNoActiveRefundRisk($sellerId);
            $reservation = SellerWalletEntry::where('seller_id',$sellerId)->where('payout_id',$payout->id)
                ->where('type','PAYOUT_RESERVATION')->where('status','RESERVED')->lockForUpdate()->get();
            if ($reservation->count() !== 1 || (int)$reservation->first()->amount_minor !== (int)$payout->amount_minor
                || $this->summary($seller)['available_minor'] + (int)$payout->amount_minor < (int)$payout->amount_minor) {
                throw ValidationException::withMessages(['payout'=>'Payout funding changed or its reservation is inconsistent; financial review is required.']);
            }
            $payout->update(['status'=>'PAID','method'=>$method,'reference'=>filled($reference) ? trim($reference) : null,'reviewed_by'=>$admin->id,'paid_at'=>now()]);
            SellerWalletEntry::query()->where('payout_id',$payout->id)->where('type','PAYOUT_RESERVATION')->where('status','RESERVED')->update(['status'=>'SETTLED']);
            SellerWalletEntry::firstOrCreate(['seller_id'=>$payout->seller_id,'payout_id'=>$payout->id,'type'=>'PAYOUT_COMPLETED'], ['status'=>'POSTED','amount_minor'=>$payout->amount_minor,'reference'=>$payout->reference,'description'=>'Manual payout completed']);
            return $payout->fresh();
        }, 3);
    }

    private function hasActiveComplaint(int $sellerOrderId): bool
    {
        return Complaint::query()->where('seller_order_id', $sellerOrderId)->whereIn('status', self::ACTIVE_COMPLAINT_STATUSES)->exists();
    }

    private function hasActiveReturn(int $sellerOrderId): bool
    {
        return ReturnRefundRequest::financial()->where('seller_order_id', $sellerOrderId)->whereIn('status', ReturnRefundRequest::ACTIVE_STATUSES)->exists();
    }

    /** Conservative seller-wide payout pause, including pre-existing requests.
     * Recorded balances/reservations are not rewritten. Rejection remains possible.
     */
    private function assertNoActiveRefundRisk(int $sellerId): void
    {
        if ($message = $this->payoutRestriction($sellerId)) throw ValidationException::withMessages(['payout'=>$message]);
    }

    public function payoutRestriction(int $sellerId): ?string
    {
        if (ReturnRefundRequest::financial()->where('seller_id',$sellerId)->whereIn('status',ReturnRefundRequest::ACTIVE_STATUSES)->exists()) {
            return 'Payouts are paused while an active return/refund request requires financial review.';
        }
        if (Complaint::whereIn('status',self::ACTIVE_COMPLAINT_STATUSES)->whereHas('sellerOrder',fn($q)=>$q->where('seller_id',$sellerId))->exists()) {
            return 'Payouts are paused while a linked financial complaint remains unresolved.';
        }
        return null;
    }

    private function status(SellerOrder $order): string
    {
        return $order->workflow_status instanceof OrderStatus ? $order->workflow_status->value : (string) $order->workflow_status;
    }
}
