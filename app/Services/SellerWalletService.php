<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Payout;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerWalletEntry;
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
            SellerWalletEntry::firstOrCreate(['seller_id'=>$order->seller_id,'seller_order_id'=>$order->id,'type'=>'SALE_EARNING'], ['status'=>$this->hasActiveComplaint($order->id) ? 'HELD' : 'AVAILABLE','amount_minor'=>max(0, $earning),'reference'=>'SO-'.$order->id,'description'=>'Completed seller order earning']);
        }, 3);
    }

    /** Explicit, idempotent reconciliation after a complaint lifecycle action; never invoked by wallet reads. */
    public function reconcileSellerOrder(SellerOrder $sellerOrder): void
    {
        DB::transaction(function () use ($sellerOrder): void {
            $order = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);
            $entry = SellerWalletEntry::query()->where('seller_id', $order->seller_id)->where('seller_order_id', $order->id)->where('type', 'SALE_EARNING')->lockForUpdate()->first();
            if (! $entry || $entry->status !== 'HELD' || $this->hasActiveComplaint($order->id)) return;
            $entry->update(['status' => 'AVAILABLE']);
        }, 3);
    }

    /** Read-only ledger summary. */
    public function summary(Seller $seller): array
    {
        $entries = SellerWalletEntry::query()->where('seller_id', $seller->id);
        $reserved = (int) (clone $entries)->where('type', 'PAYOUT_RESERVATION')->where('status', 'RESERVED')->sum('amount_minor');
        $available = (int) (clone $entries)->where('type', 'SALE_EARNING')->where('status', 'AVAILABLE')->sum('amount_minor');
        return ['available_minor'=>$available-$reserved,'held_minor'=>(int)(clone $entries)->where('type','SALE_EARNING')->where('status','HELD')->sum('amount_minor'),'reserved_minor'=>$reserved,'paid_out_minor'=>(int)(clone $entries)->where('type','PAYOUT_COMPLETED')->sum('amount_minor'),'total_earnings_minor'=>(int)(clone $entries)->where('type','SALE_EARNING')->sum('amount_minor')];
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

    private function status(SellerOrder $order): string
    {
        return $order->workflow_status instanceof OrderStatus ? $order->workflow_status->value : (string) $order->workflow_status;
    }
}
