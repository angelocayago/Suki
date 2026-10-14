<?php

namespace App\Models;

use App\Enums\SellerViolationSeverity;
use App\Enums\SellerViolationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerComplianceEvent extends Model
{
    protected $fillable = [
        'seller_violation_id',
        'seller_id',
        'actor_id',
        'account_status_event_id',
        'action',
        'previous_status',
        'new_status',
        'previous_severity',
        'new_severity',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => SellerViolationStatus::class,
            'new_status' => SellerViolationStatus::class,
            'previous_severity' => SellerViolationSeverity::class,
            'new_severity' => SellerViolationSeverity::class,
            'metadata' => 'array',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function accountStatusEvent(): BelongsTo
    {
        return $this->belongsTo(AccountStatusEvent::class);
    }
}
