<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'owner_type',
        'seller_id',
        'name',
        'code',
        'description',
        'discount_type',
        'discount_value_minor',
        'discount_percent_bps',
        'minimum_spend_minor',
        'maximum_discount_minor',
        'product_scope',
        'usage_limit',
        'per_user_limit',
        'starts_at',
        'ends_at',
        'is_enabled',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_enabled' => 'boolean',
        'discount_value_minor' => 'integer',
        'discount_percent_bps' => 'integer',
        'minimum_spend_minor' => 'integer',
        'maximum_discount_minor' => 'integer',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'usage_count' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'voucher_products'
        );
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    public function scopeCurrentlyActive($query)
    {
        return $query
            ->where('is_enabled', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function getDisplayStatusAttribute(): string
    {
        if (!$this->is_enabled) {
            return 'paused';
        }

        if ($this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at->isPast()) {
            return 'expired';
        }

        if (
            $this->usage_limit !== null &&
            $this->usage_count >= $this->usage_limit
        ) {
            return 'exhausted';
        }

        return 'active';
    }
}
