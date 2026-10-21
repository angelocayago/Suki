<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class RiderShippingFeeEarning extends Model { protected $fillable=['seller_order_id','shipment_id','rider_id','amount_minor','earned_at']; protected function casts():array{return['amount_minor'=>'integer','earned_at'=>'datetime'];} }
