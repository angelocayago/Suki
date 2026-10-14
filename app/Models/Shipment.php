<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'seller_order_id',
        'logistics_provider_id',
        'pickup_rider_id',
        'rider_id',
        'tracking_code',
        'status',
        'workflow_status',
        'sorting_area',
        'pickup_accepted_at',
        'status_changed_at',
        'fee_minor',
        'cod_amount_minor',
        'cod_collected',
        'attempts',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'fee_minor' => 'integer',
            'cod_amount_minor' => 'integer',
            'cod_collected' => 'boolean',
            'attempts' => 'integer',
            'workflow_status' => OrderStatus::class,
            'pickup_accepted_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    /**
     * Seller order this shipment belongs to.
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /**
     * Logistics provider handling this shipment.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(
            LogisticsProvider::class,
            'logistics_provider_id'
        );
    }

    /**
     * Rider assigned to this shipment.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function pickupRider(): BelongsTo
    {
        return $this->belongsTo(Rider::class, 'pickup_rider_id');
    }

    /**
     * Delivery timeline events for this shipment.
     */
    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class)
            ->orderBy('occurred_at');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
