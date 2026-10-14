<?php

namespace App\Models;

use App\Enums\SellerViolationCategory;
use App\Enums\SellerViolationSeverity;
use App\Enums\SellerViolationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerViolation extends Model
{
    protected $fillable = [
        'reference',
        'seller_id',
        'product_id',
        'created_by',
        'category',
        'severity',
        'status',
        'reason',
        'details',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => SellerViolationCategory::class,
            'severity' => SellerViolationSeverity::class,
            'status' => SellerViolationStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(SellerWarning::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SellerComplianceEvent::class)->latest();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(SellerViolationEvidence::class)->latest();
    }
}
