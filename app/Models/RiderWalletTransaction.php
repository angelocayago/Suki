<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RiderWalletTransaction extends Model {
    protected $fillable=['rider_wallet_id','shipment_id','type','amount_minor','balance_after_minor','reference','description','meta'];
    protected $casts=['amount_minor'=>'integer','balance_after_minor'=>'integer','meta'=>'array'];
    public function wallet(){ return $this->belongsTo(RiderWallet::class,'rider_wallet_id'); }
    public function shipment(){ return $this->belongsTo(Shipment::class); }
}
