<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherClaim extends Model
{
    public $timestamps = false;
    protected $fillable = ['voucher_id', 'buyer_id', 'claimed_at'];
    protected function casts(): array { return ['claimed_at' => 'datetime']; }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
}
