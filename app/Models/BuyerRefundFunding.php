<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BuyerRefundFunding extends Model {
    public const AWAITING='APPROVED_AWAITING_FUNDING';public const READY='FUNDED_READY_TO_CREDIT';public const CREDITED='CREDITED_TO_BUYER_WALLET';
    protected $primaryKey='return_refund_request_id';public $incrementing=false;protected $guarded=['*'];
    protected function casts(): array{return ['buyer_refund_minor'=>'integer','seller_adjustment_minor'=>'integer','commission_reversal_minor'=>'integer','funded_minor'=>'integer','verified_at'=>'datetime','consumed_at'=>'datetime'];}
    public function returnRequest(){return $this->belongsTo(ReturnRefundRequest::class,'return_refund_request_id');}
    public function sellerAdjustment(){return $this->belongsTo(SellerWalletEntry::class,'seller_wallet_entry_id');}
    public function statusLabel(): string{return match($this->status){self::READY=>'Funded — awaiting wallet credit',self::CREDITED=>'Credited to Suki Wallet',default=>'Approved — awaiting funding'};}
}
