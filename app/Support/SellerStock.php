<?php

namespace App\Support;

class SellerStock
{
    public static function threshold(array $data = []): int
    {
        return (int) ($data['low_stock_threshold'] ?? config('seller_inventory.low_stock_threshold'));
    }

    public static function status(int $stock, ?int $threshold = null): string
    {
        return $stock <= 0 ? 'out-of-stock' : ($stock <= ($threshold ?? self::threshold()) ? 'low-stock' : 'in-stock');
    }

    public static function sqlThreshold($query): string
    {
        return '(COALESCE('.$query->getQuery()->getGrammar()->wrap('seller_listing_data->low_stock_threshold').', '.self::threshold().') + 0)';
    }

    public static function filter($query, ?string $status): void
    {
        $threshold = self::sqlThreshold($query);
        match ($status) {
            'out-of-stock' => $query->where('sellable_stock', 0),
            'low-stock' => $query->where('sellable_stock', '>', 0)->whereRaw('sellable_stock <= '.$threshold),
            'in-stock' => $query->whereRaw('sellable_stock > '.$threshold),
            default => null,
        };
    }
}
