<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderShippingFeeEarning extends Model
{
    protected $fillable = ['seller_order_id', 'shipment_id', 'rider_id', 'amount_minor', 'earned_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'earned_at' => 'datetime'];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }
}
