<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use App\Services\ProductCatalogService;
use App\Services\SellerCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function create(Request $request, SellerCategoryService $categories): View
    {
        $seller = $this->sellerFor($request);

        return view('seller.products-create', [
            'categories' => $categories->allowedCategories($seller),
            'assignedCategory' => $seller->category,
        ]);
    }

    public function store(
        Request $request,
        ProductCatalogService $catalog,
        SellerCategoryService $categories
    ): RedirectResponse
    {
        $seller = $this->sellerFor($request);
        $data = $this->validated($request);
        $this->ensureAllowedCategory($seller, (int) $data['category_id'], $categories);
        $catalog->create($seller, $data);

        return redirect()
            ->route('seller.products')
            ->with('success', 'Product added successfully.');
    }

    public function edit(
        Request $request,
        string $product,
        ProductCatalogService $catalog,
        SellerCategoryService $categories
    ): View
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
                'categories' => $categories->allowedCategories($seller),
                'assignedCategory' => $seller->category,
                'legacyProduct' => true,
            ]);
        }

        $allowedCategories = $categories->allowedCategories($seller);
        return view('seller.products-edit', [
            'product' => $catalog->sellerItem($databaseProduct),
            'categories' => $allowedCategories,
            'assignedCategory' => $seller->category,
            'legacyProduct' => false,
        ]);
    }

    public function update(
        Request $request,
        string $product,
        ProductCatalogService $catalog,
        SellerCategoryService $categories
    ): RedirectResponse {
        $seller = $this->sellerFor($request);
        $databaseProduct = $seller->products()->with('variants')->find($product);

        if (! $databaseProduct) {
            abort_unless($request->session()->has("seller_products.{$product}"), 404);

            return back()->withErrors([
                'product' => 'This legacy session product must be recreated as a database product before it can be updated.',
            ]);
        }

        $data = $this->validated($request, $databaseProduct);
        $this->ensureAllowedCategory($seller, (int) $data['category_id'], $categories);
        $catalog->update($databaseProduct, $data);

        return redirect()
            ->route('seller.products')
            ->with('success', 'Product updated successfully.');
    }

    public function archive(
        Request $request,
        string $product,
        ProductCatalogService $catalog
    ): RedirectResponse {
        $seller = $this->sellerFor($request);
        $databaseProduct = $seller->products()->findOrFail($product);

        $catalog->archive($databaseProduct);

        return redirect()
            ->route('seller.products')
            ->with('success', 'Product archived successfully.');
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
            'image_colors' => ['nullable', 'array', 'max:8'],
            'image_colors.*' => ['nullable', 'string', 'max:100'],
            'variants' => ['nullable', 'array', 'max:50'],
            'variants.*.id' => ['nullable', 'integer', 'distinct'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:50', 'distinct'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0', 'max:99999999.99'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.options.Color' => ['nullable', 'string', 'max:100'],
            'variants.*.options.Size' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function sellerFor(Request $request): Seller
    {
        return $request->user()
            ->sellers()
            ->where('status', 'approved')
            ->firstOrFail();
    }

    private function ensureAllowedCategory(
        Seller $seller,
        int $categoryId,
        SellerCategoryService $categories,
    ): void
    {
        if (! $categories->allows($seller, $categoryId)) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category is outside your assigned category.',
            ]);
        }
    }
}
