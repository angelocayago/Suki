<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerProducts
{
    public static function owned(Seller $seller, string $id): Product
    {
        abort_unless(preg_match('/\A(?:P)?[0-9]+\z/', $id), 404);

        return $seller->products()->with(['category.parent', 'variants', 'images'])
            ->findOrFail((int) ltrim($id, 'P'));
    }

    public static function listing(Product $product): array
    {
        $variant = $product->variants->first();
        $category = $product->category;
        $images = $product->images->map(fn ($image) => Storage::disk('public')->url($image->path))->all();
        $details = $product->seller_listing_data ?? [];
        foreach (['regular_price', 'sale_price'] as $field) {
            if (isset($details[$field])) {
                $details[$field] = (float) $details[$field];
            }
        }

        return array_replace($details, [
            'id' => 'P'.str_pad($product->id, 4, '0', STR_PAD_LEFT),
            'name' => $product->name === '' ? null : $product->name,
            'description' => $product->description,
            'category' => SellerCategories::display($category?->parent?->name ?? $category?->name),
            'subcategory' => $category?->parent_id ? $category->name : null,
            'subcategory_id' => $category?->parent_id ? $category->id : null,
            'image' => $images[0] ?? null, 'images' => $images,
            'price' => $variant ? (float) ($variant->price_minor / 100) : null,
            'stock' => $product->variants->sum('stock'), 'sku' => $variant?->sku,
            'status' => ($product->seller_listing_data['status'] ?? null) === 'draft' ? 'draft' : ($product->is_active ? 'active' : 'inactive'),
            'created_at' => $product->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
        ]);
    }

    public static function sync(Seller $seller): array
    {
        $products = $seller->products()->with(['category.parent', 'variants', 'images'])->get()
            ->mapWithKeys(fn ($product) => [self::listing($product)['id'] => self::listing($product)])->all();
        // Unverified session-only rows cannot establish ownership. Preserve a
        // separate review copy, never import them into the persistent catalogue.
        if (session()->has('seller_products') && ! session()->has('seller_products_owner')) {
            session()->put('seller_products_legacy_review', session('seller_products'));
        }
        session()->put('seller_products', $products);
        session()->put('seller_products_owner', $seller->id);

        return $products;
    }

    public static function save(Seller $seller, array $data, int $categoryId, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($seller, $data, $categoryId, $product) {
            if ($product) {
                $product = $seller->products()->lockForUpdate()->findOrFail($product->id);
            } else {
                $product = new Product(['seller_id' => $seller->id, 'slug' => Str::slug($data['name'] ?? 'product').'-'.Str::uuid()]);
            }
            $product->fill([
                'name' => $data['name'] ?? '', 'description' => $data['description'] ?? null,
                'category_id' => $categoryId, 'is_active' => $data['status'] === 'active',
                'seller_listing_data' => Arr::except($data, [
                    'id', 'name', 'description', 'category', 'subcategory', 'subcategory_id',
                    'image', 'images', 'price', 'stock', 'sku', 'created_at', 'updated_at',
                ]),
            ])->save();

            $variants = $product->variants()->lockForUpdate()->get();
            // The existing seller form has one price/stock field. Never flatten
            // independently persisted variants when changing a category.
            if ($variants->count() > 1) {
                if ((int) $data['stock'] !== $variants->sum('stock') || (int) round($data['price'] * 100) !== $variants->first()->price_minor || $data['sku'] !== $variants->first()->sku) {
                    throw ValidationException::withMessages(['stock' => 'This product has separate variants. Preserve its existing price, SKU and stock when updating its category.']);
                }
            } else {
                $variant = $variants->first() ?? $product->variants()->make();
                $sku = $data['sku'] ?: ($variant->sku ?? 'S'.$seller->id.'-P'.$product->id);
                if (ProductVariant::withTrashed()->where('sku', $sku)->when($variant->exists, fn ($query) => $query->where('id', '!=', $variant->id))->exists()) {
                    throw ValidationException::withMessages(['sku' => 'This SKU is already in use.']);
                }
                $variant->fill(['sku' => $sku, 'name' => $variant->exists ? $variant->name : ($data['name'] ?? ''),
                    'price_minor' => (int) round(($data['price'] ?? 0) * 100), 'stock' => (int) $data['stock'],
                    'is_active' => $data['status'] === 'active'])->save();
            }
            // Edit currently has no image controls: leave its existing rows intact.
            if (! $product->images()->exists()) {
                foreach ($data['images'] ?? [] as $position => $url) {
                    $prefix = rtrim(Storage::disk('public')->url(''), '/').'/';
                    abort_unless(str_starts_with($url, $prefix), 422);
                    $product->images()->create(['path' => substr($url, strlen($prefix)), 'position' => $position]);
                }
            }

            return $product;
        });
    }

    public static function adjustStock(Seller $seller, string $id, int $amount): void
    {
        $product = self::owned($seller, $id);
        DB::transaction(function () use ($product, $amount) {
            $variants = $product->variants()->lockForUpdate()->get();
            abort_unless($variants->count() === 1, 409, 'Select the individual variant before changing stock.');
            $variant = $variants->first();
            $variant->update(['stock' => max(0, $variant->stock + $amount)]);
            $product->touch();
        });
        self::sync($seller);
    }
}
