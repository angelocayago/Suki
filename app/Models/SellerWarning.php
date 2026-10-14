<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerWarning extends Model
{
    protected $fillable = [
        'seller_violation_id',
        'seller_id',
        'issued_by',
        'message',
        'issued_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(SellerViolation::class, 'seller_violation_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
