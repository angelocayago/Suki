<?php

namespace App\Services;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Admin\ProductModerationService;

class ProductCatalogService
{
    public function __construct(
        private readonly SellerInventoryService $inventory,
        private readonly ProductModerationService $moderation
    ) {}

    public function marketplaceProducts(): array
    {
        return $this->marketplaceQuery()
            ->orderByDesc('products.created_at')
            ->get()
            ->mapWithKeys(fn (Product $product) => [
                $product->slug => $this->marketplaceItem($product),
            ])
            ->all();
    }

    public function marketplaceProduct(string $slug): ?array
    {
        $product = $this->marketplaceQuery()
            ->where('products.slug', $slug)
            ->first();

        return $product ? $this->marketplaceItem($product) : null;
    }

    public function sellerProducts(Seller $seller): array
    {
        return $seller->products()
            ->with(['category', 'images', 'variants'])
            ->orderByDesc('created_at')
            ->get()
            ->mapWithKeys(fn (Product $product) => [
                $product->id => $this->sellerItem($product),
            ])
            ->all();
    }

    public function create(Seller $seller, array $data): Product
    {
        return DB::transaction(function () use ($seller, $data) {
            $product = $seller->products()->create([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'],
                'shipping_options' => $data['shipping_options'] ?? [],
                'is_active' => $data['status'] === 'active',
                'moderation_status' => ProductModerationStatus::Pending->value,
            ]);

            $product->variants()->create($this->variantAttributes($data, $product));
            $this->storeImages($product, $data['images'] ?? []);
            $this->moderation->recordInitialSubmission($product, $seller->owner);

            return $product->fresh(['category', 'images', 'variants']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $variant = $product->variants()->orderBy('id')->first();
            $before = $this->materialFingerprint($product, $variant);
            $hasNewImages = collect($data['images'] ?? [])->contains(
                fn ($image) => $image instanceof UploadedFile
            );

            $product->update([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'description' => $data['description'],
                'shipping_options' => $data['shipping_options'] ?? [],
                'is_active' => $data['status'] === 'active',
            ]);

            if ($variant) {
                $attributes = $this->variantAttributes($data, $product, $variant);
                $stock = $attributes['stock'];
                unset($attributes['stock']);

                $variant->update($attributes);
                $this->inventory->setStock($product->seller, $variant->id, $stock);
            } else {
                $product->variants()->create($this->variantAttributes($data, $product));
            }

            $this->storeImages($product, $data['images'] ?? []);

            $fresh = $product->fresh(['category', 'images', 'variants', 'seller']);
            if ($hasNewImages || $before !== $this->materialFingerprint($fresh, $fresh->variants->first())) {
                $this->moderation->returnToPendingAfterMaterialEdit($fresh, $fresh->seller->owner);
                $fresh = $fresh->fresh(['category', 'images', 'variants']);
            }

            return $fresh;
        });
    }

    public function sellerItem(Product $product): array
    {
        $variant = $product->variants->first();
        $options = $variant?->options ?? [];

        return [
            'id' => $product->id,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'category' => $product->category?->name ?? 'Uncategorized',
            'brand' => $product->brand,
            'description' => $product->description,
            'price' => ($variant?->price_minor ?? 0) / 100,
            'stock' => $variant?->stock ?? 0,
            'sku' => $variant?->sku,
            'status' => $product->is_active && ($variant?->is_active ?? false) ? 'active' : 'inactive',
            'moderation_status' => $product->moderation_status?->value ?? ProductModerationStatus::Pending->value,
            'moderation_reason' => $product->moderation_reason,
            'weight' => $variant?->weight_grams ? $variant->weight_grams / 1000 : null,
            'length' => $variant?->length_mm ? $variant->length_mm / 10 : null,
            'width' => $variant?->width_mm ? $variant->width_mm / 10 : null,
            'height' => $variant?->height_mm ? $variant->height_mm / 10 : null,
            'variation_names' => array_keys($options),
            'variation_values' => array_values($options),
            'shipping_options' => $product->shipping_options ?? [],
            'sold' => 0,
            'image' => $this->imageUrl($product->images->first()?->path),
            'images' => $product->images->map(fn ($image) => $this->imageUrl($image->path))->all(),
            'created_at' => $product->created_at?->toDateTimeString(),
            'updated_at' => $product->updated_at?->toDateTimeString(),
        ];
    }

    private function marketplaceQuery()
    {
        return Product::query()
            ->with(['category', 'images', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->where('products.is_active', true)
            ->where('products.moderation_status', ProductModerationStatus::Approved->value)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->whereHas('variants', fn ($query) => $query->where('is_active', true))
            ->whereHas('seller', function ($query) {
                $query->where('status', 'approved')
                    ->whereHas('owner', function ($owner) {
                        $owner->where('status', 'active')->where('is_suspended', false);
                    });
            });
    }

    private function marketplaceItem(Product $product): array
    {
        $variant = $product->variants->first();
        $images = $product->images
            ->map(fn ($image) => $this->imageUrl($image->path))
            ->filter()
            ->values()
            ->all();
        $fallbackImage = 'https://placehold.co/800x800?text='.rawurlencode($product->name);
        $images = $images ?: [$fallbackImage];

        return [
            'id' => $product->id,
            'source' => 'database',
            'variant_id' => $variant?->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => ($variant?->price_minor ?? 0) / 100,
            'old_price' => ($variant?->price_minor ?? 0) / 100,
            'discount' => '0%',
            'rating' => 0,
            'ratings' => 0,
            'sold' => 0,
            'category' => $product->category?->name ?? 'Uncategorized',
            'stock' => $variant?->stock ?? 0,
            'image' => $images[0],
            'images' => $images,
            'colors' => array_values((array) ($variant?->options['Color'] ?? [])),
            'seller' => $product->seller?->name ?? 'SUKI Seller',
            'seller_rating' => 'New',
            'seller_products' => $product->seller?->products()->count() ?? 0,
            'description' => $product->description ?? '',
            'features' => [],
        ];
    }

    private function variantAttributes(array $data, Product $product, ?ProductVariant $variant = null): array
    {
        $options = [];
        foreach ($data['variation_name'] ?? [] as $index => $name) {
            $name = trim((string) $name);
            $value = trim((string) ($data['variation_value'][$index] ?? ''));
            if ($name !== '' && $value !== '') {
                $options[$name] = $value;
            }
        }

        return [
            'sku' => $data['sku'] ?: ($variant?->sku ?? $this->uniqueSku($product)),
            'name' => $variant?->name ?? 'Default',
            'options' => $options ?: null,
            'price_minor' => (int) round(((float) $data['price']) * 100),
            'stock' => (int) $data['stock'],
            'weight_grams' => filled($data['weight'] ?? null) ? (int) round($data['weight'] * 1000) : null,
            'length_mm' => filled($data['length'] ?? null) ? (int) round($data['length'] * 10) : null,
            'width_mm' => filled($data['width'] ?? null) ? (int) round($data['width'] * 10) : null,
            'height_mm' => filled($data['height'] ?? null) ? (int) round($data['height'] * 10) : null,
            'is_active' => $data['status'] === 'active',
        ];
    }

    private function materialFingerprint(Product $product, ?ProductVariant $variant): array
    {
        return [
            'name' => $product->name,
            'category_id' => (int) $product->category_id,
            'brand' => $product->brand,
            'description' => $product->description,
            'shipping_options' => $product->shipping_options ?? [],
            'variant' => $variant ? [
                'sku' => $variant->sku,
                'name' => $variant->name,
                'options' => $variant->options,
                'price_minor' => $variant->price_minor,
                'weight_grams' => $variant->weight_grams,
                'length_mm' => $variant->length_mm,
                'width_mm' => $variant->width_mm,
                'height_mm' => $variant->height_mm,
            ] : null,
        ];
    }

    private function storeImages(Product $product, array $images): void
    {
        foreach ($images as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $image->store('products/'.$product->id, 'public'),
                'position' => (int) $product->images()->max('position') + 1,
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueSku(Product $product): string
    {
        do {
            $sku = 'SUKI-'.$product->id.'-'.Str::upper(Str::random(6));
        } while (ProductVariant::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : Storage::disk('public')->url($path);
    }
}
