<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupRequest extends Model
{
    /**
     * Fields allowed for mass assignment.
     */
    protected $fillable = [
        'shipment_id',
        'rider_id',
        'status',
        'offered_at',
        'expires_at',
        'responded_at',
    ];

    /**
     * Convert timestamps into Carbon datetime objects.
     */
    protected $casts = [
        'offered_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    /**
     * Shipment associated with this pickup request.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Rider receiving the pickup offer.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
