<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = ['code','scope','seller_id','name','description','audience','discount_type','discount_minor','discount_bps','minimum_spend_minor','max_discount_minor','per_buyer_limit','is_active','starts_at','expires_at','usage_limit','archived_at'];
    protected function casts(): array
    {
        return ['discount_minor'=>'integer','discount_bps'=>'integer','minimum_spend_minor'=>'integer','max_discount_minor'=>'integer','per_buyer_limit'=>'integer','is_active'=>'boolean','starts_at'=>'datetime','expires_at'=>'datetime','usage_limit'=>'integer','used_count'=>'integer','archived_at'=>'datetime','followers_notified_at'=>'datetime'];
    }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function claims(): HasMany { return $this->hasMany(VoucherClaim::class); }
    public function redemptions(): HasMany { return $this->hasMany(VoucherRedemption::class); }
}
