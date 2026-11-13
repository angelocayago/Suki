<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ReturnRefundRequest extends Model {
    public const FINANCIAL = 'FINANCIAL';
    public const ADMIN_REVIEW = 'ADMIN_REVIEW';
    public function isAdminReview(): bool { return $this->workflow_mode === self::ADMIN_REVIEW; }
    public function scopeFinancial(\Illuminate\Database\Eloquent\Builder $query): void
    {
        // Older installations have only financial records; do not require rollout
        // of the additive discriminator merely to keep existing wallet reads safe.
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'workflow_mode')) {
            $query->where($this->qualifyColumn('workflow_mode'), self::FINANCIAL);
        }
    }
    public function statusLabel(): string
    {
        if ($this->isAdminReview()) return match ($this->status) {
            'ADMIN_APPROVED' => 'Approved — Contact Seller',
            'ADMIN_REJECTED' => 'Rejected',
            default => 'Pending Admin Review',
        };
        return $this->status === 'ADMIN_APPROVED' ? 'Approved — Refund Due' : str_replace('_', ' ', $this->status);
    }
    public const ACTIVE_STATUSES = ['SUBMITTED', 'SELLER_RESPONDED', 'UNDER_REVIEW'];
    public function walletAdjustment(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(SellerWalletEntry::class,'return_refund_request_id')->where('type','REFUND_ADJUSTMENT'); }
    protected $fillable=['buyer_id','order_id','seller_order_id','order_item_id','seller_id','type','status','quantity','buyer_reason','buyer_description','seller_recommendation','seller_note','seller_responded_at','reviewed_by','admin_decision','admin_reason','decided_at','refund_minor'];
    protected function casts(): array { return ['seller_responded_at'=>'datetime','decided_at'=>'datetime','refund_minor'=>'integer']; }
    public function buyer(): BelongsTo{return $this->belongsTo(User::class,'buyer_id');} public function seller(): BelongsTo{return $this->belongsTo(Seller::class);} public function order(): BelongsTo{return $this->belongsTo(Order::class);} public function sellerOrder(): BelongsTo{return $this->belongsTo(SellerOrder::class);} public function item(): BelongsTo{return $this->belongsTo(OrderItem::class,'order_item_id');} public function reviewer(): BelongsTo{return $this->belongsTo(User::class,'reviewed_by');} public function events(): HasMany{return $this->hasMany(ReturnRefundEvent::class);}
}
