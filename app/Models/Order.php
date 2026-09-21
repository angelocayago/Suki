<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'buyer_id',
        'reference',
        'total_minor',
        'payment_method',
        'payment_status',
        'shipping_address',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'total_minor' => 'integer',
            'shipping_address' => 'array',
        ];
    }

    /**
     * Buyer who placed this order.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'buyer_id'
        );
    }

    /**
     * Seller-specific orders under this order.
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * Payments attached to this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}