<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromotionCampaign extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_path',
        'campaign_type',
        'starts_at',
        'ends_at',
        'is_enabled',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_enabled' => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'campaign_products',
            'campaign_id',
            'product_id'
        )->withPivot([
            'discount_type',
            'discount_value_minor',
            'discount_percent_bps',
            'funded_by',
            'quantity_limit',
            'quantity_used',
        ])->withTimestamps();
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
            return 'disabled';
        }

        if ($this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at->isPast()) {
            return 'ended';
        }

        return 'active';
    }
}
