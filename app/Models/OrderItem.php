<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /**
     * Supports both the existing SUKI order flow
     * and the new seller-order architecture.
     */
    protected $fillable = [

        // Legacy production fields
        'order_id',
        'seller_id',
        'product_slug',
        'product_name',
        'image',
        'variation',
        'unit_price',
        'quantity',
        'line_total',

        // New architecture fields
        'seller_order_id',
        'product_id',
        'product_variant_id',
        'variant_name',
        'unit_price_minor',
    ];

    protected function casts(): array
    {
        return [

            // Legacy
            'variation' => 'array',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',

            // Shared / new
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
        ];
    }

    /**
     * Legacy parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Legacy seller reference.
     *
     * Current production seller_id points to users.id.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'seller_id'
        );
    }

    /**
     * New seller-order architecture.
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}