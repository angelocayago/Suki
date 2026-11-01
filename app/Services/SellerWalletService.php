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
            $reconciled = ! $requiresCodReconciliation || \App\Models\LogisticsCodRemittanceItem::query()->whereHas('riderRemittance', fn($q)=>$q->where('seller_order_id',$order->id))->whereHas('logisticsRemittance', fn($q)=>$q->where('status','ACCEPTED'))->exists();
            $heldForComplaint=$this->hasActiveComplaint($order->id); $heldForReturn=$this->hasActiveReturn($order->id);
            $holdDescription=$heldForComplaint&&$heldForReturn?'Held due to an active complaint and return/refund request':($heldForComplaint?'Held due to an active complaint':($heldForReturn?'Held due to an active return/refund request':(!$reconciled?'Held pending COD reconciliation':'Completed seller order earning')));
            SellerWalletEntry::firstOrCreate(['seller_id'=>$order->seller_id,'seller_order_id'=>$order->id,'type'=>'SALE_EARNING'], ['status'=>($heldForComplaint || $heldForReturn || ! $reconciled) ? 'HELD' : 'AVAILABLE','amount_minor'=>max(0, $earning),'reference'=>'SO-'.$order->id,'description'=>$holdDescription]);
        }, 3);
    }

    /** Explicit, idempotent reconciliation after a complaint lifecycle action; never invoked by wallet reads. */
    public function reconcileSellerOrder(SellerOrder $sellerOrder): bool
    {
        return DB::transaction(function () use ($sellerOrder): bool {
            $order = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);
            $entry = SellerWalletEntry::query()->where('seller_id', $order->seller_id)->where('seller_order_id', $order->id)->where('type', 'SALE_EARNING')->lockForUpdate()->first();
            if (! $entry || $entry->status !== 'HELD' || $this->hasActiveComplaint($order->id) || $this->hasActiveReturn($order->id)) return false;
            $entry->update(['status' => 'AVAILABLE','description'=>'Completed seller order earning']);
            return true;
        }, 3);
    }

    /** Read-only ledger summary. */
    public function summary(Seller $seller): array
    {
        // A disbursement has one identity: its owned, PAID payout. Match the
        // positive POSTED ledger entry to its amount; duplicate rows must not
        // deduct the same payout twice. No historical records are rewritten.
        $completed = DB::table('seller_wallet_entries as completed')
            ->where('completed.seller_id', $seller->id)
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
        $totals = DB::table('seller_wallet_entries')->where('seller_id', $seller->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('SALE_EARNING', 'REFUND_ADJUSTMENT') AND status = 'AVAILABLE' THEN amount_minor ELSE 0 END), 0) AS available_credits_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'PAYOUT_RESERVATION' AND status = 'RESERVED' THEN amount_minor ELSE 0 END), 0) AS reserved_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'SALE_EARNING' AND status = 'HELD' THEN amount_minor ELSE 0 END), 0) AS held_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'PAYOUT_COMPLETED' THEN amount_minor ELSE 0 END), 0) AS paid_out_minor")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'SALE_EARNING' THEN amount_minor ELSE 0 END), 0) AS total_earnings_minor")
            ->selectSub($paid, 'completed_minor')->first();

        return [
            'available_minor'=>(int) $totals->available_credits_minor - (int) $totals->reserved_minor - (int) $totals->completed_minor,
            'held_minor'=>(int) $totals->held_minor,
            'reserved_minor'=>(int) $totals->reserved_minor,
            'paid_out_minor'=>(int) $totals->paid_out_minor,
            'total_earnings_minor'=>(int) $totals->total_earnings_minor,
        ];
    }

    public function request(Seller $seller, int $amountMinor): Payout
    {
        return DB::transaction(function () use ($seller, $amountMinor): Payout {
            if ($amountMinor < 1) throw ValidationException::withMessages(['amount' => 'Enter a positive payout amount.']);
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
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
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
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
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status !== 'APPROVED') throw ValidationException::withMessages(['payout' => 'Only approved payouts can be paid.']);
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
        return ReturnRefundRequest::query()->where('seller_order_id', $sellerOrderId)->whereIn('status', ['SUBMITTED', 'SELLER_RESPONDED'])->exists();
    }

    private function status(SellerOrder $order): string
    {
        return $order->workflow_status instanceof OrderStatus ? $order->workflow_status->value : (string) $order->workflow_status;
    }
}
