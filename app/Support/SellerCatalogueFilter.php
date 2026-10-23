<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerCatalogueFilter
{
    public static function query(Seller $seller, Request $request)
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:150',
            'category' => ['nullable', 'integer', Rule::in([$main = SellerCategories::main($seller)?->id])],
            'subcategory' => ['nullable', 'integer', Rule::in(SellerCategories::children($seller, SellerCategories::main($seller))->pluck('id')->all())],
            'status' => 'nullable|in:active,inactive,draft',
            'stock_status' => 'nullable|in:in-stock,low-stock,out-of-stock',
            'product_type' => 'nullable|in:simple,variable',
            'variant_id' => ['nullable', 'integer', 'min:1', Rule::exists('product_variants', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->whereIn('product_id', $seller->products()->select('products.id')))],
            'min_stock' => 'nullable|integer|min:0|max:4294967295',
            'max_stock' => ['nullable', 'integer', 'min:0', 'max:4294967295', Rule::when($request->filled('min_stock'), 'gte:min_stock')],
            'min_price' => 'nullable|numeric|min:0|max:42949672.95',
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:42949672.95', Rule::when($request->filled('min_price'), 'gte:min_price')],
            'sort' => 'nullable|in:name_asc,name_desc,stock_asc,stock_desc,updated,price_asc,price_desc',
            'date_field' => 'nullable|in:created_at,updated_at',
            'page' => 'nullable|integer|min:1',
        ]);
        $base = $seller->products()->select('products.*')
            ->selectSub(ProductVariant::selectRaw('COALESCE(SUM(stock), 0)')->whereColumn('product_id', 'products.id')->where('is_active', true), 'sellable_stock')
            ->selectSub(ProductVariant::selectRaw('MIN(price_minor)')->whereColumn('product_id', 'products.id')->where('is_active', true), 'sellable_price')
            ->withCount('variants');
        $query = Product::query()->fromSub($base->toBase(), 'products')->with(['category.parent', 'variants', 'images']);
        if (isset($data['search']) && $data['search'] !== '') {
            $term = '%'.$data['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $term)->orWhere('name', 'like', $term)->orWhere('options', 'like', $term)));
        }
        if (! empty($data['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('id', $main)->orWhere('parent_id', $main));
        }
        if (! empty($data['subcategory'])) {
            $query->where('category_id', $data['subcategory']);
        }
        if (! empty($data['status'])) {
            if ($data['status'] === 'draft') {
                $query->where('seller_listing_data->status', 'draft');
            } else {
                $query->where(fn ($q) => $q->whereNull('seller_listing_data->status')->orWhere('seller_listing_data->status', '!=', 'draft'))
                    ->where('is_active', $data['status'] === 'active');
            }
        }
        SellerStock::filter($query, $data['stock_status'] ?? null);
        if (! empty($data['product_type'])) {
            $variable = $data['product_type'] === 'variable';
            $query->where(function ($q) use ($variable) {
                if ($variable) {
                    $q->where('seller_listing_data->product_type', 'variable')->orWhere('variants_count', '>', 1)->orWhereHas('variants', fn ($v) => $v->whereNotNull('options')->where('options', '!=', '[]')->where('options', '!=', '{}'));
                } else {
                    $q->where('variants_count', '<=', 1)->where(fn ($q) => $q->whereNull('seller_listing_data->product_type')->orWhere('seller_listing_data->product_type', '!=', 'variable'))
                        ->whereDoesntHave('variants', fn ($v) => $v->whereNotNull('options')->where('options', '!=', '[]')->where('options', '!=', '{}'));
                }
            });
        }
        if (! empty($data['variant_id'])) {
            $query->whereHas('variants', fn ($q) => $q->whereKey($data['variant_id']));
        }
        foreach (['stock' => 'sellable_stock', 'price' => 'sellable_price'] as $field => $column) {
            foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
                if (isset($data[$key = $bound.'_'.$field])) {
                    $query->where($column, $operator, $data[$key] * ($field === 'price' ? 100 : 1));
                }
            }
        }
        SellerDateFilter::apply($query, $request, 'products.'.($data['date_field'] ?? 'created_at'));
        [$column, $direction] = match ($data['sort'] ?? 'name_asc') {
            'name_desc' => ['name', 'desc'], 'stock_asc' => ['sellable_stock', 'asc'], 'stock_desc' => ['sellable_stock', 'desc'],
            'updated' => ['updated_at', 'desc'], 'price_asc' => ['sellable_price', 'asc'], 'price_desc' => ['sellable_price', 'desc'], default => ['name', 'asc'],
        };

        return $query->orderBy($column, $direction)->orderBy('id');
    }

    public static function page(Seller $seller, Request $request): array
    {
        $query = self::query($seller, $request);
        $summary = (clone $query)->reorder()->with([])->get(['sellable_stock', 'seller_listing_data', 'is_active'])->map(fn ($p) => [
            'stock' => (int) $p->sellable_stock,
            'low_stock_threshold' => SellerStock::threshold($p->seller_listing_data ?? []),
            'stock_status' => SellerStock::status((int) $p->sellable_stock, SellerStock::threshold($p->seller_listing_data ?? [])),
            'status' => ($p->seller_listing_data['status'] ?? '') === 'draft' ? 'draft' : ($p->is_active ? 'active' : 'inactive'),
        ]);
        $paginator = $query->paginate(15)->withQueryString();

        return ['products' => $paginator->getCollection()->mapWithKeys(function ($p) {
            $row = SellerProducts::listing($p);

            return [$row['id'] => $row];
        })->all(),
            'paginator' => $paginator, 'filteredSummary' => $summary,
            'filterCategories' => SellerCategories::children($seller, SellerCategories::main($seller)),
            'mainCategory' => SellerCategories::main($seller),
            'filterVariants' => ProductVariant::whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))->orderBy('sku')->get(['id', 'sku'])];
    }
}
