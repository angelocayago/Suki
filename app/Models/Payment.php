<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'order_id',
        'method',
        'amount_minor',
        'status',
        'provider_ref',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    /**
     * Order this payment belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}