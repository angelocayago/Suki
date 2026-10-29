<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'shipment_id',
        'collected_by',
        'method',
        'status',
        'amount_minor',
        'currency',
        'provider',
        'provider_reference',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * COD remittance records associated with this payment.
     *
     * A payment may have multiple historical remittance
     * records if an earlier remittance was rejected.
     */
    public function remittanceItems(): HasMany
    {
        return $this->hasMany(
            CodRemittancePayment::class,
            'payment_id'
        );
    }
}
