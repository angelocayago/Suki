<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiderWallet extends Model
{
    protected $fillable = [
        'rider_id',
        'available_balance_minor',
        'pending_balance_minor',
    ];

    protected $casts = [
        'available_balance_minor' => 'integer',
        'pending_balance_minor' => 'integer',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(RiderWalletTransaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(RiderWithdrawal::class);
    }
}
