<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BuyerWallet extends Model {
    protected $primaryKey='buyer_id';public $incrementing=false;protected $fillable=['buyer_id'];
    public function buyer(){return $this->belongsTo(User::class,'buyer_id');}
    public function entries(){return $this->hasMany(BuyerWalletEntry::class,'buyer_id','buyer_id');}
}
