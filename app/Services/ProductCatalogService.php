<?php

namespace App\Services;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ->withSum($this->completedSalesRelation(), 'quantity')
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

    /**
     * Database-backed buyer catalog. Query parameters are filters only; all
     * marketplace visibility is enforced by marketplaceQuery().
     */
    public function marketplacePage(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->marketplaceQuery()->withSum($this->completedSalesRelation(), 'quantity');

        if (filled($filters['q'] ?? null)) {
            $term = trim((string) $filters['q']);
            $query->where(fn ($q) => $q->where('products.name', 'like', "%{$term}%")->orWhere('products.description', 'like', "%{$term}%"));
        }
        if (filled($filters['category'] ?? null)) {
            $category = Category::query()->where('id', $filters['category'])->orWhere('slug', $filters['category'])->first();
            if (! $category) $query->whereRaw('1 = 0');
            else $query->whereIn('products.category_id', $this->categoryTreeIds($category->id));
        }
        if (filled($filters['seller'] ?? null)) {
            $query->whereHas('seller', fn ($q) => $q->where('id', $filters['seller'])->orWhere('slug', $filters['seller']));
        }
        if (($filters['in_stock'] ?? false) === '1') $query->whereHas('variants', fn ($q) => $q->where('is_active', true)->where('stock', '>', 0));
        if (isset($filters['min_price']) && $filters['min_price'] !== '') $query->whereHas('variants', fn ($q) => $q->where('is_active', true)->where('price_minor', '>=', (int) round(((float) $filters['min_price']) * 100)));
        if (isset($filters['max_price']) && $filters['max_price'] !== '') $query->whereHas('variants', fn ($q) => $q->where('is_active', true)->where('price_minor', '<=', (int) round(((float) $filters['max_price']) * 100)));

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderByRaw('(select min(price_minor) from product_variants where product_variants.product_id = products.id and is_active = 1) asc'),
            'price_desc' => $query->orderByRaw('(select min(price_minor) from product_variants where product_variants.product_id = products.id and is_active = 1) desc'),
            'name' => $query->orderBy('products.name'),
            'best_selling' => $query->orderByDesc('order_items_sum_quantity'),
            default => $query->orderByDesc('products.created_at'),
        };

        return $query->paginate(min(max($perPage, 1), 48))
            ->withQueryString()
            ->through(fn (Product $product) => $this->marketplaceItem($product));
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
            $this->saveAdditionalVariants($product, $data['variants'] ?? []);
            $this->storeImages($product, $data['images'] ?? [], $data['image_colors'] ?? []);
            $this->moderation->recordInitialSubmission($product, $seller->owner);

            return $product->fresh(['category', 'images', 'variants']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $variant = $product->variants()->orderBy('id')->first();
            $before = $this->materialFingerprint($product);
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

            $this->saveAdditionalVariants($product, $data['variants'] ?? []);

            $this->storeImages($product, $data['images'] ?? [], $data['image_colors'] ?? []);

            $fresh = $product->fresh(['category', 'images', 'variants', 'seller']);
            if ($hasNewImages || $before !== $this->materialFingerprint($fresh)) {
                $this->moderation->returnToPendingAfterMaterialEdit($fresh, $fresh->seller->owner);
                $fresh = $fresh->fresh(['category', 'images', 'variants']);
            }

            return $fresh;
        });
    }

    public function archive(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $locked->update(['is_active' => false]);
            $locked->delete();
        }, 3);
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
            'variants' => $product->variants->skip(1)->map(fn (ProductVariant $additional) => [
                'id' => $additional->id,
                'name' => $additional->name,
                'sku' => $additional->sku,
                'price' => $additional->price_minor / 100,
                'stock' => $additional->stock,
                'status' => $additional->is_active ? 'active' : 'inactive',
                'options' => $additional->options ?? [],
            ])->values()->all(),
            'shipping_options' => $product->shipping_options ?? [],
            'sold' => (int) ($product->order_items_sum_quantity ?? 0),
            'image' => $this->imageUrl($product->images->first()?->path),
            'images' => $product->images->map(fn ($image) => $this->imageUrl($image->path))->all(),
            'created_at' => $product->created_at?->toDateTimeString(),
            'updated_at' => $product->updated_at?->toDateTimeString(),
        ];
    }

    private function marketplaceQuery()
    {
        return Product::query()
            ->with(['category', 'images', 'seller' => fn ($query) => $query->withCount('visibleReviews')->withAvg('visibleReviews', 'rating'), 'variants' => fn ($query) => $query->where('is_active', true)])
            ->withCount('visibleReviews')
            ->withAvg('visibleReviews', 'rating')
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

    private function categoryTreeIds(int $rootId): array
    {
        $ids = [$rootId]; $frontier = [$rootId];
        while ($frontier) { $frontier = Category::query()->whereIn('parent_id', $frontier)->pluck('id')->all(); $ids = [...$ids, ...$frontier]; }
        return $ids;
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
            'rating' => round((float) ($product->visible_reviews_avg_rating ?? 0), 1),
            'ratings' => $product->visible_reviews_count,
            'sold' => (int) ($product->order_items_sum_quantity ?? 0),
            'category' => $product->category?->name ?? 'Uncategorized',
            'stock' => $variant?->stock ?? 0,
            'image' => $images[0],
            'images' => $images,
            'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => $variant->price_minor / 100,
                'stock' => $variant->stock,
                'is_active' => $variant->is_active,
                'options' => $variant->options ?? [],
            ])->values()->all(),
            'colors' => $product->variants->map(fn (ProductVariant $variant) => $variant->options['Color'] ?? null)->filter()->unique()->values()->all(),
            'sizes' => $product->variants->map(fn (ProductVariant $variant) => $variant->options['Size'] ?? null)->filter()->unique()->values()->all(),
            'media_by_color' => $product->images->filter(fn ($image) => filled($image->color))->groupBy('color')->map(fn ($images) => $images->map(fn ($image) => $this->imageUrl($image->path))->filter()->values()->all())->all(),
            'seller' => $product->seller?->name ?? 'SUKI Seller',
            'seller_slug' => $product->seller?->slug,
            'seller_logo' => $this->imageUrl($product->seller?->logo_path),
            'seller_rating' => $product->seller?->visible_reviews_count ? number_format((float) $product->seller->visible_reviews_avg_rating, 1).'/5' : 'New',
            'seller_products' => $product->seller?->products()
                ->where('is_active', true)
                ->where('moderation_status', ProductModerationStatus::Approved->value)
                ->count() ?? 0,
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

    private function materialFingerprint(Product $product): array
    {
        return [
            'name' => $product->name,
            'category_id' => (int) $product->category_id,
            'brand' => $product->brand,
            'description' => $product->description,
            'shipping_options' => $product->shipping_options ?? [],
            'variants' => $product->variants->sortBy('id')->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'options' => $variant->options,
                'price_minor' => $variant->price_minor,
                'weight_grams' => $variant->weight_grams,
                'length_mm' => $variant->length_mm,
                'width_mm' => $variant->width_mm,
                'height_mm' => $variant->height_mm,
                'is_active' => $variant->is_active,
            ])->values()->all(),
        ];
    }

    private function saveAdditionalVariants(Product $product, array $variants): void
    {
        $primaryVariantId = $product->variants()->orderBy('id')->value('id');

        foreach ($variants as $data) {
            if (filled($data['id'] ?? null) && (int) $data['id'] === (int) $primaryVariantId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'variants' => 'The default variant cannot be submitted as an additional variant.',
                ]);
            }

            $variant = filled($data['id'] ?? null)
                ? $product->variants()->whereKey($data['id'])->lockForUpdate()->firstOrFail()
                : new ProductVariant(['product_id' => $product->id]);

            $sku = filled($data['sku'] ?? null)
                ? trim((string) $data['sku'])
                : ($variant->sku ?: $this->uniqueSku($product));

            $duplicate = ProductVariant::withTrashed()
                ->where('sku', $sku)
                ->when($variant->exists, fn ($query) => $query->whereKeyNot($variant->id))
                ->exists();

            if ($duplicate) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'variants' => "The variant SKU {$sku} is already in use.",
                ]);
            }

            $variant->fill([
                'sku' => $sku,
                'name' => trim((string) $data['name']),
                'price_minor' => (int) round(((float) $data['price']) * 100),
                'stock' => (int) $data['stock'],
                'is_active' => ($data['status'] ?? 'active') === 'active',
                'options' => $this->normalisedOptions($data['options'] ?? []),
            ]);
            $variant->save();
        }
    }

    private function storeImages(Product $product, array $images, array $imageColors = []): void
    {
        foreach ($images as $key => $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $image->store('products/'.$product->id, 'public'),
                'color' => filled($imageColors[$key] ?? null) ? trim((string) $imageColors[$key]) : null,
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

    private function normalisedOptions(array $options): ?array
    {
        $options = collect($options)->only(['Color', 'Size'])->map(fn ($value) => trim((string) $value))->filter()->all();
        return $options ?: null;
    }

    private function completedSalesRelation(): array
    {
        return ['orderItems as order_items_sum_quantity' => function ($query) {
            // A sale is visible as soon as checkout creates a placed SellerOrder.
            // Cancellation changes both the parent order and seller-order status, so
            // neither cancelled path can continue contributing to the catalog count.
            $query->whereHas('sellerOrder', fn ($sellerOrder) => $sellerOrder
                ->where('status', '!=', 'cancelled')
                ->whereHas('order', fn ($order) => $order->where('status', '!=', 'CANCELLED')));
        }];
    }
}
