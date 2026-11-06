<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCancellationService
{
    public const REASONS = [
        'changed_mind' => 'I changed my mind',
        'ordered_by_mistake' => 'I ordered by mistake',
        'found_better_price' => 'I found a better price',
        'wrong_product' => 'Wrong product or variation',
        'seller_requested' => 'Seller requested cancellation',
        'other' => 'Other',
    ];

    private const PROGRESSION_DATES = [
        'confirmed_at', 'preparing_at', 'ready_for_pickup_at', 'picked_up_at',
        'at_sorting_center_at', 'sorted_at', 'assigned_to_rider_at',
        'out_for_delivery_at', 'delivered_at', 'completed_at', 'delivery_failed_at', 'returned_at',
    ];

    /** Read-only presentation policy. Cancellation reloads and locks this graph. */
    public function rejectionReason(Order $order, bool $lockFinancialRecords = false): ?string
    {
        if (strtoupper((string) $order->status) !== 'PLACED') {
            return 'Cancellation is available only before any seller starts processing the order.';
        }
        if ($order->payment_method !== 'cod' || strtoupper((string) $order->payment_status) !== 'UNPAID') {
            return 'This order needs payment review. Please contact support; cancellation cannot issue a refund.';
        }
        if ($order->cancelled_at || $this->hasAny($order, self::PROGRESSION_DATES)) {
            return 'The order has processing history. Please contact support if its status looks incorrect.';
        }
        $order->loadMissing(['sellerOrders.shipment.events', 'sellerOrders.seller',
            'items.productVariant' => fn ($q) => $q->withTrashed(),
            'items.productVariant.product' => fn ($q) => $q->withTrashed(), 'payments']);
        $inconsistent = 'Order records are incomplete or inconsistent. Please contact support before cancelling.';
        if ($order->sellerOrders->isEmpty() || $order->items->isEmpty()) return $inconsistent;
        foreach ($order->sellerOrders as $sellerOrder) {
            if ($sellerOrder->getRawOriginal('workflow_status') !== 'PLACED' || $sellerOrder->status !== 'pending'
                || $sellerOrder->logistics_provider_id || $this->hasAny($sellerOrder, self::PROGRESSION_DATES)) {
                return 'At least one seller has started processing this order. Whole-order cancellation is no longer available.';
            }
            $shipment = $sellerOrder->shipment;
            if (! $shipment || ! $order->items->contains('seller_order_id', $sellerOrder->id)) return $inconsistent;
            if ($shipment->getRawOriginal('workflow_status') !== 'PLACED' || $shipment->status !== 'unassigned'
                || $this->hasAny($shipment, ['logistics_provider_id','pickup_rider_id','rider_id','pickup_accepted_at',
                    'delivery_accepted_at','sorting_area','rescheduled_at','proof_path','proof_delivered_at','delivered_by',
                    'received_by','cod_collected_at','location_updated_at','location_rider_id',
                    'current_latitude','current_longitude','proof_mime','proof_size','proof_checksum','delivery_note'])
                || $shipment->cod_collected || $shipment->cod_collected_minor !== null || (int) $shipment->attempts !== 0
                || $shipment->events->contains(fn ($event) => $event->status !== 'PLACED')) {
                return 'A parcel has assignment, collection or fulfillment history. Please contact support instead of cancelling.';
            }
        }
        foreach ($order->items as $item) {
            $sellerOrder = $order->sellerOrders->firstWhere('id', $item->seller_order_id);
            $variant = $item->productVariant;
            if (! $sellerOrder || ! $variant || (int) $item->quantity < 1
                || ! $item->product_id || (int) $variant->stock < 0
                || (int) $variant->product_id !== (int) $item->product_id
                || ! $variant->product || (int) $variant->product->seller_id !== (int) $sellerOrder->seller_id
                || ! $sellerOrder->seller || (int) $item->seller_id !== (int) $sellerOrder->seller->user_id) return $inconsistent;
        }
        // Even pending payment records are ambiguous here: this flow handles only
        // unpaid COD with no payment/settlement ledger, never automatic refunds.
        if ($order->payments->isNotEmpty()) return 'Payment records require support review before this order can be cancelled.';
        $ids = $order->sellerOrders->modelKeys();
        foreach (['seller_wallet_entries', 'rider_cod_remittances', 'rider_shipping_fee_earnings'] as $table) {
            $query = DB::table($table)->whereIn('seller_order_id', $ids)->orderBy('id');
            if ($lockFinancialRecords) $query->lockForUpdate();
            if ($query->get(['id'])->isNotEmpty()) {
                return 'Collection or settlement records require support review before this order can be cancelled.';
            }
        }
        return null;
    }

    private function hasAny($model, array $fields): bool
    {
        foreach ($fields as $field) if ($model->getRawOriginal($field) !== null) return true;
        return false;
    }

    public function cancelByBuyer(Order $order, User $buyer, string $reason): Order
    {
        return DB::transaction(function () use ($order, $buyer, $reason) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ((int) $locked->buyer_id !== (int) $buyer->id || ! $buyer->hasRole('buyer')) {
                throw new AuthorizationException('You may cancel only your own order.');
            }

            if (! array_key_exists($reason, self::REASONS)) {
                throw ValidationException::withMessages(['cancel_reason' => 'Choose a valid cancellation reason.']);
            }

            if (strtoupper((string) $locked->status) === 'CANCELLED') {
                return $locked->fresh(['sellerOrders', 'items']);
            }

            // Same parent -> seller orders -> shipments order as fulfillment.
            $sellerOrders = $locked->sellerOrders()->orderBy('id')->lockForUpdate()->get();
            $shipments = Shipment::whereIn('seller_order_id', $sellerOrders->modelKeys())->orderBy('id')->lockForUpdate()->get();
            $events = \App\Models\DeliveryEvent::whereIn('shipment_id', $shipments->modelKeys())->orderBy('id')->lockForUpdate()->get();
            foreach ($shipments as $shipment) $shipment->setRelation('events', $events->where('shipment_id', $shipment->id)->values());
            foreach ($sellerOrders as $sellerOrder) $sellerOrder->setRelation('shipment', $shipments->firstWhere('seller_order_id', $sellerOrder->id));
            $locked->setRelation('sellerOrders', $sellerOrders);
            $locked->setRelation('payments', $locked->payments()->orderBy('id')->lockForUpdate()->get());

            $items = OrderItem::query()
                ->where('order_id', $locked->id)
                ->orderBy('product_variant_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $variants = ProductVariant::withTrashed()->whereIn('id', $items->pluck('product_variant_id')->filter()->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) $item->setRelation('productVariant', $variants->get($item->product_variant_id));
            $locked->setRelation('items', $items);
            if ($message = $this->rejectionReason($locked, true)) throw ValidationException::withMessages(['order' => $message]);
            foreach ($items->groupBy('product_variant_id') as $variantId => $lines) {
                $variants[$variantId]->increment('stock', $lines->sum('quantity'));
            }

            $now = now();
            $locked->update([
                'status' => 'CANCELLED',
                'cancel_reason' => $reason,
                'cancelled_at' => $now,
            ]);

            SellerOrder::query()
                ->where('order_id', $locked->id)
                ->update([
                    'status' => 'cancelled',
                    'status_changed_at' => $now,
                    'note' => 'Cancelled by buyer: '.$reason,
                ]);

            Shipment::query()
                ->whereIn('seller_order_id', $locked->sellerOrders()->pluck('id'))
                ->update([
                    'status' => 'cancelled',
                    'status_changed_at' => $now,
                ]);

            $cancelled = $locked->fresh(['sellerOrders.seller', 'sellerOrders.shipment.pickupRider', 'sellerOrders.shipment.rider', 'items']);
            foreach ($cancelled->sellerOrders as $sellerOrder) {
                $recipients = collect([$sellerOrder->seller?->user_id, $sellerOrder->shipment?->pickupRider?->user_id, $sellerOrder->shipment?->rider?->user_id])->filter()->unique();
                foreach ($recipients as $userId) {
                    app(DomainNotificationService::class)->afterCommit($userId, 'Order cancelled', 'An order or assignment was cancelled.', ['seller_order_id' => $sellerOrder->id]);
                }
            }

            return $cancelled;
        }, 3);
    }
}
