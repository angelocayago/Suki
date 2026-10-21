<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerWithdrawal extends Model
{
    protected $fillable = [
        'seller_wallet_id',
        'seller_id',
        'amount_minor',
        'payment_method',
        'account_name',
        'account_number',
        'bank_name',
        'status',
        'transaction_reference',
        'seller_notes',
        'admin_notes',
        'requested_at',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SellerWallet::class, 'seller_wallet_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
