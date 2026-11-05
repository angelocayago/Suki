<?php

namespace App\Support;

use App\Models\{Order, OrderItem, SellerOrder, TransactionReview};
use App\Services\TransactionReviewEligibility;

/** Presentation for the existing transaction review endpoints; never writes reviews. */
class BuyerReviews
{
    public function __construct(private TransactionReviewEligibility $eligibility) {}

    public function url(int $sellerOrderId, string $anchor): string
    {
        return route('reviews.buyer-page', ['seller_order' => $sellerOrderId]).'#'.$anchor;
    }

    public function productState(OrderItem $item, SellerOrder $order, ?TransactionReview $review, int $buyerId): array
    {
        $canCreate = $this->eligibility->order($order, $buyerId, true) && $this->eligibility->productIdentity($item, $order);
        $expired = $order->completed_at && $order->completed_at->lt(now()->subDays(30));
        return [
            'url' => $this->url($order->id, 'product-review-'.$item->id),
            'edit_url' => route('reviews.buyer-page', ['seller_order' => $order->id, 'edit_item' => $item->id]).'#product-review-'.$item->id,
            'create' => ! $review && $canCreate,
            'view' => $review !== null,
            'edit' => $review && $this->eligibility->editable($review, $order, $buyerId),
            'reason' => $expired ? 'The 30-day product review window has expired.' : 'This purchased item is not currently eligible for a product review.',
        ];
    }

    public function forOrder(Order $order, int $buyerId): array
    {
        abort_unless((int) $order->buyer_id === $buyerId, 403);
        $order->loadMissing(['items.product', 'sellerOrders.order']);
        $reviews = TransactionReview::where('buyer_id', $buyerId)->whereIn('seller_order_id', $order->sellerOrders->pluck('id'))->get()->keyBy('eligibility_key');
        $items = []; $sellers = [];
        foreach ($order->sellerOrders as $sellerOrder) {
            $sellerReview = $reviews->get('seller-order:'.$sellerOrder->id);
            $sellers[$sellerOrder->id] = [
                'url' => $this->url($sellerOrder->id, 'seller-review-'.$sellerOrder->id),
                'create' => ! $sellerReview && $this->eligibility->order($sellerOrder, $buyerId),
                'view' => $sellerReview !== null,
            ];
            foreach ($order->items->where('seller_order_id', $sellerOrder->id) as $item) {
                $items[$item->id] = $this->productState($item, $sellerOrder, $reviews->get('item:'.$item->id), $buyerId);
            }
        }
        return compact('items', 'sellers');
    }
}
