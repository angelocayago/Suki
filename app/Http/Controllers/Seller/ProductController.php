<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Seller;
use App\Services\ProductCatalogService;
use App\Services\SellerCategoryService;
use App\Support\SellerProducts;
use App\Support\ProductOptions;
use App\Services\SellerInventoryService;
use Illuminate\Support\Str;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, SellerProducts $listing, SellerCategoryService $categories): View
    {
        return view('seller.products', $listing->listing($this->sellerFor($request), $request, $categories));
    }

    public function create(Request $request, SellerCategoryService $categories): View
    {
        $seller = $this->sellerFor($request);
        $tokens = $request->session()->get('seller_product_tokens', []);
        $token = old('creation_token');
        if (! is_string($token) || ! isset($tokens[$token]) || $tokens[$token] !== 'pending') {
            $token = Str::random(40);
            $tokens[$token] = 'pending';
        }
        $request->session()->put('seller_product_tokens', array_slice($tokens, -20, null, true));

        return view('seller.products-create', [
            'creationToken' => $token,
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
        $token = $request->validate(['creation_token' => [$request->has('product_mode') ? 'required' : 'nullable', 'string', 'alpha_num', 'size:40']])['creation_token'] ?? null;
        if ($token) {
            $saved = $request->session()->get('seller_product_tokens.'.$token);
            if ($saved === null) throw ValidationException::withMessages(['product' => 'This form has expired. Reload Add Product before submitting.']);
            if ($saved !== 'pending') {
                $seller->products()->findOrFail($saved);
                return redirect()->route('seller.products')->with('success', 'This product was already submitted.');
            }
        }
        $data = $this->validated($request);
        $this->ensureAllowedCategory($seller, (int) $data['category_id'], $categories);
        try {
            $created = $catalog->create($seller, $data);
            if ($token) $request->session()->put('seller_product_tokens.'.$token, $created->id);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'sku' => 'A product identifier was already assigned. Please save again to generate a unique identifier.',
            ]);
        }

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
            'imageColors' => $databaseProduct->images->pluck('color')->all(),
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
        try {
            $catalog->update($databaseProduct, $data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'sku' => 'A product identifier was already assigned. Please review the variants and save again.',
            ]);
        }

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
        $variantId = $product?->variants->sortBy('id')->first()?->id;
        $universal = ! $product && $request->has('product_mode');
        $generated = $universal && $request->input('product_mode') === 'variants';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:3000'],
            'price' => [$generated ? 'nullable' : 'required', 'numeric', 'min:0', 'max:21474836.47', 'decimal:0,2'],
            'stock' => [$generated ? 'nullable' : 'required', 'integer', 'min:0', 'max:'.SellerInventoryService::MAX_STOCK],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:2147483.647', 'decimal:0,3'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:429496729'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:429496729'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:429496729'],
            'variation_name' => ['nullable', 'array'],
            'variation_name.*' => ['nullable', 'string', 'max:100'],
            'variation_value' => ['nullable', 'array'],
            'variation_value.*' => ['nullable', 'string', 'max:255'],
            'product_mode' => ['sometimes', Rule::in(['simple', 'variants'])],
            'option_groups' => ['nullable', 'array', 'max:3'],
            'option_groups.*' => ['array:name,values'],
            'option_groups.*.name' => ['nullable', 'string', 'max:40'],
            'option_groups.*.values' => ['nullable', 'string', 'max:2100'],
            'shipping_options' => ['prohibited'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_colors' => ['nullable', 'array', 'max:8'],
            'image_colors.*' => ['nullable', 'string', 'max:100'],
            'variants' => [$generated ? 'required' : 'nullable', 'array', 'max:50'],
            'variants.*' => ['array'],
            'variants.*.id' => [$universal ? 'prohibited' : 'nullable', 'integer', 'distinct'],
            'variants.*.name' => [$universal ? 'nullable' : 'required_with:variants', 'string', 'max:100'],
            'variants.*.sku' => ['nullable', 'string', 'max:50', 'distinct'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0', 'max:21474836.47', 'decimal:0,2'],
            'variants.*.stock' => ['required_with:variants', 'integer', 'min:0', 'max:'.SellerInventoryService::MAX_STOCK],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0', 'max:2147483.647', 'decimal:0,3'],
            'variants.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'variants.*.options' => ['nullable', 'array', 'max:3'],
            'variants.*.options.*' => ['nullable', 'string', 'max:100'],
        ]);

        if ($universal) $data = ProductOptions::creation($data);
        foreach ($data['variants'] ?? [] as $index => $row) {
            $data['variants'][$index]['options'] = ProductOptions::options($row['options'] ?? []);
        }

        // Shipping metadata is not a delivery selector. Preserve saved metadata;
        // new products use the catalog service's existing empty-array default.
        $data['shipping_options'] = $product?->shipping_options ?? [];
        $data['sku'] ??= null;

        return $data;
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
