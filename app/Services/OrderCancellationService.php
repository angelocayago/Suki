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
    public function cancelByBuyer(Order $order, User $buyer, string $reason): Order
    {
        return DB::transaction(function () use ($order, $buyer, $reason) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ((int) $locked->buyer_id !== (int) $buyer->id || ! $buyer->hasRole('buyer')) {
                throw new AuthorizationException('You may cancel only your own order.');
            }

            if (strtoupper((string) $locked->status) === 'CANCELLED') {
                return $locked->fresh(['sellerOrders', 'items']);
            }

            if (strtoupper((string) $locked->status) !== 'PLACED') {
                throw ValidationException::withMessages([
                    'order' => 'This order can no longer be cancelled because seller processing has started.',
                ]);
            }

            $items = OrderItem::query()
                ->where('order_id', $locked->id)
                ->orderBy('product_variant_id')
                ->lockForUpdate()
                ->get();

            foreach ($items->whereNotNull('product_variant_id')->groupBy('product_variant_id') as $variantId => $lines) {
                $variant = ProductVariant::withTrashed()
                    ->whereKey($variantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $variant->increment('stock', $lines->sum('quantity'));
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
