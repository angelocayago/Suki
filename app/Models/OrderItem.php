<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'seller_order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'unit_price_minor',
        'quantity',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /**
     * Seller order this item belongs to.
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /**
     * Product referenced by this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Product variant referenced by this item.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}