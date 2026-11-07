<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\{CartItem, Product, ProductVariant, User, WishlistItem};
use App\Services\{CheckoutService, ProductCatalogService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    public function __construct(private CartController $actions, private CheckoutService $checkout, private ProductCatalogService $catalog) {}

    private function product(string $slug): array
    {
        $model = Product::withTrashed()->where('slug', $slug)->firstOrFail();
        $variant = ProductVariant::with(['product' => fn ($q) => $q->withTrashed()->with(['category', 'seller.owner'])])->where('product_id', $model->id)->where('is_active', true)->orderBy('id')->first();
        $issue = $this->checkout->cartIssue($variant, 1, $slug);
        if ($issue && $issue !== 'This item is out of stock.') throw ValidationException::withMessages(['product' => $issue]);
        $product = $this->catalog->marketplaceProduct($slug);
        if (!$product) throw ValidationException::withMessages(['product' => 'This product is no longer available.']);
        return $product;
    }

    public function index(Request $request)
    {
        return $this->actions->action($request, function () use ($request) {
            $catalog = $this->catalog->marketplaceProducts();
            $wishlist = $request->user()->wishlistItems()->get()->mapWithKeys(function ($item) use ($catalog) {
                $current = $catalog[$item->product_slug] ?? null;
                $inStock = $current && collect($current['variants'])->contains(fn ($variant) => $variant['stock'] > 0);
                return [$item->product_slug => ['name' => $item->product_name, 'price' => (float) $item->price, 'image' => $item->image,
                    'availability' => !$current ? 'unavailable' : ($inStock ? 'in_stock' : 'out_of_stock'),
                    'availability_message' => !$current ? 'This product is no longer available.' : (!$inStock ? 'This item is out of stock.' : 'In stock'),
                    'choose_variant' => $current && count($current['variants']) > 1]];
            })->all();
            return response()->view('buyer.wishlist', compact('wishlist'));
        }, '');
    }

    public function add(Request $request, string $slug)
    {
        return $this->actions->action($request, function () use ($request, $slug) {
            $request->validate(['cart_item_id' => ['nullable', 'integer', 'min:1']]);
            // Foreign Cart IDs remain 404, even when a product is unavailable.
            $cart = $request->filled('cart_item_id') ? CartItem::where('user_id', $request->user()->id)->where('product_slug', $slug)->findOrFail($request->input('cart_item_id')) : null;
            $product = $this->product($slug);
            return DB::transaction(function () use ($request, $slug, $cart, $product) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $existing = WishlistItem::where('user_id', $request->user()->id)->where('product_slug', $slug)->lockForUpdate()->first();
                WishlistItem::updateOrCreate(['user_id' => $request->user()->id, 'product_slug' => $slug], ['product_name' => $product['name'], 'price' => $product['price'], 'image' => $product['image']]);
                $cart?->delete();
                return $cart ? $product['name'].' moved to your wishlist!' : ($existing ? 'Already in your wishlist.' : $product['name'].' added to your wishlist!');
            }, 3);
        }, 'Item added to your wishlist.');
    }

    public function remove(Request $request, string $slug)
    {
        return $this->actions->action($request, function () use ($request, $slug) {
            if (!WishlistItem::where('user_id', $request->user()->id)->where('product_slug', $slug)->delete()) throw ValidationException::withMessages(['wishlist' => 'This item is no longer in your wishlist.']);
        }, 'Item removed from wishlist.');
    }

    public function toggle(Request $request, string $slug)
    {
        return $this->actions->action($request, function () use ($request, $slug) {
            return DB::transaction(function () use ($request, $slug) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $saved = WishlistItem::where('user_id', $request->user()->id)->where('product_slug', $slug)->lockForUpdate()->first();
                if ($saved) { $saved->delete(); return 'Item removed from wishlist.'; }
                $product = $this->product($slug);
                WishlistItem::create(['user_id' => $request->user()->id, 'product_slug' => $slug, 'product_name' => $product['name'], 'price' => $product['price'], 'image' => $product['image']]);
                return $product['name'].' added to your wishlist!';
            }, 3);
        }, 'Wishlist updated.');
    }

    public function moveToCart(Request $request, string $slug)
    {
        return $this->actions->action($request, function () use ($request, $slug) {
            return DB::transaction(function () use ($request, $slug) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $saved = WishlistItem::where('user_id', $request->user()->id)->where('product_slug', $slug)->lockForUpdate()->first();
                if (!$saved) throw ValidationException::withMessages(['wishlist' => 'This item is no longer in your wishlist.']);
                $product = $this->product($slug);
                if (count($product['variants']) > 1) {
                    $message = 'Please select an available variation before adding to cart.';
                    if ($request->expectsJson()) return response()->json(['success' => false, 'message' => $message, 'errors' => ['variant_id' => [$message]], 'code' => 'variant_required'], 422);
                    return redirect()->route('buyer.product', $slug)->with('info', $message);
                }
                $this->checkout->addCartItem($request->user(), $slug, (int) $product['variant_id'], 1);
                $saved->delete();
            }, 3);
        }, 'Item moved to your cart!', 'buyer.cart');
    }
}
