<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Services\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, ProductCatalogService $catalog): View
    {
        $products = $catalog->sellerProducts($this->sellerFor($request));

        return view('seller.products', [
            'products' => $products ?: $request->session()->get('seller_products', []),
            'databaseProducts' => $products !== [],
        ]);
    }

    public function create(Request $request): View
    {
        $this->sellerFor($request);

        return view('seller.products-create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request, ProductCatalogService $catalog): RedirectResponse
    {
        $catalog->create($this->sellerFor($request), $this->validated($request));

        return redirect()
            ->route('seller.products')
            ->with('success', 'Product added successfully.');
    }

    public function edit(Request $request, string $product, ProductCatalogService $catalog): View
    {
        $seller = $this->sellerFor($request);
        $databaseProduct = $seller->products()
            ->with(['category', 'images', 'variants'])
            ->find($product);

        if (! $databaseProduct) {
            $legacyProduct = $request->session()->get("seller_products.{$product}");
            abort_unless($legacyProduct, 404);

            return view('seller.products-edit', [
                'product' => $legacyProduct,
                'categories' => $this->categories(),
                'legacyProduct' => true,
            ]);
        }

        return view('seller.products-edit', [
            'product' => $catalog->sellerItem($databaseProduct),
            'categories' => $this->categories(),
            'legacyProduct' => false,
        ]);
    }

    public function update(
        Request $request,
        string $product,
        ProductCatalogService $catalog
    ): RedirectResponse {
        $seller = $this->sellerFor($request);
        $databaseProduct = $seller->products()->with('variants')->find($product);

        if (! $databaseProduct) {
            abort_unless($request->session()->has("seller_products.{$product}"), 404);

            return back()->withErrors([
                'product' => 'This legacy session product must be recreated as a database product before it can be updated.',
            ]);
        }

        $catalog->update($databaseProduct, $this->validated($request, $databaseProduct));

        return redirect()
            ->route('seller.products')
            ->with('success', 'Product updated successfully.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $variantId = $product?->variants->first()?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:3000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stock' => ['required', 'integer', 'min:0'],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:4294967'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:429496729'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:429496729'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:429496729'],
            'variation_name' => ['nullable', 'array'],
            'variation_name.*' => ['nullable', 'string', 'max:100'],
            'variation_value' => ['nullable', 'array'],
            'variation_value.*' => ['nullable', 'string', 'max:255'],
            'shipping_options' => ['nullable', 'array'],
            'shipping_options.*' => ['string', Rule::in(['suki_rider', 'logistics_partner'])],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }

    private function sellerFor(Request $request): Seller
    {
        return $request->user()
            ->sellers()
            ->where('status', 'approved')
            ->firstOrFail();
    }

    private function categories()
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }
}
