<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'label',
        'recipient',
        'phone',
        'line1',
        'barangay',
        'city',
        'province',
        'postal_code',
        'is_default',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * User who owns this address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}