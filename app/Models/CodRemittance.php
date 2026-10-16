<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodRemittance extends Model
{
    protected $fillable = [
        'reference',
        'rider_user_id',
        'logistic_id',
        'total_minor',
        'status',
        'submitted_at',
        'confirmed_at',
        'rejected_at',
        'confirmed_by',
        'rejected_by',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'total_minor' => 'integer',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_user_id');
    }

    public function logistic(): BelongsTo
    {
        return $this->belongsTo(Logistic::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CodRemittancePayment::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
