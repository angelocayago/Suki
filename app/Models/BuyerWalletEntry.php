<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BuyerWalletEntry extends Model {
    public $timestamps=false;protected $guarded=['*'];
    protected function casts(): array{return ['amount_minor'=>'integer','created_at'=>'datetime'];}
    public function order(){return $this->belongsTo(Order::class);}
    public function returnRequest(){return $this->belongsTo(ReturnRefundRequest::class,'return_refund_request_id');}
    public function funding(){return $this->belongsTo(BuyerRefundFunding::class,'return_refund_request_id','return_refund_request_id');}
}
