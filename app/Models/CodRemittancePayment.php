<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodRemittancePayment extends Model
{
    protected $fillable = [
        'cod_remittance_id',
        'payment_id',
        'amount_minor',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
    ];

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(
            CodRemittance::class,
            'cod_remittance_id'
        );
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
