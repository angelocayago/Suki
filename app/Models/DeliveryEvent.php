<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEvent extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'shipment_id',
        'status',
        'attempt',
        'user_id',
        'note',
        'photo_path',
        'occurred_at',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Shipment this event belongs to.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * User who recorded this delivery event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}