<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use SoftDeletes;

    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'options',
        'price_minor',
        'stock',
        'weight_grams',
        'length_mm',
        'width_mm',
        'height_mm',
        'is_active',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price_minor' => 'integer',
            'stock' => 'integer',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Product this variant belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Cart items using this variant.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Order items that reference this variant.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
