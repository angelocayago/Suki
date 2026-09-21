<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOrder extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'order_id',
        'seller_id',
        'logistics_provider_id',
        'subtotal_minor',
        'shipping_fee_minor',
        'commission_minor',
        'status',
        'delivered_at',
        'note',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'shipping_fee_minor' => 'integer',
            'commission_minor' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Main order this seller order belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Seller/shop responsible for this order.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Logistics provider assigned to this seller order.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(
            LogisticsProvider::class,
            'logistics_provider_id'
        );
    }

    /**
     * Items belonging to this seller order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Shipment for this seller order.
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}