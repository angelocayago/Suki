<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    /**
     * Compatibility fields for both legacy and new schema.
     */
    protected $fillable = [
        'user_id',
        'product_slug',
        'product_name',

        // Legacy production field
        'price',

        // New schema field
        'price_minor',

        'image',
    ];

    protected function casts(): array
    {
        return [
            // Legacy production schema
            'price' => 'decimal:2',

            // New schema
            'price_minor' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}