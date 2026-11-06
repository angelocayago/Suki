<?php

namespace App\Services;

use App\Models\LogisticsCodRemittance;
use App\Models\LogisticsProvider;
use App\Models\OrderItem;
use App\Models\Rider;
use App\Models\RiderCodRemittance;
use App\Models\RiderShippingFeeEarning;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerWalletEntry;
use App\Models\Shipment;
use App\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Support\ManilaTime;

/** Read-only projections over the canonical custody and settlement ledgers. */
class MoneyReportService
{
    public function rider(Rider $rider): array
    {
        $cod = RiderCodRemittance::query()->where('rider_id', $rider->id)
            ->with(['shipment', 'sellerOrder.order'])->latest('id')->get();
        $earnings = RiderShippingFeeEarning::query()->where('rider_id', $rider->id)
            ->with(['shipment', 'sellerOrder.order'])->latest('earned_at')->get();

        return compact('cod', 'earnings') + [
            // The liability is always the full persisted COD collection. A
            // rider's separate shipping fee earning is never netted here.
            'cod_liability_minor' => (int) $cod->sum('expected_minor'),
            'cod_outstanding_minor' => (int) $cod->whereIn('status', ['OUTSTANDING', 'SUBMITTED', 'REJECTED'])->sum('expected_minor'),
            'cod_remitted_minor' => (int) $cod->where('status', 'ACCEPTED')->sum('accepted_minor'),
            'cod_accepted_minor' => (int) $cod->where('status', 'ACCEPTED')->sum('accepted_minor'),
            'earnings_available_minor' => (int) $earnings->sum('amount_minor'),
            'earnings_pending_minor' => 0,
            // There is no rider payout ledger in the current schema. Do not
            // present COD custody as a withdrawable wallet balance.
            'earnings_paid_minor' => 0,
        ];
    }

    public function logistics(LogisticsProvider $provider): array
    {
        $riderRemittances = RiderCodRemittance::query()->where('logistics_provider_id', $provider->id)
            ->with(['rider.user', 'shipment', 'sellerOrder.order'])->latest('id')->get();
        $upstream = LogisticsCodRemittance::query()->where('logistics_provider_id', $provider->id)
            ->with(['items.riderRemittance.shipment'])->latest('id')->get();
        $received = (int) $riderRemittances->where('status', 'ACCEPTED')->sum('accepted_minor');
        $remitted = (int) $upstream->where('status', 'ACCEPTED')->sum('accepted_minor');

        return compact('riderRemittances', 'upstream') + [
            'received_from_riders_minor' => $received,
            'remitted_to_admin_minor' => $remitted,
            'held_by_logistics_minor' => max(0, $received - $remitted),
        ];
    }

    public function seller(Seller $seller): array
    {
        $releaseIds = app(SellerWalletService::class)->validReleaseEntries($seller)->select('id');
        $entries = SellerWalletEntry::query()->where('seller_id', $seller->id)
            ->where(fn($q)=>$q->whereIn('type', ['SALE_EARNING', 'REFUND_ADJUSTMENT'])->orWhereIn('id',$releaseIds))
            ->latest('id')->get();
        $orders = SellerOrder::query()->where('seller_id', $seller->id)
            ->whereIn('id', $entries->where('type', 'SALE_EARNING')->pluck('seller_order_id')->filter())
            ->with(['order', 'items'])->get()->keyBy('id');
        $sellerDiscounts = VoucherRedemption::query()->whereIn('seller_order_id', $orders->keys())
            ->where('scope_snapshot', 'SELLER')->get()->groupBy('seller_order_id')->map->sum('discount_minor');
        $rows = $entries->map(function (SellerWalletEntry $entry) use ($orders, $sellerDiscounts) {
            $order = $orders->get($entry->seller_order_id);
            $gross = $order ? (int) $order->items->sum(fn ($item) => (int) $item->unit_price_minor * (int) $item->quantity) : 0;
            $commission = $order ? (int) $order->items->sum('commission_minor') : 0;
            $discount = (int) ($sellerDiscounts[$entry->seller_order_id] ?? 0);
            if ($entry->type === 'EARNING_RELEASE') $gross = $commission = $discount = 0;
            return compact('entry', 'order', 'gross', 'commission', 'discount');
        });

        return ['rows' => $rows, 'wallet' => app(SellerWalletService::class)->summary($seller), 'earningRelease'=>app(SellerWalletService::class)->releaseReadiness()];
    }

    public function admin(array $filters): array
    {
        [$from, $to] = ManilaTime::utcDateBounds($filters['from'] ?? null, $filters['to'] ?? null);
        $shipmentScope = Shipment::query()->where('cod_collected', true)
            ->when($filters['provider_id'] ?? null, fn (Builder $q, $id) => $q->where('logistics_provider_id', $id))
            ->when($filters['seller_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('sellerOrder', fn (Builder $sellerOrders) => $sellerOrders->where('seller_id', $id)));
        $shipments = $this->dated($shipmentScope, $from, $to, 'cod_collected_at')->get();
        $riderCod = $this->dated(RiderCodRemittance::query()->with(['rider.user', 'provider', 'shipment']), $from, $to, 'created_at')
            ->when($filters['provider_id'] ?? null, fn (Builder $q, $id) => $q->where('logistics_provider_id', $id))
            ->when($filters['seller_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('sellerOrder', fn (Builder $sellerOrders) => $sellerOrders->where('seller_id', $id)))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))->get();
        $upstream = $this->dated(LogisticsCodRemittance::query()->with('provider'), $from, $to, 'created_at')
            ->when($filters['provider_id'] ?? null, fn (Builder $q, $id) => $q->where('logistics_provider_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))->get();
        $earnings = $this->dated(RiderShippingFeeEarning::query()
            ->when($filters['provider_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('shipment', fn (Builder $shipments) => $shipments->where('logistics_provider_id', $id)))
            ->when($filters['seller_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('sellerOrder', fn (Builder $sellerOrders) => $sellerOrders->where('seller_id', $id))), $from, $to, 'earned_at')->get();
        $wallet = SellerWalletEntry::query()->whereIn('type', ['SALE_EARNING', 'REFUND_ADJUSTMENT'])
            ->when($filters['seller_id'] ?? null, fn (Builder $q, $id) => $q->where('seller_id', $id))
            ->when($from, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('created_at', '<=', $to))->get();
        // Financial balances are current obligations, not sums of date-filtered
        // sales movements. Reuse the Seller Wallet projection, including payouts.
        $balances = app(SellerWalletService::class)->reportSummary(isset($filters['seller_id']) ? (int)$filters['seller_id'] : null);
        $sellerOrders = $this->dated(SellerOrder::query()->where('workflow_status', 'COMPLETED')->where('status', '!=', 'cancelled')
            ->when($filters['seller_id'] ?? null, fn (Builder $q, $id) => $q->where('seller_id', $id))->with('items'), $from, $to, 'completed_at')->get();
        $commission = (int) $sellerOrders->sum(fn (SellerOrder $order) => (int) $order->items->sum('commission_minor'));
        $refundAccounting = DB::table('return_refund_accounting')
            ->join('return_refund_requests', 'return_refund_requests.id', '=', 'return_refund_accounting.return_refund_request_id')
            ->when($filters['seller_id'] ?? null, fn ($q, $id) => $q->where('return_refund_requests.seller_id', $id))
            ->when($from, fn ($q) => $q->where('return_refund_accounting.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('return_refund_accounting.created_at', '<=', $to))
            ->selectRaw('COALESCE(SUM(return_refund_accounting.commission_reversal_minor), 0) AS commission_reversal_minor')
            ->selectRaw('COALESCE(SUM(return_refund_accounting.seller_adjustment_minor), 0) AS seller_adjustment_minor')
            ->first();
        $received = (int) $riderCod->where('status', 'ACCEPTED')->sum('accepted_minor');
        $remitted = (int) $upstream->where('status', 'ACCEPTED')->sum('accepted_minor');

        $providerRows = $riderCod->groupBy('logistics_provider_id')->map(function ($rows, $providerId) use ($upstream) {
            $accepted = (int) $rows->where('status', 'ACCEPTED')->sum('accepted_minor');
            $toAdmin = (int) $upstream->where('logistics_provider_id', $providerId)->where('status', 'ACCEPTED')->sum('accepted_minor');
            return ['provider' => $rows->first()->provider?->name ?? 'Unknown', 'rider_received_minor' => $accepted, 'remitted_minor' => $toAdmin, 'outstanding_minor' => max(0, $accepted - $toAdmin)];
        })->values();

        return compact('shipments', 'riderCod', 'upstream', 'earnings', 'wallet', 'providerRows') + [
            'total_cod_collected_minor' => (int) $shipments->sum('cod_collected_minor'),
            'cod_held_by_riders_minor' => (int) $riderCod->whereIn('status', ['OUTSTANDING', 'SUBMITTED', 'REJECTED'])->sum('expected_minor'),
            'cod_held_by_logistics_minor' => max(0, $received - $remitted),
            'cod_remitted_to_admin_minor' => $remitted,
            'rider_earnings_minor' => (int) $earnings->sum('amount_minor'),
            'seller_payable_minor' => $balances['available_minor'] + $balances['held_minor'] + $balances['reserved_minor'],
            'seller_available_minor' => $balances['available_minor'],
            'seller_held_minor' => $balances['held_minor'],
            'seller_reserved_minor' => $balances['reserved_minor'],
            'seller_paid_minor' => $balances['verified_paid_out_minor'],
            'seller_lifetime_earnings_minor' => $balances['total_earnings_minor'],
            'earning_release' => app(SellerWalletService::class)->releaseReadiness(),
            'refund_adjustments_minor' => (int) $wallet->where('type', 'REFUND_ADJUSTMENT')->sum('amount_minor'),
            'commission_reversal_minor' => (int) $refundAccounting->commission_reversal_minor,
            'commission_revenue_minor' => $commission - (int) $refundAccounting->commission_reversal_minor,
        ];
    }

    private function dated(Builder $query, ?string $from, ?string $to, string $column): Builder
    {
        if ($from) $query->where($column, '>=', $from);
        if ($to) $query->where($column, '<=', $to);
        return $query;
    }
}
