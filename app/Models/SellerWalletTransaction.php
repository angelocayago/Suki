<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerWalletTransaction extends Model
{
    protected $fillable = [
        'seller_wallet_id',
        'seller_settlement_id',
        'type',
        'amount_minor',
        'balance_after_minor',
        'reference',
        'description',
        'meta',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'balance_after_minor' => 'integer',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SellerWallet::class, 'seller_wallet_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SellerSettlement::class, 'seller_settlement_id');
    }
}
