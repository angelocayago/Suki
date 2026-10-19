<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\TransactionReview;
use App\Services\DomainNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionReviewController extends Controller
{
    public function productSummary(Product $product)
    {
        abort_unless($product->is_active && $product->moderation_status?->value === 'APPROVED' && $product->seller?->status === 'approved' && $product->seller?->owner?->status === 'active' && ! $product->seller?->owner?->is_suspended, 404);
        $visible = TransactionReview::where('kind', 'product')->where('product_id', $product->id)->whereNull('hidden_at');
        $summary = (clone $visible)->selectRaw('count(*) as review_count, avg(rating) as average_rating')->first();
        return response()->json([
            'average_rating' => $summary->average_rating === null ? null : (float) $summary->average_rating,
            'review_count' => (int) $summary->review_count,
            'reviews' => $visible->latest()->paginate(25, ['id', 'rating', 'body', 'seller_response', 'created_at']),
        ]);
    }

    public function moderationIndex()
    {
        return response()->json(TransactionReview::latest()->paginate(25));
    }

    private function input(Request $request): array
    {
        return $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['nullable', 'string', 'max:500']]);
    }

    private function eligible(SellerOrder $order, Request $request, bool $limit = false): void
    {
        abort_unless((int) $order->order->buyer_id === (int) $request->user()->id, 403);
        if ($order->status === 'cancelled' || $order->workflow_status !== OrderStatus::Completed || ! $order->completed_at || $order->completed_at->isFuture() || ($limit && $order->completed_at->lt(now()->subDays(30)))) {
            throw ValidationException::withMessages(['order' => 'This transaction is not eligible for review.']);
        }
    }

    public function product(Request $request, OrderItem $item)
    {
        $data = $this->input($request);
        $result = DB::transaction(function () use ($request, $item, $data) {
            $order = SellerOrder::with('order')->lockForUpdate()->findOrFail($item->seller_order_id);
            $this->eligible($order, $request, true);
            abort_unless($item->product_id && (int) $item->order_id === (int) $order->order_id && $item->product && (int) $item->product->seller_id === (int) $order->seller_id, 403);
            abort_if($request->filled('product_id') && (int) $request->input('product_id') !== (int) $item->product_id, 403);
            return $this->createReview('product', 'item:'.$item->id, $request, $order, $data, ['order_item_id' => $item->id, 'product_id' => $item->product_id]);
        });
        return $request->expectsJson() ? $result : redirect()->route('reviews.buyer-page')->with('success', 'Review saved.');
    }

    public function seller(Request $request, SellerOrder $sellerOrder)
    {
        $data = $this->input($request);
        $result = DB::transaction(function () use ($request, $sellerOrder, $data) {
            $order = SellerOrder::with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            $this->eligible($order, $request);
            abort_if($request->filled('seller_id') && (int) $request->input('seller_id') !== (int) $order->seller_id, 403);
            return $this->createReview('seller', 'seller-order:'.$order->id, $request, $order, $data);
        });
        return $request->expectsJson() ? $result : redirect()->route('reviews.buyer-page')->with('success', 'Store review saved.');
    }

    public function rider(Request $request, SellerOrder $sellerOrder)
    {
        $data = $this->input($request);
        $result = DB::transaction(function () use ($request, $sellerOrder, $data) {
            $order = SellerOrder::with(['order', 'shipment'])->lockForUpdate()->findOrFail($sellerOrder->id);
            $this->eligible($order, $request);
            $shipment = $order->shipment;
            $rider = $shipment && $shipment->proof_delivered_at && $shipment->delivered_by
                ? Rider::where('user_id', $shipment->delivered_by)->first() : null;
            if (! $rider) {
                throw ValidationException::withMessages(['rider' => 'The final delivery rider cannot be verified.']);
            }
            abort_if($request->filled('rider_id') && (int) $request->input('rider_id') !== (int) $rider->id, 403);
            return $this->createReview('rider', 'rider-order:'.$order->id, $request, $order, $data, ['rider_id' => $rider->id]);
        });
        return $request->expectsJson() ? $result : redirect()->route('reviews.buyer-page')->with('success', 'Rider rating saved.');
    }

    private function createReview(string $kind, string $key, Request $request, SellerOrder $order, array $data, array $extra = [])
    {
        if (TransactionReview::where('eligibility_key', $key)->exists()) {
            throw ValidationException::withMessages(['review' => 'This transaction has already been reviewed.']);
        }
        $review = TransactionReview::create($data + $extra + [
            'kind' => $kind, 'eligibility_key' => $key, 'buyer_id' => $request->user()->id,
            'seller_order_id' => $order->id, 'seller_id' => $order->seller_id,
        ]);
        if (in_array($kind, ['product', 'seller'], true) && $order->seller?->user_id) {
            app(DomainNotificationService::class)->afterCommit($order->seller->user_id, 'New review', 'Your store received a review.', ['review_id' => $review->id]);
        }
        return response()->json(['id' => $review->id], 201);
    }

    public function edit(Request $request, TransactionReview $review)
    {
        $data = $this->input($request);
        $result = DB::transaction(function () use ($request, $review, $data) {
            $review = TransactionReview::lockForUpdate()->findOrFail($review->id);
            abort_unless($review->kind === 'product' && (int) $review->buyer_id === (int) $request->user()->id, 403);
            if ($review->edited || $review->hidden_at) {
                throw ValidationException::withMessages(['review' => 'This review cannot be edited.']);
            }
            $order = SellerOrder::with('order')->findOrFail($review->seller_order_id);
            $this->eligible($order, $request, true);
            $review->update($data + ['edited' => true]);
            return response()->json(['id' => $review->id]);
        });
        return $request->expectsJson() ? $result : redirect()->route('reviews.buyer-page')->with('success', 'Review updated.');
    }

    public function respond(Request $request, TransactionReview $review)
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:500']]);
        abort_unless(in_array($review->kind, ['product', 'seller'], true) && DB::table('sellers')->where('id', $review->seller_id)->where('user_id', $request->user()->id)->exists(), 403);
        DB::transaction(function () use ($review, $data) {
            $review->update(['seller_response' => $data['response']]);
            app(DomainNotificationService::class)->afterCommit($review->buyer_id, 'Seller responded to your review', 'The seller has responded to your review.', ['review_id' => $review->id]);
        });
        return $request->expectsJson() ? response()->json(['id' => $review->id]) : redirect()->route('reviews.seller-page')->with('success', 'Response saved.');
    }

    public function moderate(Request $request, TransactionReview $review)
    {
        $data = $request->validate(['action' => ['required', 'in:hide,restore'], 'reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $review, $data) {
            $review = TransactionReview::lockForUpdate()->findOrFail($review->id);
            $review->update(['hidden_at' => $data['action'] === 'hide' ? now() : null]);
            DB::table('review_moderation_events')->insert([
                'transaction_review_id' => $review->id, 'admin_id' => $request->user()->id,
                'action' => $data['action'], 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return $request->expectsJson() ? response()->json(['id' => $review->id]) : redirect()->route('reviews.admin-page')->with('success', 'Moderation saved.');
    }
}
