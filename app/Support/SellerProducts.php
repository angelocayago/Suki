<?php

namespace App\Support;

use App\Models\Product;
use App\Services\SellerCategoryService;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Read-only Seller listing. ProductCatalogService remains the write authority. */
class SellerProducts
{
    // Same low-stock boundary as the current Inventory page.
    private const LOW_STOCK = 10;

    public function listing(Seller $seller, Request $request, SellerCategoryService $categories): array
    {
        $filterCategories = $categories->allowedCategories($seller);
        $criteria = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'category' => ['nullable', 'integer', Rule::in($filterCategories->pluck('id')->all())],
            'stock' => ['nullable', Rule::in(['in_stock', 'low_stock', 'out_of_stock', 'no_active_variants'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = $seller->products();
        if (filled($criteria['search'] ?? null)) {
            $term = '%'.mb_strtolower(trim($criteria['search'])).'%';
            $query->where(fn ($query) => $query->whereRaw('LOWER(products.name) LIKE ?', [$term])
                ->orWhereHas('variants', fn ($variant) => $variant->whereRaw('LOWER(sku) LIKE ?', [$term])));
        }
        if (filled($criteria['status'] ?? null)) {
            $query->where('products.is_active', $criteria['status'] === 'active');
        }
        if (filled($criteria['category'] ?? null)) {
            $query->where('products.category_id', $criteria['category']);
        }
        $active = fn ($variant) => $variant->where('is_active', true);
        switch ($criteria['stock'] ?? '') {
            case 'in_stock':
                $query->whereHas('variants', fn ($variant) => $active($variant)->where('stock', '>', 0));
                break;
            case 'low_stock':
                $query->whereHas('variants', fn ($variant) => $active($variant)->whereBetween('stock', [1, self::LOW_STOCK]));
                break;
            case 'out_of_stock':
                $query->whereHas('variants', $active)
                    ->whereDoesntHave('variants', fn ($variant) => $active($variant)->where('stock', '>', 0));
                break;
            case 'no_active_variants':
                $query->whereDoesntHave('variants', $active);
                break;
        }
        $summary = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('products.is_active', true)->count(),
            'inactive' => (clone $query)->where('products.is_active', false)->count(),
            'restock' => (clone $query)->whereHas('variants', fn ($variant) => $active($variant)->where('stock', '<=', self::LOW_STOCK))->count(),
            'out_of_stock' => (clone $query)->whereHas('variants', $active)
                ->whereDoesntHave('variants', fn ($variant) => $active($variant)->where('stock', '>', 0))->count(),
        ];
        $direction = ($criteria['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $paginator = $query->with(['category.parent', 'images', 'variants' => fn ($variant) => $variant->orderBy('id')])
            ->orderBy('products.created_at', $direction)->orderBy('products.id', $direction)
            ->paginate(15)->appends(Arr::except($criteria, 'page'));
        $paginator->through(fn (Product $product) => $this->present($product));

        return compact('paginator', 'summary', 'criteria', 'filterCategories') + [
            'registeredCategory' => $seller->category?->name ?? 'Not assigned',
        ];
    }

    private function present(Product $product): array
    {
        $active = $product->variants->where('is_active', true);
        $stock = (int) $active->sum('stock');
        $path = $product->images->first()?->path;

        return [
            'id' => $product->id, 'name' => $product->name,
            'category' => $product->category?->name ?? 'Uncategorized',
            'category_parent' => $product->category?->parent?->name,
            'updated_at' => $product->updated_at?->setTimezone('Asia/Manila'),
            // Activity is the product's flag, independent of review and variant flags.
            'activity' => $product->is_active ? 'Active' : 'Inactive',
            'moderation' => $product->moderation_status?->value ?? 'Not recorded',
            'moderation_reason' => $product->moderation_reason,
            'image' => $path ? (Str::startsWith($path, ['https://', 'http://']) ? $path : Storage::disk('public')->url($path)) : null,
            'stock' => $stock, 'variant_count' => $product->variants->count(), 'active_variant_count' => $active->count(),
            'price_min' => $active->min('price_minor'), 'price_max' => $active->max('price_minor'),
            'stock_status' => $active->isEmpty() ? 'No active variants' : ($stock <= 0 ? 'Out of stock' : ($stock <= self::LOW_STOCK ? 'Low stock' : 'In stock')),
            'low_variants' => $active->where('stock', '>', 0)->where('stock', '<=', self::LOW_STOCK)->count(),
            'empty_variants' => $active->where('stock', '<=', 0)->count(),
            'variants' => $product->variants->map(fn ($variant) => [
                'name' => $variant->name, 'sku' => $variant->sku, 'stock' => $variant->stock,
                'price_minor' => $variant->price_minor, 'active' => $variant->is_active,
            ]),
        ];
    }
}
