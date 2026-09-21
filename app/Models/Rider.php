<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'logistics_provider_id',
        'vehicle_type',
        'plate_no',
        'is_active',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * User account of this rider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logistics provider this rider belongs to.
     */
    public function logisticsProvider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class);
    }

    /**
     * Shipments assigned to this rider.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}