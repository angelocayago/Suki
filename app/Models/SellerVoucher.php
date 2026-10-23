<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SellerVoucher extends Model
{
    protected $fillable = ['seller_id', 'name', 'code', 'description', 'discount_type', 'discount_bps', 'discount_minor', 'minimum_minor', 'cap_minor', 'product_scope', 'starts_at', 'ends_at', 'total_limit', 'per_buyer_limit', 'status'];

    protected function casts(): array
    {
        return ['discount_bps' => 'integer', 'discount_minor' => 'integer', 'minimum_minor' => 'integer', 'cap_minor' => 'integer',
            'total_limit' => 'integer', 'per_buyer_limit' => 'integer', 'used_count' => 'integer', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'archived_at' => 'immutable_datetime', 'terms_locked_at' => 'immutable_datetime'];
    }

    protected function code(): Attribute
    {
        return Attribute::make(set: fn ($value) => strtoupper(trim((string) $value)));
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'seller_voucher_products')->withTrashed();
    }

    public function termsLocked(): bool
    {
        return $this->used_count > 0 || $this->terms_locked_at !== null;
    }

    public function estimate(int $eligibleSubtotalMinor): int
    {
        if ($eligibleSubtotalMinor < $this->minimum_minor || $eligibleSubtotalMinor <= 0) {
            return 0;
        }
        $discount = $this->discount_type === 'fixed' ? $this->discount_minor : intdiv($eligibleSubtotalMinor * $this->discount_bps + 5000, 10000);
        if ($this->discount_type === 'percentage' && $this->cap_minor !== null) {
            $discount = min($discount, $this->cap_minor);
        }

        return max(0, min($eligibleSubtotalMinor, $discount));
    }
}
