<?php

namespace App\Http\Controllers;

use App\Enums\ProductModerationStatus;
use App\Models\BuyerStoreFollow;
use App\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function following(Request $request): View
    {
        abort_unless($request->user()?->isBuyer(), 403);

        $stores = BuyerStoreFollow::query()
            ->where('buyer_id', $request->user()->id)
            ->with(['seller.owner', 'seller.category'])
            ->latest()
            ->get()
            ->map(fn (BuyerStoreFollow $follow) => $follow->seller)
            ->filter(fn (?Seller $seller) => $seller
                && $seller->status === 'approved'
                && $seller->owner?->status === 'active'
                && ! $seller->owner?->is_suspended)
            ->values();

        return view('buyer.followed-stores', compact('stores'));
    }

    public function show(Seller $seller): View
    {
        $this->visibleSeller($seller);
        $products = $seller->products()->with(['category', 'images', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)->where('moderation_status', ProductModerationStatus::Approved->value)
            ->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))->paginate(18);
        $following = auth()->check() && BuyerStoreFollow::query()->where('buyer_id', auth()->id())->where('seller_id', $seller->id)->exists();
        $seller->loadCount('visibleReviews')->loadAvg('visibleReviews', 'rating');
        $reviews = $seller->visibleReviews()->latest()->paginate(10, ['*'], 'reviews_page');
        return view('buyer.store', compact('seller', 'products', 'following', 'reviews'));
    }

    public function follow(Request $request, Seller $seller): RedirectResponse
    {
        $this->visibleSeller($seller);
        abort_unless($request->user()?->isBuyer(), 403);
        BuyerStoreFollow::query()->firstOrCreate(['buyer_id'=>$request->user()->id, 'seller_id'=>$seller->id]);
        return back()->with('success', 'Store followed.');
    }

    public function unfollow(Request $request, Seller $seller): RedirectResponse
    {
        abort_unless($request->user()?->isBuyer(), 403);
        BuyerStoreFollow::query()->where('buyer_id', $request->user()->id)->where('seller_id', $seller->id)->delete();
        return back()->with('success', 'Store unfollowed.');
    }

    private function visibleSeller(Seller $seller): void
    {
        abort_unless($seller->status === 'approved' && $seller->owner?->status === 'active' && ! $seller->owner?->is_suspended, 404);
    }
}
