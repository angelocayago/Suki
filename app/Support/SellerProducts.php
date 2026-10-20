<?php

namespace App\Support;

use App\Models\Product;
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
        $imageRecords = $product->images->map(fn ($image) => [
            'id' => $image->id, 'url' => ProductImageUrl::url($image->path),
        ])->all();
        $images = array_values(array_filter(array_column($imageRecords, 'url')));
        $details = $product->seller_listing_data ?? [];
        foreach (['regular_price', 'sale_price'] as $field) {
            if (isset($details[$field])) {
                $details[$field] = (float) $details[$field];
            }
        }
        // A flag alone must never produce a fabricated discount in either layout.
        $details['on_sale'] = ! empty($details['on_sale'])
            && is_numeric($details['sale_price'] ?? null)
            && is_numeric($details['regular_price'] ?? null)
            && $details['sale_price'] > 0
            && $details['sale_price'] < $details['regular_price']
            && $variant && (int) round($details['sale_price'] * 100) === $variant->price_minor;

        return array_replace($details, [
            'id' => 'P'.str_pad($product->id, 4, '0', STR_PAD_LEFT),
            'name' => $product->name === '' ? null : $product->name,
            'description' => $product->description,
            'category' => SellerCategories::display($category?->parent?->name ?? $category?->name),
            'subcategory' => $category?->parent_id ? $category->name : null,
            'subcategory_id' => $category?->parent_id ? $category->id : null,
            'image' => $images[0] ?? null, 'images' => $images,
            'image_records' => $imageRecords,
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
            ->mapWithKeys(function ($product) {
                $listing = self::listing($product);

                return [$listing['id'] => $listing];
            })->all();
        // Unverified session-only rows cannot establish ownership. Preserve a
        // separate review copy, never import them into the persistent catalogue.
        if (session()->has('seller_products') && ! session()->has('seller_products_owner')) {
            session()->put('seller_products_legacy_review', session('seller_products'));
        }
        session()->put('seller_products', $products);
        session()->put('seller_products_owner', $seller->id);

        return $products;
    }

    public static function save(Seller $seller, array $data, int $categoryId, ?Product $product = null, array $imageChanges = []): Product
    {
        return DB::transaction(function () use ($seller, $data, $categoryId, $product, $imageChanges) {
            $creating = $product === null;
            if ($product) {
                $product = $seller->products()->lockForUpdate()->findOrFail($product->id);
            } else {
                $product = new Product(['seller_id' => $seller->id, 'slug' => Str::slug($data['name'] ?? 'product').'-'.Str::uuid()]);
            }
            // Only new listings receive the intended delivery default. Existing
            // metadata is historical and must survive ordinary edits verbatim.
            unset($data['shipping_options']);
            if ($creating) {
                $data['shipping_options'] = ['logistics_partner'];
            } elseif (array_key_exists('shipping_options', $product->seller_listing_data ?? [])) {
                $data['shipping_options'] = $product->seller_listing_data['shipping_options'];
            }
            $product->fill([
                'name' => $data['name'] ?? '', 'description' => $data['description'] ?? null,
                'category_id' => $categoryId, 'is_active' => $data['status'] === 'active',
                'seller_listing_data' => Arr::except($data, [
                    'id', 'name', 'description', 'category', 'subcategory', 'subcategory_id',
                    'image', 'images', 'image_records', 'replace_images', 'remove_image_ids', 'replacement_images', 'add_images', 'price', 'stock', 'sku', 'created_at', 'updated_at',
                ]),
            ])->save();

            $variants = $product->variants()->lockForUpdate()->get();
            // The existing seller form has one price/stock field. Never flatten
            // independently persisted variants when changing a category.
            if ($variants->count() > 1) {
                if ((int) $data['stock'] !== $variants->sum('stock') || (int) round($data['price'] * 100) !== $variants->first()->price_minor) {
                    throw ValidationException::withMessages(['stock' => 'This product has separate variants. Preserve its existing price, SKU and stock when updating its category.']);
                }
            } else {
                $variant = $variants->first();
                if (! $creating && ! $variant) {
                    throw ValidationException::withMessages(['sku' => 'This product has no active variant. Contact support to restore its SKU before editing.']);
                }
                $attributes = ['name' => $variant ? $variant->name : ($data['name'] ?? ''),
                    'price_minor' => (int) round(($data['price'] ?? 0) * 100), 'stock' => (int) $data['stock'],
                    'is_active' => $data['status'] === 'active'];
                if ($creating) {
                    SellerProductSku::createVariant($product, $attributes);
                } else {
                    // Ordinary seller edits never accept a replacement SKU.
                    $variant->fill($attributes)->save();
                }
            }
            $existing = $product->images()->lockForUpdate()->get();
            $remove = $imageChanges['remove'] ?? [];
            $replacements = $imageChanges['replacements'] ?? [];
            if (array_diff($remove, $existing->pluck('id')->all())) {
                throw ValidationException::withMessages(['remove_image_ids' => 'Select images belonging to this product.']);
            }
            if (array_diff(array_keys($replacements), $existing->pluck('id')->all())
                || array_intersect(array_keys($replacements), $remove)
                || (($imageChanges['replace'] ?? false) && $replacements)) {
                throw ValidationException::withMessages(['replacement_images' => 'Select existing product images and do not replace an image marked for removal.']);
            }
            $paths = $creating ? ($data['images'] ?? []) : ($imageChanges['paths'] ?? []);
            $remaining = ($imageChanges['replace'] ?? false) ? 0 : $existing->count() - count($remove);
            if ($remaining + count($paths) > 5) {
                throw ValidationException::withMessages(['add_images' => 'You can upload up to 5 product images.']);
            }
            foreach ($replacements as $imageId => $path) {
                abort_unless(ProductImageUrl::path($path) === $path && Storage::disk('public')->exists($path), 422);
                $existing->firstWhere('id', $imageId)->update(['path' => $path]);
            }
            if ($imageChanges['replace'] ?? false) {
                $product->images()->delete();
            } elseif ($remove) {
                $product->images()->whereIn('id', $remove)->delete();
            }
            // Detach only explicitly selected records; retain files for other references.
            $position = ($product->images()->max('position') ?? -1) + 1;
            foreach ($paths as $path) {
                abort_unless(ProductImageUrl::path($path) === $path && Storage::disk('public')->exists($path), 422);
                $product->images()->create(['path' => $path, 'position' => $position++]);
            }

            return $product;
        }, 3);
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
