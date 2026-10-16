<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;

class CommissionService
{
    private const RATE_PERCENT = 10;

    public function generateForOrder(Order $order): void
    {
        $order->load('sellerOrders.seller', 'sellerOrders.items');

        foreach ($order->sellerOrders as $sellerOrder) {

            // Generate commission only for delivered seller orders.
            if ($sellerOrder->status !== 'delivered') {
                continue;
            }

            $sellerUserId = $sellerOrder->seller?->user_id;

            if (!$sellerUserId) {
                continue;
            }

            // Original product subtotal before voucher discounts.
            $subtotalMinor = (int) $sellerOrder->items->sum(
                fn($item) => (int) $item->subtotal_minor
            );

            // Seller-funded vouchers reduce seller revenue.
            $sellerDiscountMinor = (int) (
                $sellerOrder->seller_discount_minor ?? 0
            );

            // Platform-funded vouchers do not reduce seller revenue.
            $commissionBaseMinor = max(
                0,
                $subtotalMinor - $sellerDiscountMinor
            );

            // Commission is 10% of seller revenue base.
            $commissionMinor = intdiv(
                $commissionBaseMinor * self::RATE_PERCENT + 50,
                100
            );

            // Preserve existing commission records.
            Commission::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'seller_id' => $sellerUserId,
                ],
                [
                    'order_total_minor' => $commissionBaseMinor,
                    'rate' => self::RATE_PERCENT,
                    'amount_minor' => $commissionMinor,
                    'status' => 'pending',
                ]
            );
        }
    }
}
