<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerWalletEntry extends Model
{
    protected $fillable=['seller_id','seller_order_id','payout_id','return_refund_request_id','source_wallet_entry_id','type','status','amount_minor','reference','description'];
    protected function casts(): array { return ['amount_minor'=>'integer']; }
    public function refundRequest(){return $this->belongsTo(ReturnRefundRequest::class,'return_refund_request_id');}
    public function sourceEarning(){return $this->belongsTo(self::class,'source_wallet_entry_id');}
    public function release(){return $this->hasOne(self::class,'source_wallet_entry_id');}
}
