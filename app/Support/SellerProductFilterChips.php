<?php

namespace App\Support;

class SellerProductFilterChips
{
    public const KEYS = ['search', 'status', 'stock_status', 'product_type', 'variant_id', 'sort', 'date_field', 'category', 'subcategory', 'date_range', 'from', 'to', 'min_stock', 'max_stock', 'min_price', 'max_price'];

    /** Backwards-compatible catalogue presentation entry point. */
    public static function make(array $input, array $choices, string $url): array
    {
        return SellerFilterChips::make($input, $choices, $url, self::KEYS);
    }
}
