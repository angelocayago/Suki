<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\{OrderItem, SellerOrder, TransactionReview};

class TransactionReviewEligibility
{
    public function order(SellerOrder $order, int $buyerId, bool $productWindow = false): bool
    {
        return (int) $order->order?->buyer_id === $buyerId
            && $order->status !== 'cancelled'
            && $order->workflow_status === OrderStatus::Completed
            && $order->completed_at && ! $order->completed_at->isFuture()
            && (! $productWindow || ! $order->completed_at->lt(now()->subDays(30)));
    }

    public function productIdentity(OrderItem $item, SellerOrder $order): bool
    {
        return (int) $item->seller_order_id === (int) $order->id
            && (int) $item->order_id === (int) $order->order_id
            && $item->product_id && $item->product
            && (int) $item->product->seller_id === (int) $order->seller_id;
    }

    public function editable(TransactionReview $review, SellerOrder $order, int $buyerId): bool
    {
        return $review->kind === 'product' && (int) $review->buyer_id === $buyerId
            && ! $review->edited && ! $review->hidden_at && $this->order($order, $buyerId, true);
    }
}
