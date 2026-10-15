<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class VoucherRedemption extends Model {protected $fillable=['voucher_id','order_id','seller_order_id','buyer_id','code_snapshot','scope_snapshot','seller_id_snapshot','discount_type_snapshot','discount_value_snapshot','discount_minor']; protected function casts():array{return['discount_value_snapshot'=>'integer','discount_minor'=>'integer'];} public function sellerOrder(){return $this->belongsTo(SellerOrder::class);}}
