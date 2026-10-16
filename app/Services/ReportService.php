<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\LogisticsProvider;
use App\Models\Payout;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\VoucherRedemption;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    public function admin(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $completed = $this->completedOrders($from, $to)->with(['seller', 'items.product.category'])->get();
        $cancelled = $this->dated(SellerOrder::query()->where('status', 'cancelled'), $from, $to, 'updated_at')->count();

        return [
            'summary' => $this->financialSummary($completed),
            'completed_orders' => $completed->count(),
            'cancelled_orders' => $cancelled,
            'seller_performance' => $this->sellerPerformance($completed),
            'category_performance' => $this->categoryPerformance($completed),
            'payouts' => [
                'pending_minor' => (int) Payout::query()->where('status', 'PENDING')->sum('amount_minor'),
                'approved_minor' => (int) Payout::query()->where('status', 'APPROVED')->sum('amount_minor'),
                'paid_minor' => (int) Payout::query()->where('status', 'PAID')->sum('amount_minor'),
            ],
        ];
    }

    public function seller(Seller $seller, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $completed = $this->completedOrders($from, $to)->where('seller_id', $seller->id)->with(['items.product'])->get();
        $cancelled = $this->dated(SellerOrder::query()->where('seller_id', $seller->id)->where('status', 'cancelled'), $from, $to, 'updated_at')->count();
        $products = [];
        foreach ($completed as $order) foreach ($order->items as $item) {
            $key = $item->product_id ?: $item->product_slug;
            $products[$key] ??= ['name'=>$item->product_name, 'units'=>0, 'raw_minor'=>0];
            $products[$key]['units'] += (int) $item->quantity;
            $products[$key]['raw_minor'] += (int) $item->unit_price_minor * (int) $item->quantity;
        }
        usort($products, fn ($a, $b) => $b['units'] <=> $a['units'] ?: $b['raw_minor'] <=> $a['raw_minor']);

        return ['summary'=>$this->financialSummary($completed), 'completed_orders'=>$completed->count(), 'cancelled_orders'=>$cancelled, 'units_sold'=>array_sum(array_column($products, 'units')), 'products'=>array_values($products)];
    }

    public function logistics(LogisticsProvider $provider, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $shipments = $this->dated(Shipment::query()->where('logistics_provider_id', $provider->id)->with(['rider.user']), $from, $to, 'status_changed_at')->get();
        $statuses = $shipments->countBy(fn (Shipment $shipment) => $this->status($shipment));
        $riders = [];
        foreach ($shipments as $shipment) {
            if (! $shipment->rider) continue;
            $id = $shipment->rider->id;
            $riders[$id] ??= ['name'=>$shipment->rider->user?->name ?? 'Rider #'.$id, 'assignments'=>0, 'delivered'=>0, 'cod_collected_minor'=>0];
            $riders[$id]['assignments']++;
            if ($this->status($shipment) === OrderStatus::Delivered->value || $this->status($shipment) === OrderStatus::Completed->value) $riders[$id]['delivered']++;
            $riders[$id]['cod_collected_minor'] += (int) ($shipment->cod_collected_minor ?? 0);
        }
        return ['total'=>$shipments->count(), 'statuses'=>$statuses, 'cod_collected_minor'=>(int) $shipments->sum('cod_collected_minor'), 'riders'=>array_values($riders), 'shipments'=>$shipments];
    }

    public function rider(Rider $rider, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $shipments = $this->dated(Shipment::query()->where(fn (Builder $q) => $q->where('rider_id', $rider->id)->orWhere('pickup_rider_id', $rider->id)), $from, $to, 'status_changed_at')->get();
        $statuses = $shipments->countBy(fn (Shipment $shipment) => $this->status($shipment));
        return ['assignments'=>$shipments->count(), 'completed'=>(int) (($statuses[OrderStatus::Completed->value] ?? 0) + ($statuses[OrderStatus::Delivered->value] ?? 0)), 'pickup_assignments'=>(int) $shipments->where('pickup_rider_id', $rider->id)->count(), 'delivery_assignments'=>(int) $shipments->where('rider_id', $rider->id)->count(), 'cod_collected_minor'=>(int) $shipments->where('rider_id', $rider->id)->sum('cod_collected_minor'), 'statuses'=>$statuses, 'shipments'=>$shipments];
    }

    private function completedOrders(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return $this->dated(SellerOrder::query()->where('workflow_status', OrderStatus::Completed->value)->where('status', '!=', 'cancelled'), $from, $to, 'completed_at');
    }

    private function dated(Builder $query, ?CarbonInterface $from, ?CarbonInterface $to, string $column): Builder
    {
        if ($from) $query->where($column, '>=', $from);
        if ($to) $query->where($column, '<=', $to);
        return $query;
    }

    private function financialSummary($orders): array
    {
        $sellerOrderIds = $orders->pluck('id');
        $sellerDiscounts = VoucherRedemption::query()->whereIn('seller_order_id', $sellerOrderIds)->where('scope_snapshot', 'SELLER')->sum('discount_minor');
        $platformDiscounts = VoucherRedemption::query()->whereIn('seller_order_id', $sellerOrderIds)->where('scope_snapshot', 'PLATFORM')->sum('discount_minor');
        $raw = 0; $commission = 0; $shipping = 0;
        foreach ($orders as $order) { $shipping += (int) $order->shipping_fee_minor; foreach ($order->items as $item) { $raw += (int) $item->unit_price_minor * (int) $item->quantity; $commission += (int) $item->commission_minor; } }
        return ['raw_minor'=>$raw,'commission_minor'=>$commission,'seller_discount_minor'=>(int)$sellerDiscounts,'platform_discount_minor'=>(int)$platformDiscounts,'shipping_minor'=>$shipping,'net_minor'=>$raw-$commission-(int)$sellerDiscounts];
    }

    private function sellerPerformance($orders): array
    {
        return $orders->groupBy('seller_id')->map(function ($rows) { $summary=$this->financialSummary($rows); return ['seller'=>$rows->first()->seller?->name ?? 'Unknown','completed_orders'=>$rows->count()] + $summary; })->values()->all();
    }

    private function categoryPerformance($orders): array
    {
        $rows=[]; foreach ($orders as $order) foreach ($order->items as $item) { $name=$item->category_name_snapshot ?? $item->product?->category?->name ?? 'Uncategorized'; $rows[$name] ??=['category'=>$name,'units'=>0,'raw_minor'=>0]; $rows[$name]['units']+=(int)$item->quantity; $rows[$name]['raw_minor']+=(int)$item->unit_price_minor*(int)$item->quantity; } return array_values($rows);
    }

    private function status(Shipment $shipment): string { return $shipment->workflow_status instanceof OrderStatus ? $shipment->workflow_status->value : (string) $shipment->workflow_status; }
}
