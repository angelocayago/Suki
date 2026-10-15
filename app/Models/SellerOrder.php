<?php

namespace App\Models;

use App\Enums\OrderStatus;
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
        'workflow_status',
        'status_changed_at',
        'confirmed_at',
        'preparing_at',
        'ready_for_pickup_at',
        'picked_up_at',
        'at_sorting_center_at',
        'sorted_at',
        'assigned_to_rider_at',
        'out_for_delivery_at',
        'delivered_at',
        'completed_at',
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
            'workflow_status' => OrderStatus::class,
            'status_changed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_for_pickup_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'at_sorting_center_at' => 'datetime',
            'sorted_at' => 'datetime',
            'assigned_to_rider_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function voucherRedemptions(): HasMany { return $this->hasMany(VoucherRedemption::class); }
}
