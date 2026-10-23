<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderWithdrawal extends Model
{
    protected $fillable = [
        'rider_id',
        'rider_wallet_id',
        'amount_minor',
        'payment_method',
        'account_name',
        'account_number',
        'bank_name',
        'status',
        'transaction_reference',
        'rider_notes',
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

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(RiderWallet::class, 'rider_wallet_id');
    }
}
