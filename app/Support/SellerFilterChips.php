<?php

namespace App\Support;

class SellerFilterChips
{
    public static function catalogue(array $input, $main, $categories, $variants, string $url): array
    {
        return self::make($input, [
            'status' => ['active' => 'Listing: Active', 'inactive' => 'Listing: Inactive', 'draft' => 'Listing: Draft'],
            'stock_status' => ['in-stock' => 'In Stock', 'low-stock' => 'Low Stock', 'out-of-stock' => 'Out of Stock'],
            'product_type' => ['simple' => 'Simple Products', 'variable' => 'Products With Variations'],
            'variant_id' => $variants->mapWithKeys(fn ($v) => [$v->id => 'Variant: '.$v->sku])->all(),
            'sort' => ['name_asc' => 'Product Name A–Z', 'name_desc' => 'Product Name Z–A', 'stock_asc' => 'Stock Lowest First', 'stock_desc' => 'Stock Highest First', 'price_asc' => 'Price Lowest First', 'price_desc' => 'Price Highest First', 'updated' => 'Recently Updated'],
            'category' => $main ? [$main->id => $main->name] : [], 'subcategory' => $categories->pluck('name', 'id')->all(),
        ], $url, SellerProductFilterChips::KEYS);
    }

    public static function transactions(array $input, $products, $main, $categories, $methods, string $url, bool $sales = false): array
    {
        return self::make($input, [
            'product_id' => $products->mapWithKeys(fn ($p) => [$p->id => 'Product: '.$p->name])->all(),
            'category' => $main ? [$main->id => $main->name] : [], 'subcategory' => $categories->pluck('name', 'id')->all(),
            'order_status' => array_combine(SellerReports::STATUSES, array_map(fn ($v) => 'Status: '.ucwords(str_replace('_', ' ', $v)), SellerReports::STATUSES)),
            'payment_method' => $methods->mapWithKeys(fn ($v) => [$v => 'Payment method: '.$v])->all(),
            'sort' => $sales ? ['newest' => 'Newest First', 'oldest' => 'Oldest First', 'amount_desc' => 'Highest Transaction Amount', 'amount_asc' => 'Lowest Transaction Amount']
                : ['recent' => 'Most Recent', 'sales_desc' => 'Highest Sales', 'sales_asc' => 'Lowest Sales', 'units_desc' => 'Most Units Sold'],
        ], $url, ['product_id', 'category', 'subcategory', 'order_status', 'payment_method', 'sort', 'date_range', 'from', 'to'], $sales ? 'newest' : 'sales_desc');
    }

    public static function make(array $input, array $choices, string $url, array $keys, string $defaultSort = 'name_asc'): array
    {
        $query = array_filter(array_intersect_key($input, array_flip($keys)), fn ($value) => is_scalar($value) && (string) $value !== '');
        $chips = [];
        $add = function (string $key, string $label, array $remove) use (&$chips, $query, $url) {
            $remaining = array_diff_key($query, array_flip($remove));
            $chips[] = ['key' => $key, 'label' => $label, 'url' => $url.($remaining ? '?'.http_build_query($remaining) : '')];
        };
        if (isset($query['search'])) {
            $add('search', 'Search: '.$query['search'], ['search']);
        }
        foreach ($choices as $key => $values) {
            if (isset($query[$key], $values[$query[$key]]) && ! ($key === 'sort' && $query[$key] === $defaultSort)) {
                $add($key, $values[$query[$key]], [$key]);
            }
        }
        $preset = $query['date_range'] ?? 'all';
        if ($preset !== 'all' && isset(SellerDateFilter::PRESETS[$preset])) {
            if ($preset !== 'custom' || isset($query['from'], $query['to'])) {
                $add('date_range', $preset === 'custom' ? 'Date: '.$query['from'].' – '.$query['to'] : SellerDateFilter::PRESETS[$preset], ['date_range', 'from', 'to']);
            }
            if (($query['date_field'] ?? '') === 'updated_at') {
                $add('date_field', 'Last Updated', ['date_field']);
            }
        }
        foreach (['stock' => 'Stock', 'price' => 'Price'] as $field => $label) {
            $min = $query['min_'.$field] ?? null;
            $max = $query['max_'.$field] ?? null;
            if ($min !== null || $max !== null) {
                $format = fn ($value) => $field === 'price' ? '₱'.number_format((float) $value, 2) : number_format((int) $value);
                $range = $min !== null && $max !== null ? $format($min).' – '.$format($max) : ($min !== null ? '≥ '.$format($min) : '≤ '.$format($max));
                $add($field, $label.': '.$range, ['min_'.$field, 'max_'.$field]);
            }
        }

        return $chips;
    }
}
