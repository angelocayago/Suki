<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignProduct extends Model
{
    protected $fillable = [
        'campaign_id',
        'product_id',
        'discount_type',
        'discount_value_minor',
        'discount_percent_bps',
        'funded_by',
        'quantity_limit',
        'quantity_used',
    ];

    protected $casts = [
        'discount_value_minor' => 'integer',
        'discount_percent_bps' => 'integer',
        'quantity_limit' => 'integer',
        'quantity_used' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionCampaign::class, 'campaign_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
