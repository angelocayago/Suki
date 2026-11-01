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

    public function show(Request $request, Seller $seller): View
    {
        $this->visibleSeller($seller);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc'],
        ]);
        $filters = array_replace(['q'=>'', 'category'=>'', 'sort'=>'newest'], array_filter($filters, fn ($value) => $value !== null));
        $seller->loadMissing('category');
        // Keep the existing public storefront eligibility, including stock.
        $visible = $seller->products()
            ->where('is_active', true)->where('moderation_status', ProductModerationStatus::Approved->value)
            ->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0));
        $visibleCount = (clone $visible)->count();
        $categories = \App\Models\Category::query()->whereIn('id', (clone $visible)->select('category_id'))->orderBy('name')->get(['id', 'name']);
        $query = (clone $visible)->with(['category', 'images', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
            ->withMin(['variants as storefront_price' => fn ($query) => $query->where('is_active', true)], 'price_minor');
        $term = trim($filters['q']);
        if ($term !== '') {
            $query->whereRaw('LOWER(products.name) LIKE ? ESCAPE \'!\'', ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%']);
        }
        if ($filters['category'] !== '') $query->where('category_id', $filters['category']);
        if ($filters['sort'] === 'newest') $query->orderByDesc('products.created_at');
        else $query->orderBy('storefront_price', $filters['sort'] === 'price_asc' ? 'asc' : 'desc');
        $products = $query->orderByDesc('products.id')->paginate(12)->withQueryString()->fragment('products');
        $following = $request->user()?->isBuyer() && BuyerStoreFollow::query()->where('buyer_id', $request->user()->id)->where('seller_id', $seller->id)->exists();
        $seller->loadCount('visibleReviews')->loadAvg('visibleReviews', 'rating');
        $reviews = $seller->visibleReviews()->latest()->orderByDesc('id')->paginate(6, ['*'], 'reviews_page')->withQueryString()->fragment('reviews');
        return view('buyer.store', compact('seller', 'products', 'following', 'reviews', 'filters', 'visibleCount', 'categories'), ['title'=>$seller->name.' | SUKI SHOP']);
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
