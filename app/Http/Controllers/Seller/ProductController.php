<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Support\SellerAccount;
use App\Support\SellerCategories;
use App\Support\SellerProducts;
use App\Support\SellerProductVariants;
use App\Support\SellerStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private function validatePaymentInput(Request $request): void
    {
        // Availability is informational here; payment choice belongs to checkout.
        // A submitted COD value does not become a product-level setting.
        $request->validate([
            'payment_method' => 'sometimes|nullable|string|in:cod',
            'payment_methods' => 'sometimes|array|max:1',
            'payment_methods.*' => 'required|string|in:cod',
        ]);
    }

    private function saleRules(Request $request): array
    {
        // Unchecked HTML checkboxes are omitted, including submissions without JS.
        $request->mergeIfMissing(['on_sale' => false]);

        return [
            'on_sale' => 'required|boolean',
            'sale_price' => [Rule::excludeIf(! $request->boolean('on_sale')), 'bail',
                'required', 'numeric', 'decimal:0,2', 'gt:0', 'lt:price', 'max:42949672.95'],
        ];
    }

    private function pricing(Request $request, bool $variable = false): array
    {
        if ($variable) {
            return ['regular_price' => null, 'sale_price' => null, 'on_sale' => false, 'price' => null];
        }
        $regular = $request->filled('price') ? (float) $request->price : null;
        $sale = $request->boolean('on_sale') ? (float) $request->sale_price : null;

        return ['regular_price' => $regular, 'sale_price' => $sale,
            'on_sale' => $request->boolean('on_sale'), 'price' => $sale ?? $regular];
    }

    public function create()
    {
        return view('seller.products-create', $this->options(SellerAccount::current()));
    }

    private function options(Seller $seller): array
    {
        $main = SellerCategories::main($seller);

        return ['sellerCategory' => SellerCategories::display($seller->business_category),
            'productSubcategories' => SellerCategories::children($seller, $main),
            'categoryAvailable' => $main !== null];
    }

    private function category(Request $request, Seller $seller, bool $draft): array
    {
        $main = SellerCategories::main($seller);
        $children = SellerCategories::children($seller, $main);
        if (! $main || $children->isEmpty()) {
            throw ValidationException::withMessages(['subcategory' => 'Your assigned business category has no supported subcategories. Contact support; your category has not been changed.']);
        }
        $request->validate(['subcategory' => [$draft ? 'nullable' : 'required', 'integer', Rule::in($children->pluck('id')->all())]],
            ['subcategory.in' => 'Select a subcategory under your assigned business category.']);

        return [$main, $children->firstWhere('id', (int) $request->subcategory)];
    }

    public function store(Request $request)
    {
        $seller = SellerAccount::current();
        $sellerCategory = $seller->business_category;
        $isDraft = $request->input('submit_action') === 'draft';
        $required = $isDraft ? 'nullable' : 'required';
        [$main, $child] = $this->category($request, $seller, $isDraft);
        $this->validatePaymentInput($request);
        $variationData = SellerProductVariants::input($request);
        $variable = $variationData['product_type'] === 'variable';

        $saleRules = $this->saleRules($request);
        $request->validate([
            'name' => "{$required}|string|max:150",
            'description' => "{$required}|string|max:3000",
            'price' => $variable ? 'exclude' : [Rule::requiredIf($request->boolean('on_sale')), $required, 'numeric', 'min:0', 'max:42949672.95'],
            ...$saleRules,
            'stock' => $variable ? 'exclude' : "{$required}|integer|min:0|max:4294967295",
            'low_stock_threshold' => 'nullable|integer|min:0',
            'brand' => 'nullable|string|max:150',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'status' => $isDraft ? 'nullable|in:active,inactive' : 'required|in:active,inactive',
            'images' => $isDraft ? 'nullable|array|max:5' : 'required|array|min:1|max:5',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'additional_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'product_video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:10240',
            'has_variations' => 'nullable|boolean',
            'variation_name' => 'nullable|array',
            'variation_name.*' => 'nullable|string|max:100',
            'variation_value' => 'nullable|array',
            'variation_value.*' => 'nullable|string|max:255',
        ]);

        $newPaths = [];
        try {
            $imageUrls = collect($request->file('images', []))
                ->map(function ($image) use (&$newPaths) {
                    $path = $image->store('products', 'public');

                    $newPaths[] = $path;

                    if (! $path) {
                        throw new \RuntimeException('The product image could not be saved.');
                    }

                    return $path;
                })
                ->all();

            $additionalImageUrl = $request->hasFile('additional_image')
                ? Storage::disk('public')->url(
                    $newPaths[] = $request->file('additional_image')->store('products', 'public')
                )
                : null;

            $productVideoUrl = $request->hasFile('product_video')
                ? Storage::disk('public')->url(
                    $newPaths[] = $request->file('product_video')->store('products/videos', 'public')
                )
                : null;

            $data = [
                'name' => $request->name,
                'category' => $sellerCategory,
                'subcategory' => $child?->name,
                'brand' => $request->brand,
                'description' => $request->description,
                'image' => $imageUrls[0] ?? null,
                'images' => $imageUrls,
                'additional_image' => $additionalImageUrl,
                'product_video' => $productVideoUrl,

                ...$this->pricing($request, $variable),
                'stock' => (int) $request->stock,
                'low_stock_threshold' => (int) ($request->input('low_stock_threshold') ?? SellerStock::threshold()),

                'status' => $isDraft ? 'draft' : $request->status,

                'weight' => $request->weight,
                'length' => $request->length,
                'width' => $request->width,
                'height' => $request->height,

                'has_variations' => $request->boolean('has_variations'),
                'variation_names' => $request->boolean('has_variations')
                    ? array_values(array_filter($request->variation_name ?? []))
                    : [],
                'variation_values' => $request->boolean('has_variations')
                    ? array_values(array_filter($request->variation_value ?? []))
                    : [],

                'sold' => 0,

                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];

            $data = array_replace($data, $variationData);

            SellerProducts::save($seller, $data, $child?->id ?? $main->id);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPaths);
            throw $exception;
        }
        SellerProducts::sync($seller);

        return redirect()
            ->route('seller.products')
            ->with(
                'success',
                $isDraft ? 'Product saved as a draft.' : 'Product added successfully.'
            );

    }

    public function edit(string $id)
    {
        $seller = SellerAccount::current();
        $product = SellerProducts::owned($seller, $id);
        $options = $this->options($seller);
        $options['legacyCategory'] = $product->category_id !== SellerCategories::main($seller)?->id
            && ! $options['productSubcategories']->contains('id', $product->category_id);

        return view('seller.products-edit', $options + ['product' => SellerProducts::listing($product)]);
    }

    public function update(Request $request, string $id)
    {
        $seller = SellerAccount::current();
        $product = SellerProducts::owned($seller, $id);
        $draft = $request->input('status') === 'draft';
        [$main, $child] = $this->category($request, $seller, $draft);
        $this->validatePaymentInput($request);
        $variationData = SellerProductVariants::input($request, $product);
        $variable = $variationData['product_type'] === 'variable';
        // A legacy product must receive an explicit supported selection even as
        // a draft; never silently remap an existing category to the main parent.
        if (! $child && $product->category_id !== $main->id) {
            throw ValidationException::withMessages(['subcategory' => 'Select a supported subcategory to explicitly update this product classification.']);
        }
        $required = $draft ? 'nullable' : 'required';
        $saleRules = $this->saleRules($request);
        $validated = $request->validate([
            'name' => "$required|string|max:150", 'description' => "$required|string|max:3000",
            'price' => $variable ? 'exclude' : [Rule::requiredIf($request->boolean('on_sale')), $required, 'numeric', 'min:0', 'max:42949672.95'],
            ...$saleRules,
            'stock' => $variable ? 'exclude' : "$required|integer|min:0|max:4294967295",
            'status' => 'required|in:active,inactive,draft',
            'brand' => 'nullable|string|max:150', 'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0', 'width' => 'nullable|numeric|min:0', 'height' => 'nullable|numeric|min:0',
            'variation_name' => 'nullable|array', 'variation_name.*' => 'nullable|string|max:100',
            'variation_value' => 'nullable|array', 'variation_value.*' => 'nullable|string|max:255',
            'replace_images' => 'sometimes|boolean',
            'images' => [Rule::requiredIf($request->boolean('replace_images')), 'nullable', 'array', 'min:1', 'max:5'],
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_image_ids' => 'sometimes|array',
            'remove_image_ids.*' => 'required|integer|distinct',
            'replacement_images' => 'sometimes|array|max:5',
            'replacement_images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'add_images' => 'sometimes|array|max:5',
            'add_images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $data = array_replace(SellerProducts::listing($product), $validated,
            ['category' => $seller->business_category, 'subcategory' => $child?->name], $this->pricing($request, $variable), $variationData);
        foreach (['variation_name' => 'variation_names', 'variation_value' => 'variation_values'] as $input => $field) {
            if ($request->exists($input)) {
                $data[$field] = $validated[$input] ?? [];
            }
            unset($data[$input]);
        }
        $newPaths = [];
        try {
            $replacements = [];
            foreach ($request->file('replacement_images', []) as $imageId => $image) {
                if (! ctype_digit((string) $imageId)) {
                    throw ValidationException::withMessages(['replacement_images' => 'Select an image belonging to this product.']);
                }
                $path = $image->store('products', 'public');
                if (! $path) {
                    throw new \RuntimeException('The product image could not be saved.');
                }
                $replacements[$imageId] = $path;
                $newPaths[] = $path;
            }
            $addedPaths = [];
            foreach ($request->file('add_images', []) as $image) {
                $path = $image->store('products', 'public');
                if (! $path) {
                    throw new \RuntimeException('The product image could not be saved.');
                }
                $addedPaths[] = $path;
                $newPaths[] = $path;
            }
            $allPaths = [];
            if ($request->boolean('replace_images')) {
                foreach ($request->file('images', []) as $image) {
                    $path = $image->store('products', 'public');
                    if (! $path) {
                        throw new \RuntimeException('The product image could not be saved.');
                    }
                    $newPaths[] = $path;
                    $allPaths[] = $path;
                }
            }
            SellerProducts::save($seller, $data, $child?->id ?? $main->id, $product, [
                'replace' => $request->boolean('replace_images'), 'paths' => array_merge($allPaths, $addedPaths),
                'replacements' => $replacements,
                'remove' => $validated['remove_image_ids'] ?? [],
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPaths);
            throw $exception;
        }
        SellerProducts::sync($seller);

        return redirect()->route('seller.products')->with('success', 'Product updated successfully.');
    }
}
