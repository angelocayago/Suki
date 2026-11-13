<?php
namespace App\Services;

use App\Models\{LogisticsCodRemittance,LogisticsCodRemittanceItem,LogisticsHub,Payout,RiderCodRemittance,Shipment,Seller, SellerWalletEntry,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CodRemittanceService
{
    public function createLiability(Shipment $shipment): void
    {
        if (! $shipment->cod_collected || ! $shipment->delivered_by || (int) $shipment->cod_collected_minor < 1) return;
        $rider = \App\Models\Rider::query()->where('user_id', $shipment->delivered_by)->first();
        if (! $rider || (int) $rider->logistics_provider_id !== (int) $shipment->logistics_provider_id) return;
        RiderCodRemittance::firstOrCreate(['shipment_id'=>$shipment->id], ['seller_order_id'=>$shipment->seller_order_id,'rider_id'=>$rider->id,'logistics_provider_id'=>$shipment->logistics_provider_id,'logistics_hub_id'=>$shipment->destination_hub_id,'expected_minor'=>$shipment->cod_collected_minor,'status'=>'PENDING']);
    }
    public function submitRider(RiderCodRemittance $remittance, User $actor, ?int $amountMinor = null, string $reference=null): void
    { DB::transaction(function() use($remittance,$actor,$amountMinor,$reference) { $r=RiderCodRemittance::lockForUpdate()->findOrFail($remittance->id); $amountMinor ??= (int)$r->expected_minor; if(!$actor->isRider() || (int)$r->rider->user_id!==$actor->id) abort(403); if(in_array($r->status,['SUBMITTED','VERIFIED'],true)) return; if(!in_array($r->status,['PENDING','OUTSTANDING'],true)) throw ValidationException::withMessages(['remittance'=>'This COD liability is not available.']); if($amountMinor<1)throw ValidationException::withMessages(['amount_minor'=>'A positive cash amount is required.']); $match=$amountMinor===(int)$r->expected_minor; $r->update(['submitted_minor'=>$amountMinor,'status'=>$match?'SUBMITTED':'DISCREPANCY','submitted_at'=>now(),'reference'=>$reference,'reason'=>$match?null:'Submitted cash does not equal collected COD.']); $this->audit($r,$actor,$match?'RIDER_SUBMITTED':'RIDER_DISCREPANCY',$r->reason); }); }
    public function reviewRider(RiderCodRemittance $remittance, User $actor, string $status, ?string $reason): void
    { DB::transaction(function() use($remittance,$actor,$status,$reason) { $r=RiderCodRemittance::with('provider')->lockForUpdate()->findOrFail($remittance->id); if(!$actor->isLogistics() || (int)$r->provider->user_id!==$actor->id) abort(403); if($r->status==='VERIFIED'&&$status==='VERIFIED')return; if($r->status!=='SUBMITTED') throw ValidationException::withMessages(['remittance'=>'Only submitted remittances can be verified.']); if($status==='DISCREPANCY'&&!filled($reason)) throw ValidationException::withMessages(['reason'=>'A discrepancy reason is required.']); $r->update(['status'=>$status,'accepted_minor'=>$status==='VERIFIED'?$r->expected_minor:null,'reason'=>$status==='DISCREPANCY'?trim($reason):null,'reviewed_by'=>$actor->id,'reviewed_at'=>now()]); $this->audit($r,$actor,'LOGISTICS_'.$status,$reason); }); }
    public function submitLogistics(User $actor, array $ids, ?string $reference): LogisticsCodRemittance
    { return DB::transaction(function()use($actor,$ids,$reference){ if(!$actor->isLogistics()) abort(403); $provider=$actor->logisticsProvider; if(!$provider) abort(403); $items=RiderCodRemittance::whereIn('id',array_unique($ids))->where('logistics_provider_id',$provider->id)->where('status','VERIFIED')->lockForUpdate()->get(); if($items->count()!==count(array_unique($ids))||$items->isEmpty()) throw ValidationException::withMessages(['remittances'=>'Select verified COD receipts from your provider only.']); if($items->pluck('logistics_hub_id')->filter()->unique()->count()>1)throw ValidationException::withMessages(['remittances'=>'A hub remittance batch must contain one designated hub.']); if(LogisticsCodRemittanceItem::whereIn('rider_cod_remittance_id',$items->pluck('id'))->exists()) throw ValidationException::withMessages(['remittances'=>'A selected receipt is already in a remittance batch.']); $total=(int)$items->sum('accepted_minor'); $hubId=$items->pluck('logistics_hub_id')->filter()->first(); $rem=LogisticsCodRemittance::create(['logistics_provider_id'=>$provider->id,'logistics_hub_id'=>$hubId,'expected_minor'=>$total,'submitted_minor'=>$total,'accepted_minor'=>$total,'status'=>'VERIFIED','submitted_at'=>now(),'reviewed_at'=>now(),'reviewed_by'=>$actor->id,'reference'=>$reference]); foreach($items as $item) LogisticsCodRemittanceItem::create(['logistics_cod_remittance_id'=>$rem->id,'rider_cod_remittance_id'=>$item->id,'amount_minor'=>$item->accepted_minor]); $this->audit($rem,$actor,'HUB_BATCH_VERIFIED'); foreach($items as $item) app(SellerWalletService::class)->reconcileSellerOrderResult($item->sellerOrder); return $rem; },3); }
    public function reviewLogistics(LogisticsCodRemittance $remittance, User $actor, string $status, ?string $reason): void
    {
        DB::transaction(function()use($remittance,$actor,$status,$reason){
            if(!$actor->isAdmin() || $actor->status !== 'active' || $actor->is_suspended) abort(403);
            if(!in_array($status,['ACCEPTED','REJECTED'],true)) throw ValidationException::withMessages(['remittance'=>'Choose a valid review decision.']);
            $references = LogisticsCodRemittanceItem::with('riderRemittance.sellerOrder')->where('logistics_cod_remittance_id',$remittance->id)->get();
            $sellerIds = $references->map(fn($item)=>$item->riderRemittance?->sellerOrder?->seller_id)->filter()->unique()->sort()->values();
            $parentIds = $references->map(fn($item)=>$item->riderRemittance?->sellerOrder?->order_id)->filter()->unique()->sort()->values();
            // Lock all batch Sellers and parents in stable order, before taking
            // the remittance lock. Reconciliation never locks custody in reverse.
            \App\Models\Seller::whereIn('id',$sellerIds)->orderBy('id')->lockForUpdate()->get();
            \App\Models\Order::whereIn('id',$parentIds)->orderBy('id')->lockForUpdate()->get();
            $r = LogisticsCodRemittance::with('items.riderRemittance.sellerOrder.seller')->lockForUpdate()->findOrFail($remittance->id);
            $freshSellers = $r->items->map(fn($item)=>$item->riderRemittance?->sellerOrder?->seller_id)->filter()->unique()->sort()->values();
            $freshParents = $r->items->map(fn($item)=>$item->riderRemittance?->sellerOrder?->order_id)->filter()->unique()->sort()->values();
            if($freshSellers->all() !== $sellerIds->all() || $freshParents->all() !== $parentIds->all()) throw ValidationException::withMessages(['remittance'=>'Batch ownership changed; retry the review.']);
            if($r->status!=='SUBMITTED') throw ValidationException::withMessages(['remittance'=>'Only submitted remittances can be reviewed.']);
            if($status==='REJECTED'&&!filled($reason)) throw ValidationException::withMessages(['reason'=>'A discrepancy reason is required.']);
            $r->update(['status'=>$status,'accepted_minor'=>$status==='ACCEPTED'?$r->expected_minor:null,'reason'=>$status==='REJECTED'?trim($reason):null,'reviewed_by'=>$actor->id,'reviewed_at'=>now()]);
            $this->audit($r,$actor,'ADMIN_'.$status,$reason);
            if($status==='ACCEPTED')foreach($r->items as $item){
                if($item->riderRemittance?->sellerOrder && app(SellerWalletService::class)->reconcileSellerOrderResult($item->riderRemittance->sellerOrder)['status'] === 'RELEASED')
                    app(DomainNotificationService::class)->afterCommit($item->riderRemittance->sellerOrder->seller->user_id,'COD reconciliation complete','Your seller earning is now available.',['seller_order_id'=>$item->riderRemittance->seller_order_id]);
            }
            app(DomainNotificationService::class)->afterCommit($r->provider->user_id,'COD remittance '.$status,'Your upstream COD remittance was '.strtolower($status).'.',['logistics_remittance_id'=>$r->id]);
        },3);
    }
    public function settleSeller(Seller $seller, User $actor, LogisticsProvider $provider, LogisticsHub $hub, int $amountMinor, string $receiver, string $reference, ?string $note): Payout
    {
        return DB::transaction(function()use($seller,$actor,$provider,$hub,$amountMinor,$receiver,$reference,$note){
            if(!$actor->isLogistics()||(int)$provider->user_id!==$actor->id||$provider->status!=='approved'||!$hub->is_active||(int)$hub->logistics_provider_id!==$provider->id)abort(403);
            if($amountMinor<1||!filled($receiver)||!filled($reference))throw ValidationException::withMessages(['settlement'=>'Amount, receiver and offline reference are required.']);
            $seller=Seller::lockForUpdate()->findOrFail($seller->id);
            $available=app(SellerWalletService::class)->summary($seller)['available_minor'];
            if($amountMinor>$available)throw ValidationException::withMessages(['amount_minor'=>'Settlement exceeds the seller’s verified available COD balance.']);
            $payout=Payout::create(['seller_id'=>$seller->id,'amount_minor'=>$amountMinor,'status'=>'PAID','method'=>'OFFLINE_COD','reference'=>trim($reference),'reason'=>trim((string)$note),'logistics_provider_id'=>$provider->id,'logistics_hub_id'=>$hub->id,'settled_by'=>$actor->id,'receiver_name'=>trim($receiver),'proof_note'=>trim((string)$note),'reviewed_by'=>$actor->id,'reviewed_at'=>now(),'paid_at'=>now()]);
            SellerWalletEntry::create(['seller_id'=>$seller->id,'payout_id'=>$payout->id,'type'=>'PAYOUT_COMPLETED','status'=>'POSTED','amount_minor'=>$amountMinor,'reference'=>$payout->reference,'description'=>'Offline COD settlement recorded by Logistics.']);
            $this->audit($payout,$actor,'SELLER_SETTLED',$note); return $payout;
        },3);
    }
    private function audit($subject, User $actor, string $action, ?string $reason=null): void { DB::table('cod_remittance_audits')->insert(['subject_type'=>get_class($subject),'subject_id'=>$subject->id,'actor_id'=>$actor->id,'action'=>$action,'reason'=>$reason,'occurred_at'=>now()]); }
}
