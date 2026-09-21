<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsProvider extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'status',
        'rejection_reason',
        'contact_phone',
    ];

    /**
     * User who owns/manages this logistics provider.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Riders under this logistics provider.
     */
    public function riders(): HasMany
    {
        return $this->hasMany(Rider::class);
    }

    /**
     * Seller orders assigned to this logistics provider.
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * Shipments handled by this logistics provider.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}