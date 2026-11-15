<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BuyerRefundFunding extends Model {
    public const AWAITING='APPROVED_AWAITING_FUNDING';public const READY='FUNDED_READY_TO_CREDIT';public const CREDITED='CREDITED_TO_BUYER_WALLET';
    protected $primaryKey='return_refund_request_id';public $incrementing=false;protected $guarded=['*'];
    protected function casts(): array{return ['buyer_refund_minor'=>'integer','seller_adjustment_minor'=>'integer','commission_reversal_minor'=>'integer','funded_minor'=>'integer','verified_at'=>'datetime','consumed_at'=>'datetime'];}
    public function returnRequest(){return $this->belongsTo(ReturnRefundRequest::class,'return_refund_request_id');}
    public function sellerAdjustment(){return $this->belongsTo(SellerWalletEntry::class,'seller_wallet_entry_id');}
    public function postedCredit(){return $this->hasOne(BuyerWalletEntry::class,'return_refund_request_id','return_refund_request_id')->where('type','REFUND_CREDIT')->where('status','POSTED');}
    public function simulationBacking(){return $this->hasOne(SimulationWalletBacking::class,'return_refund_request_id','return_refund_request_id');}
    public function statusLabel(): string{
        if($this->status===self::CREDITED){
            $credit=$this->relationLoaded('postedCredit')?$this->postedCredit:null;
            if($this->funding_source_type==='SIMULATION_SETTLEMENT'){
                $backing=$this->relationLoaded('simulationBacking')?$this->simulationBacking:null;
                if(!$backing || !$credit || (int)$backing->buyer_id!==(int)$this->buyer_id || (int)$backing->buyer_wallet_entry_id!==(int)$credit->id || $backing->amount_minor!==$this->buyer_refund_minor)return 'Wallet credit not verified';
            }
            return $credit && (int)$credit->buyer_id===(int)$this->buyer_id && $credit->amount_minor===$this->buyer_refund_minor
                ? 'Credited to Suki Wallet' : 'Wallet credit not verified';
        }
        if($this->funding_source_type==='SIMULATION_SETTLEMENT')return $this->status===self::READY
            ? (config('suki_wallet.refund_credit_enabled')===true?'Simulation funded — awaiting wallet credit':'Simulation funded — wallet credit disabled') : 'Simulation commitment — not spendable';
        return $this->status===self::READY?'Funded — Processing Wallet Credit':'Approved — Awaiting Funding';
    }
}
