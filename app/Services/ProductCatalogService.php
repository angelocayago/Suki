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
use App\Support\ProductOptions;

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
            ->withSum($this->completedSalesRelation(), 'quantity')
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
        // Standard SQL COALESCE keeps unsold products at zero on both databases,
        // including PostgreSQL where descending NULLs otherwise precede sales.
        $query = $this->marketplaceQuery()->withAggregate($this->completedSalesRelation(), DB::raw('COALESCE(SUM(quantity), 0)'));

        if (in_array((string) ($filters['rating'] ?? ''), ['1', '2', '3', '4', '5'], true)) {
            // Match the one-decimal average shown on cards, using the same visible reviews.
            $query->whereHas('visibleReviews', fn ($reviews) => $reviews->select('product_id')->groupBy('product_id')
                ->havingRaw('ROUND(AVG(rating), 1) '.((int) $filters['rating'] === 5 ? '=' : '>=').' ?', [(int) $filters['rating']]));
        }
        if (filled($filters['q'] ?? null)) {
            $term = trim((string) $filters['q']);
            $query->where(fn ($q) => $q->whereLike('products.name', "%{$term}%")->orWhereLike('products.description', "%{$term}%"));
        }
        if (!empty($filters['categories'])) {
            $roots = Category::query()->where('is_active', true)->whereIn('id', $filters['categories'])->pluck('id')->all();
            $query->whereIn('products.category_id', $this->categoryTreeIds($roots));
        } elseif (filled($filters['category'] ?? null)) {
            $categoryQuery = Category::query()->where('is_active', true)->where(function ($query) use ($filters) {
                $query->where('slug', (string) $filters['category']);
                if (ctype_digit((string) $filters['category'])) $query->orWhere('id', $filters['category']);
            });
            $category = $categoryQuery->first();
            if (! $category) $query->whereRaw('1 = 0');
            else $query->whereIn('products.category_id', $this->categoryTreeIds([$category->id]));
        }
        if (filled($filters['seller'] ?? null)) {
            $query->whereHas('seller', function ($q) use ($filters) {
                $q->where(function ($seller) use ($filters) {
                    $seller->where('slug', (string) $filters['seller']);
                    if (ctype_digit((string) $filters['seller'])) $seller->orWhere('id', $filters['seller']);
                });
            });
        }
        // One variant must satisfy the entire filter. Relation scopes also exclude
        // soft-deleted variants from matching, aggregation and card presentation.
        $eligible = function ($variants) use ($filters) {
            $variants->where('is_active', true);
            if (($filters['in_stock'] ?? '') === '1') $variants->where('stock', '>', 0);
            if (filled($filters['min_price'] ?? null)) $variants->where('price_minor', '>=', (int) round(((float) $filters['min_price']) * 100));
            if (filled($filters['max_price'] ?? null)) $variants->where('price_minor', '<=', (int) round(((float) $filters['max_price']) * 100));
        };
        $query->whereHas('variants', $eligible)
            ->with(['variants' => $eligible])
            ->withMin(['variants as catalog_price_minor' => $eligible], 'price_minor');

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('catalog_price_minor'),
            'price_desc' => $query->orderByDesc('catalog_price_minor'),
            'name' => $query->orderBy('products.name'),
            'best_selling' => $query->orderByDesc('order_items_sum_quantity'),
            default => $query->orderByDesc('products.created_at'),
        };

        return $query->orderByDesc('products.id')->paginate(min(max($perPage, 1), 48))
            ->withQueryString()
            ->through(fn (Product $product) => $this->marketplaceItem($product, $product->variants->sortBy([['price_minor', 'asc'], ['id', 'asc']])->first())
                + ['price_from' => $product->variants->pluck('price_minor')->unique()->count() > 1]);
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
        return $this->variantImageTransaction(function (array &$uploads) use ($seller, $data) {
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

            $primary = $product->variants()->create($this->variantAttributes($data, $product));
            $this->syncVariantImage($primary, $data['variant_image'] ?? null, false, $uploads);
            $this->saveAdditionalVariants($product, $data['variants'] ?? [], $uploads);
            $this->storeImages($product, $data['images'] ?? [], $data['image_colors'] ?? []);
            $this->syncVideo($product, $data['video'] ?? null, false);
            $this->moderation->recordInitialSubmission($product, $seller->owner);

            return $product->fresh(['category', 'images', 'variants']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return $this->variantImageTransaction(function (array &$uploads) use ($product, $data) {
            $variant = $product->variants()->orderBy('id')->first();
            $before = $this->materialFingerprint($product);
            $hasNewImages = collect($data['images'] ?? [])->contains(
                fn ($image) => $image instanceof UploadedFile
            );
            $hasNewVideo = ($data['video'] ?? null) instanceof UploadedFile;
            $removeVideo = (bool) ($data['remove_video'] ?? false);

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
                $this->syncVariantImage($variant, $data['variant_image'] ?? null, (bool) ($data['remove_variant_image'] ?? false), $uploads);
            } else {
                $primary = $product->variants()->create($this->variantAttributes($data, $product));
                $this->syncVariantImage($primary, $data['variant_image'] ?? null, false, $uploads);
            }

            $this->saveAdditionalVariants($product, $data['variants'] ?? [], $uploads);

            $this->storeImages($product, $data['images'] ?? [], $data['image_colors'] ?? []);
            $this->syncVideo($product, $data['video'] ?? null, $removeVideo);

            $fresh = $product->fresh(['category', 'images', 'variants', 'seller']);
            if ($hasNewImages || $hasNewVideo || $removeVideo || $before !== $this->materialFingerprint($fresh)) {
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
        $variant = $product->variants->sortBy('id')->first();
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
            'weight' => $variant?->weight_grams !== null ? $variant->weight_grams / 1000 : null,
            'length' => $variant?->length_mm ? $variant->length_mm / 10 : null,
            'width' => $variant?->width_mm ? $variant->width_mm / 10 : null,
            'height' => $variant?->height_mm ? $variant->height_mm / 10 : null,
            'variant_image' => $this->marketplaceImageUrl($variant?->image_path),
            'variation_names' => array_keys($options),
            'variation_values' => array_values($options),
            'variants' => $product->variants->sortBy('id')->skip(1)->map(fn (ProductVariant $additional) => [
                'id' => $additional->id,
                'name' => $additional->name,
                'sku' => $additional->sku,
                'price' => $additional->price_minor / 100,
                'stock' => $additional->stock,
                'status' => $additional->is_active ? 'active' : 'inactive',
                'options' => $additional->options ?? [],
                'weight' => $additional->weight_grams === null ? null : $additional->weight_grams / 1000,
                'image' => $this->marketplaceImageUrl($additional->image_path),
            ])->values()->all(),
            'shipping_options' => $product->shipping_options ?? [],
            'sold' => (int) ($product->order_items_sum_quantity ?? 0),
            'image' => $this->thumbnail($product),
            'images' => $product->images->map(fn ($image) => $this->imageUrl($image->path))->all(),
            'video' => $this->imageUrl($product->video_path),
            'video_mime' => $product->video_mime,
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

    private function categoryTreeIds(array $rootIds): array
    {
        if (!$rootIds) return [];
        $children = Category::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $seen = []; $frontier = $rootIds;
        while ($frontier) {
            $id = (int) array_pop($frontier);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            foreach ($children->get($id, collect()) as $child) $frontier[] = $child->id;
        }
        return array_keys($seen);
    }

    private function marketplaceItem(Product $product, ?ProductVariant $displayVariant = null): array
    {
        $variant = $displayVariant ?? $product->variants->sortBy('id')->first();
        $images = $product->images
            ->map(fn ($image) => $this->marketplaceImageUrl($image->path))
            ->filter()
            ->values()
            ->all();
        $fallbackImage = 'https://placehold.co/800x800?text='.rawurlencode($product->name);
        $images = $images ?: [$this->thumbnail($product) ?? $fallbackImage];

        return [
            'id' => $product->id,
            'source' => 'database',
            'variant_id' => $variant?->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => ($variant?->price_minor ?? 0) / 100,
            'old_price' => null,
            'discount' => null,
            'rating' => round((float) ($product->visible_reviews_avg_rating ?? 0), 1),
            'ratings' => $product->visible_reviews_count,
            'sold' => (int) ($product->order_items_sum_quantity ?? 0),
            'category' => $product->category?->name ?? 'Uncategorized',
            'stock' => $variant?->stock ?? 0,
            'image' => $images[0],
            'images' => $images,
            'video' => $this->marketplaceImageUrl($product->video_path),
            'video_mime' => $product->video_mime,
            'variants' => $product->variants->sortBy('id')->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->name,
                'price' => $variant->price_minor / 100,
                'image' => $this->marketplaceImageUrl($variant->image_path),
                'stock' => $variant->stock,
                'is_active' => $variant->is_active,
                'options' => $variant->options ?? [],
            ])->values()->all(),
            'colors' => $product->variants->map(fn (ProductVariant $variant) => $variant->options['Color'] ?? null)->filter()->unique()->values()->all(),
            'option_groups' => $product->variants->flatMap(fn (ProductVariant $variant) => array_keys($variant->options ?? []))
                ->unique()->mapWithKeys(fn ($name) => [$name => $product->variants->map(fn ($variant) => $variant->options[$name] ?? null)->filter(fn ($value) => $value !== null && $value !== '')->unique()->values()->all()])->all(),
            'sizes' => $product->variants->map(fn (ProductVariant $variant) => $variant->options['Size'] ?? null)->filter()->unique()->values()->all(),
            'media_by_color' => $product->images->filter(fn ($image) => filled($image->color))->groupBy('color')->map(fn ($images) => $images->map(fn ($image) => $this->marketplaceImageUrl($image->path))->filter()->values()->all())->all(),
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
            'sku' => filled($data['sku'] ?? null) ? $data['sku'] : ($variant?->sku ?? $this->uniqueSku($product)),
            'name' => $variant?->name ?? ($data['primary_variant_name'] ?? 'Default'),
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
                'image_path' => $variant->image_path,
                'price_minor' => $variant->price_minor,
                'weight_grams' => $variant->weight_grams,
                'length_mm' => $variant->length_mm,
                'width_mm' => $variant->width_mm,
                'height_mm' => $variant->height_mm,
                'is_active' => $variant->is_active,
            ])->values()->all(),
        ];
    }

    private function saveAdditionalVariants(Product $product, array $variants, array &$uploads): void
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
                'weight_grams' => array_key_exists('weight', $data)
                    ? (filled($data['weight']) ? (int) round((float) $data['weight'] * 1000) : null)
                    : $variant->weight_grams,
            ]);
            $variant->save();
            $this->syncVariantImage($variant, $data['image'] ?? null, (bool) ($data['remove_image'] ?? false), $uploads);
        }
    }

    /** Remove newly uploaded variant files if any later database operation fails. */
    private function variantImageTransaction(callable $callback): Product
    {
        $uploads = [];
        try {
            return DB::transaction(function () use ($callback, &$uploads) {
                return $callback($uploads);
            });
        } catch (\Throwable $error) {
            foreach ($uploads as $path) {
                // A failing after-commit callback must not delete committed media.
                if (! ProductVariant::withTrashed()->where('image_path', $path)->exists()) Storage::disk('public')->delete($path);
            }
            throw $error;
        }
    }

    private function syncVariantImage(ProductVariant $variant, mixed $image, bool $remove, array &$uploads): void
    {
        if (! $image instanceof UploadedFile && ! $remove) return;
        $oldPath = $variant->image_path;
        $directory = 'products/'.$variant->product_id.'/variants/'.$variant->id.'/';
        $newPath = null;
        if ($image instanceof UploadedFile) {
            $newPath = $image->store(rtrim($directory, '/'), 'public');
            if ($newPath) $uploads[] = $newPath;
            if (! $newPath || ! Storage::disk('public')->exists($newPath)) {
                throw new \RuntimeException('Variant image storage failed.');
            }
        }
        $variant->forceFill(['image_path' => $newPath])->save();
        // Only delete files generated inside this specific variant's directory.
        if ($oldPath && $oldPath !== $newPath && Str::startsWith($oldPath, $directory)
            && ! str_contains($oldPath, '..') && ! str_contains($oldPath, '\\')) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
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

    private function syncVideo(Product $product, mixed $video, bool $remove): void
    {
        if (! $video instanceof UploadedFile && ! $remove) {
            return;
        }

        $oldPath = $product->video_path;
        $newPath = null;
        $mime = null;

        if ($video instanceof UploadedFile) {
            $newPath = $video->store('products/'.$product->id.'/video', 'public');

            if (! $newPath || ! Storage::disk('public')->exists($newPath)) {
                throw new \RuntimeException('Product video storage failed.');
            }

            $mime = $video->getMimeType();
        }

        $product->forceFill([
            'video_path' => $newPath,
            'video_mime' => $mime,
        ])->save();

        if ($oldPath && $oldPath !== $newPath && ! Str::startsWith($oldPath, ['http://', 'https://'])) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
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

    /** Resolve a display image without changing product or media records. */
    public function thumbnail(Product $product): ?string
    {
        foreach ($product->images->sortBy(fn ($image) => [$image->position, $image->id]) as $image) {
            if ($url = $this->marketplaceImageUrl($image->path)) return $url;
        }
        $variants = $product->relationLoaded('thumbnailVariants') ? $product->thumbnailVariants : $product->variants;
        foreach ($variants->sortBy('id') as $variant) {
            if (! $variant->is_active || $variant->stock < 1 || $variant->deleted_at !== null) continue;
            if ($url = $this->marketplaceImageUrl($variant->image_path)) return $url;
        }
        return null;
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

    private function marketplaceImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }

    private function normalisedOptions(array $options): ?array
    {
        $options = ProductOptions::options($options);
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
