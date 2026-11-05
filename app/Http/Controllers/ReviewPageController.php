<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\TransactionReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewPageController extends Controller
{
    public function buyer(Request $request)
    {
        $input = $request->validate(['seller_order' => ['nullable', 'integer', 'min:1'], 'edit_item' => ['nullable', 'integer', 'min:1']]);
        $selected = filled($input['seller_order'] ?? null)
            ? SellerOrder::whereHas('order', fn ($q) => $q->where('buyer_id', $request->user()->id))->findOrFail($input['seller_order'])
            : null;
        $editItem = $input['edit_item'] ?? null;
        if ($editItem) abort_unless($selected && $selected->items()->whereKey($editItem)->exists(), 404);
        $orders = SellerOrder::query()->whereHas('order', fn ($q) => $q->where('buyer_id', $request->user()->id))
            ->when($selected, fn ($q) => $q->whereKey($selected->id), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where(fn ($q) => $q->where('workflow_status', OrderStatus::Completed->value)->where('status', '!=', 'cancelled')->whereNotNull('completed_at'))
                    ->orWhereIn('id', TransactionReview::where('buyer_id', $request->user()->id)->select('seller_order_id'));
            }))
            ->with(['seller', 'order', 'items.product', 'shipment'])->latest('completed_at')->paginate(15)->withQueryString();
        $reviews = TransactionReview::where('buyer_id', $request->user()->id)
            ->whereIn('seller_order_id', $orders->pluck('id'))->get()->keyBy('eligibility_key');
        $riders = Rider::whereIn('user_id', $orders->pluck('shipment.delivered_by')->filter())->with('user')->get()->keyBy('user_id');
        return view('reviews.buyer', compact('orders', 'reviews', 'riders', 'selected', 'editItem'));
    }

    public function seller(Request $request, \App\Support\SellerReviews $page)
    {
        if ($request->expectsJson() && $request->query('lookup') === 'products') {
            return response()->json($page->products($request))->header('Cache-Control','no-store, private');
        }
        return view('reviews.seller', $page->listing($request));
    }

    public function admin()
    {
        $reviews = TransactionReview::latest()->paginate(25);
        $events = DB::table('review_moderation_events')->whereIn('transaction_review_id', $reviews->pluck('id'))->orderByDesc('id')->get()->groupBy('transaction_review_id');
        return view('reviews.admin', compact('reviews', 'events'));
    }
}
