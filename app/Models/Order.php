<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /**
     * Supports both the current production order flow
     * and the future normalized order architecture.
     */
    protected $fillable = [

        // Legacy production fields
        'order_number',
        'buyer_id',
        'seller_id',
        'status',
        'payment_method',
        'payment_status',

        'subtotal',
        'shipping_fee',
        'discount_amount',
        'total_amount',
        'voucher_code',

        'recipient_name',
        'recipient_phone',
        'province',
        'municipality',
        'barangay',
        'street_address',
        'delivery_notes',

        'shipping_method',
        'house_number',
        'postal_code',
        'address_label',

        'placed_at',
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
        'delivery_failed_at',
        'returned_at',

        'cancel_reason',
        'cancelled_at',

        // New architecture fields
        'reference',
        'total_minor',
        'shipping_address',
    ];

    protected function casts(): array
    {
        return [

            // Legacy money fields
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',

            // Legacy order timestamps
            'placed_at' => 'datetime',
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
            'delivery_failed_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',

            // New architecture
            'total_minor' => 'integer',
            'shipping_address' => 'array',
        ];
    }

    /**
     * Buyer who placed the order.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'buyer_id'
        );
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
     * Legacy order items used by current checkout.
     */
    public function items(): HasMany
    {
        return $this->hasMany(
            OrderItem::class,
            'order_id'
        );
    }

    /**
     * New normalized seller-order architecture.
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * New payment architecture.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}