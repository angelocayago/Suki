<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerWallet extends Model
{
    protected $fillable = [
        'seller_id',
        'available_balance_minor',
        'pending_balance_minor',
    ];

    protected $casts = [
        'available_balance_minor' => 'integer',
        'pending_balance_minor' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SellerWalletTransaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(SellerWithdrawal::class);
    }
}
