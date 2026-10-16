<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerSettlement extends Model
{
    protected $fillable = [
        'seller_order_id',
        'seller_id',
        'subtotal_minor',
        'commission_minor',
        'net_minor',
        'status',
        'approved_by',
        'approved_at',
        'paid_at',
        'payout_reference',
        'notes',
    ];

    protected $casts = [
        'subtotal_minor' => 'integer',
        'commission_minor' => 'integer',
        'net_minor' => 'integer',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
