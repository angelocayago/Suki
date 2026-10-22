<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ReturnRefundRequest extends Model {
    protected $fillable=['buyer_id','order_id','seller_order_id','order_item_id','seller_id','type','status','quantity','buyer_reason','buyer_description','seller_recommendation','seller_note','seller_responded_at','reviewed_by','admin_decision','admin_reason','decided_at','refund_minor'];
    protected function casts(): array { return ['seller_responded_at'=>'datetime','decided_at'=>'datetime','refund_minor'=>'integer']; }
    public function buyer(): BelongsTo{return $this->belongsTo(User::class,'buyer_id');} public function seller(): BelongsTo{return $this->belongsTo(Seller::class);} public function order(): BelongsTo{return $this->belongsTo(Order::class);} public function sellerOrder(): BelongsTo{return $this->belongsTo(SellerOrder::class);} public function item(): BelongsTo{return $this->belongsTo(OrderItem::class,'order_item_id');} public function reviewer(): BelongsTo{return $this->belongsTo(User::class,'reviewed_by');} public function events(): HasMany{return $this->hasMany(ReturnRefundEvent::class);}
}
