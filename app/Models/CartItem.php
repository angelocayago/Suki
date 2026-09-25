<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /**
     * Temporary compatibility fields.
     *
     * Legacy production cart:
     * user_id, product_slug, product_name, price, image, quantity
     *
     * New cart architecture:
     * cart_id, product_variant_id, quantity, selected
     */
    protected $fillable = [
        // Legacy fields
        'user_id',
        'product_slug',
        'product_name',
        'price',
        'image',

        // New schema fields
        'cart_id',
        'product_variant_id',
        'selected',

        // Shared field
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            // Legacy schema
            'price' => 'decimal:2',

            // Shared/new schema
            'quantity' => 'integer',
            'selected' => 'boolean',
        ];
    }

    /**
     * Legacy cart owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * New cart architecture.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * New product variant architecture.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}