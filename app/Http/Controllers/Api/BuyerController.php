<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\CheckoutService;
use App\Services\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuyerController extends Controller
{
    public function home(ProductCatalogService $catalog): JsonResponse
    {
        $categories = Category::query()->where('is_active', true)->orderBy('position')->orderBy('name')->limit(24)
            ->get(['id', 'name', 'slug'])->map(fn (Category $category) => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'image' => ''])->values();
        return response()->json(['success' => true, 'data' => [
            'hero_slides' => [], 'promotions' => [], 'benefits' => [], 'categories' => $categories, 'flash_deals' => [],
            'best_sellers' => $catalog->marketplacePage(['sort' => 'best_selling'], 8)->items(),
            'new_arrivals' => $catalog->marketplacePage(['sort' => 'newest'], 8)->items(),
        ]]);
    }

    public function products(Request $request, ProductCatalogService $catalog): JsonResponse
    {
        $filters = $request->validate([
            'q'=>['nullable','string','max:100'], 'category'=>['nullable','string','max:100'],
            'seller'=>['nullable','string','max:100'], 'in_stock'=>['nullable','in:0,1'],
            'min_price'=>['nullable','numeric','min:0'], 'max_price'=>['nullable','numeric','min:0','gte:min_price'],
            'sort'=>['nullable','in:newest,price_asc,price_desc,name,best_selling'],
            'page'=>['nullable','integer','min:1'], 'per_page'=>['nullable','integer','min:1','max:2147483647'],
        ]);
        return response()->json(['success' => true, 'data' => $catalog->marketplacePage(
            $filters,
            (int) ($filters['per_page'] ?? 24)
        )]);
    }

    public function product(ProductCatalogService $catalog, string $slug): JsonResponse
    {
        $product = $catalog->marketplaceProduct($slug);
        abort_if($product === null, 404, 'Product not found.');
        return response()->json(['success' => true, 'data' => $product]);
    }

    public function cart(Request $request, CheckoutService $checkout): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->cartPayload($checkout, $request->user())]);
    }

    public function wishlist(Request $request): JsonResponse
    {
        $items = WishlistItem::query()->where('user_id', $request->user()->id)->orderByDesc('created_at')
            ->get(['product_slug', 'product_name', 'price', 'image']);
        return response()->json(['success' => true, 'data' => ['slugs' => $items->pluck('product_slug')->values(), 'items' => $items]]);
    }

    public function toggleWishlist(Request $request, ProductCatalogService $catalog, string $slug): JsonResponse
    {
        $product = $catalog->marketplaceProduct($slug);
        abort_if($product === null, 404, 'Product not found.');
        $isWishlisted = DB::transaction(function () use ($request, $slug, $product): bool {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $item = WishlistItem::query()->where('user_id', $request->user()->id)->where('product_slug', $slug)->lockForUpdate()->first();
            if ($item) { $item->delete(); return false; }
            WishlistItem::query()->create(['user_id' => $request->user()->id, 'product_slug' => $slug, 'product_name' => $product['name'], 'price' => number_format((float) $product['price'], 2, '.', ''), 'image' => $product['image'] ?? null]);
            return true;
        }, 3);
        return response()->json(['success' => true, 'data' => ['is_wishlisted' => $isWishlisted]]);
    }

    public function addCartItem(Request $request, ProductCatalogService $catalog, CheckoutService $checkout): JsonResponse
    {
        $data = $request->validate(['product_variant_id' => ['required', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        $userId = (int) $request->user()->id;
        DB::transaction(function () use ($data, $userId, $catalog, $checkout): void {
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $variant = ProductVariant::query()->with(['product.category', 'product.seller.owner', 'product.images'])->lockForUpdate()->findOrFail($data['product_variant_id']);
            if (! $variant->product) {
                throw ValidationException::withMessages(['product_variant_id' => 'This product option is no longer available.']);
            }
            $marketplaceProduct = $catalog->marketplaceProduct($variant->product->slug);
            if (! collect($marketplaceProduct['variants'] ?? [])->contains(fn (array $item) => (int) $item['id'] === (int) $variant->id)) {
                throw ValidationException::withMessages(['product_variant_id' => 'This product option is no longer available.']);
            }
            $item = CartItem::query()->where('user_id', $userId)->where('product_variant_id', $variant->id)->lockForUpdate()->first();
            if ($item) { $quantity = (int) $item->quantity + (int) $data['quantity']; }
            else {
                $item = CartItem::query()->create(['user_id' => $userId, 'product_variant_id' => $variant->id, 'product_slug' => $variant->product->slug, 'product_name' => $variant->product->name, 'price' => number_format($variant->price_minor / 100, 2, '.', ''), 'image' => $variant->product->images->first()?->path, 'quantity' => 1]);
                $quantity = (int) $data['quantity'];
            }
            $checkout->updateCartQuantity($item, $quantity);
        }, 3);
        return response()->json(['success' => true, 'message' => 'Added to your Laravel cart.', 'data' => $this->cartPayload($checkout, $request->user())], 201);
    }

    public function updateCartItem(Request $request, CheckoutService $checkout, int $item): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        DB::transaction(function () use ($request, $item, $data, $checkout): void {
            $cartItem = CartItem::query()->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($item);
            $checkout->updateCartQuantity($cartItem, (int) $data['quantity']);
        }, 3);
        return response()->json(['success' => true, 'data' => $this->cartPayload($checkout, $request->user())]);
    }

    public function removeCartItem(Request $request, CheckoutService $checkout, int $item): JsonResponse
    {
        DB::transaction(fn () => CartItem::query()->where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($item)->delete(), 3);
        return response()->json(['success' => true, 'data' => $this->cartPayload($checkout, $request->user())]);
    }

    public function checkoutData(Request $request, CheckoutService $checkout): JsonResponse
    {
        $addresses = Address::query()->where('user_id', $request->user()->id)->orderByDesc('is_default')->orderBy('id')->get()->map(fn (Address $address) => [
            'id' => $address->id, 'name' => $address->recipient, 'phone' => $address->phone, 'province' => $address->province, 'municipality' => $address->city, 'barangay' => $address->barangay, 'street' => $address->line1, 'postal_code' => $address->postal_code, 'label' => $address->label, 'is_default' => (bool) $address->is_default,
        ])->values();
        return response()->json(['success' => true, 'data' => [...$this->cartPayload($checkout, $request->user()), 'addresses' => $addresses]]);
    }

    public function checkout(Request $request, CheckoutService $checkout): JsonResponse
    {
        $data = $request->validate(['address_id' => ['required', 'integer', 'min:1'], 'payment_method' => ['required', 'in:cod']]);
        $address = Address::query()->where('user_id', $request->user()->id)->findOrFail($data['address_id']);
        $order = $checkout->checkout($request->user(), ['recipient_name' => $address->recipient, 'recipient_phone' => $address->phone, 'province' => $address->province, 'region_code' => $address->region_code, 'province_code' => $address->province_code, 'municipality' => $address->city, 'city_municipality_code' => $address->city_municipality_code, 'barangay' => $address->barangay, 'barangay_code' => $address->barangay_code, 'street_address' => $address->line1, 'postal_code' => $address->postal_code, 'address_label' => $address->label, 'source_address_id' => $address->id], $data['payment_method']);
        return response()->json(['success' => true, 'message' => 'Your order was placed successfully.', 'data' => ['id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->getRawOriginal('status'), 'total_amount' => (float) $order->total_amount]], 201);
    }

    private function cartPayload(CheckoutService $checkout, User $user): array
    {
        $summary = $checkout->cartSummary($user);
        $summary['cart'] = array_values($summary['cart']);
        return $summary;
    }
}
