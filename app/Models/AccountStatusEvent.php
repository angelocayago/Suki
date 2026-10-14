<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountStatusEvent extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'application_id',
        'previous_status',
        'new_status',
        'reason',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function sellerComplianceEvents(): HasMany
    {
        return $this->hasMany(SellerComplianceEvent::class);
    }
}
