<?php
namespace App\Services;

use App\Models\{CustodySource,LogisticsCodRemittance,LogisticsCodRemittanceItem,Order,RiderCodRemittance,Seller,SellerOrder,User};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

/** Explicit, Admin-only source registration. No automatic hooks or funding writer. */
class CustodySourceService
{
    public function inspect(LogisticsCodRemittanceItem $item,User $actor): array
    {
        return $this->verified($item,$actor,fn($candidate)=>$candidate+[
            'can_allocate'=>false,'blocker'=>'UNRECONCILED_SOURCE_LIABILITIES',
        ]);
    }

    public function record(LogisticsCodRemittanceItem $item,User $actor): CustodySource
    {
        return $this->verified($item,$actor,function($candidate)use($actor){
            if(!Schema::hasTable('custody_sources'))$this->invalid('The reviewed custody registry schema is not deployed.');
            $existing=CustodySource::where('source_identity',$candidate['source_identity'])->lockForUpdate()->first();
            if($existing){
                foreach(['seller_id','order_id','seller_order_id','shipment_id','rider_cod_remittance_id','logistics_cod_remittance_id','logistics_cod_remittance_item_id','accepted_by','accepted_minor','provenance_hash']as$field)
                    if((string)$existing->getRawOriginal($field)!==(string)$candidate[$field])$this->invalid('Recorded custody evidence changed; explicit reconciliation is required.');
                if($existing->state!=='UNRECONCILED'||$existing->allocatable_minor!==0||$existing->committed_minor!==0||$existing->consumed_minor!==0||$existing->unreconciled_minor!==$existing->accepted_minor)
                    $this->invalid('Recorded source state is inconsistent; no automatic repair is allowed.');
                return $existing;
            }
            $source=new CustodySource;$source->forceFill($candidate+['recorded_by'=>$actor->id,'recorded_at'=>now()])->save();return $source;
        });
    }

    private function verified(LogisticsCodRemittanceItem $item,User $actor,callable $operation): mixed
    {
        $actor=User::find($actor->getKey());
        abort_unless($actor&&$actor->isAdmin()&&$actor->status==='active'&&!$actor->is_suspended,403);
        return DB::transaction(function()use($item,$operation){
            $reference=LogisticsCodRemittanceItem::with('riderRemittance.sellerOrder')->findOrFail($item->getKey());
            $soRef=$reference->riderRemittance?->sellerOrder;
            if(!$soRef)$this->invalid('Original purchase linkage is missing.');
            // Same Seller -> parent -> purchase -> remittance order as existing financial writers.
            $seller=Seller::whereKey($soRef->seller_id)->lockForUpdate()->firstOrFail();
            $order=Order::whereKey($soRef->order_id)->lockForUpdate()->firstOrFail();
            $so=SellerOrder::whereKey($soRef->id)->lockForUpdate()->firstOrFail();
            $shipment=$so->shipment()->lockForUpdate()->first();$lines=$so->items()->orderBy('id')->lockForUpdate()->get();
            $batch=LogisticsCodRemittance::whereKey($reference->logistics_cod_remittance_id)->lockForUpdate()->firstOrFail();
            $upstream=LogisticsCodRemittanceItem::whereKey($reference->id)->lockForUpdate()->firstOrFail();
            $receipt=RiderCodRemittance::whereKey($upstream->rider_cod_remittance_id)->lockForUpdate()->firstOrFail();
            if((int)$so->seller_id!==(int)$seller->id||(int)$so->order_id!==(int)$order->id||!$shipment
                ||(int)$upstream->logistics_cod_remittance_id!==(int)$batch->id||(int)$receipt->seller_order_id!==(int)$so->id
                ||(int)$receipt->shipment_id!==(int)$shipment->id||(int)$receipt->logistics_provider_id!==(int)$batch->logistics_provider_id
                ||$receipt->status!=='ACCEPTED'||$batch->status!=='ACCEPTED'||!$batch->reviewed_at||!$receipt->reviewed_at||!$batch->submitted_at||!$receipt->submitted_at)
                $this->invalid('Accepted, correctly linked upstream settlement evidence is required.');
            $providerUserId=$batch->provider?->user_id;$acceptedBy=User::find($batch->reviewed_by);
            if(!$acceptedBy?->isAdmin()||!$providerUserId||(int)$receipt->reviewed_by!==(int)$providerUserId)$this->invalid('Settlement acceptance actor linkage requires reconciliation.');
            foreach([[$batch,'ADMIN_ACCEPTED',$batch->reviewed_by],[$batch,'LOGISTICS_SUBMITTED',$providerUserId],[$receipt,'LOGISTICS_ACCEPTED',$receipt->reviewed_by],[$receipt,'RIDER_SUBMITTED',$receipt->rider?->user_id]]as[$subject,$action,$actorId]){
                if(!$actorId||DB::table('cod_remittance_audits')->where('subject_type',get_class($subject))->where('subject_id',$subject->id)->where('action',$action)->where('actor_id',$actorId)->count()!==1)
                    $this->invalid('An authorized remittance acceptance audit is missing.');
            }
            $so->setRelation('seller',$seller)->setRelation('order',$order)->setRelation('shipment',$shipment)->setRelation('items',$lines);
            try{$components=app(ReturnRefundService::class)->codCustodySnapshot($so);}catch(ValidationException $e){$this->invalid('Purchase, collection, discount or shipping evidence does not reconcile.');}
            $amount=$this->minor($upstream->getRawOriginal('amount_minor'));
            if($amount<1||$amount!==$components['collected_minor']||$this->minor($receipt->getRawOriginal('accepted_minor'))!==$amount||$this->minor($receipt->getRawOriginal('submitted_minor'))!==$amount)
                $this->invalid('Accepted source amounts do not reconcile.');
            // A batch cannot hide an unexplained difference or duplicate receipt.
            $batchItems=$batch->items()->orderBy('id')->lockForUpdate()->get();$sum=0;$ids=[];
            foreach($batchItems as$line){$value=$this->minor($line->getRawOriginal('amount_minor'));if($value>PHP_INT_MAX-$sum)$this->invalid('Source amount exceeds supported precision.');$sum+=$value;$ids[]=(int)$line->rider_cod_remittance_id;}
            if(count($ids)!==count(array_unique($ids))||$sum!==$this->minor($batch->getRawOriginal('accepted_minor'))||$sum!==$this->minor($batch->getRawOriginal('submitted_minor'))||$sum!==$this->minor($batch->getRawOriginal('expected_minor')))
                $this->invalid('Accepted upstream batch does not reconcile.');
            $candidate=['source_identity'=>'COD-UPSTREAM-ITEM-'.$upstream->id,'kind'=>'SIMULATED_ACCEPTED_COD','state'=>'UNRECONCILED','currency'=>'PHP',
                'seller_id'=>$seller->id,'order_id'=>$order->id,'seller_order_id'=>$so->id,'shipment_id'=>$shipment->id,
                'rider_cod_remittance_id'=>$receipt->id,'logistics_cod_remittance_id'=>$batch->id,'logistics_cod_remittance_item_id'=>$upstream->id,
                'accepted_by'=>$batch->reviewed_by,'accepted_at'=>$batch->reviewed_at->format('Y-m-d H:i:s'),
                'accepted_minor'=>$amount,'unreconciled_minor'=>$amount,'allocatable_minor'=>0,'committed_minor'=>0,'consumed_minor'=>0,'components'=>$components];
            $candidate['provenance_hash']=hash('sha256',json_encode($candidate,JSON_THROW_ON_ERROR));
            // No assumption of zero historical payouts, settled shipping, reserve or recovery.
            return $operation($candidate);
        },3);
    }

    private function minor(mixed $value): int
    {
        $amount=filter_var($value,FILTER_VALIDATE_INT);
        if($amount===false||$amount<0)$this->invalid('Nonnegative integer-centavo evidence is required.');return $amount;
    }
    private function invalid(string $message): never{throw ValidationException::withMessages(['custody_source'=>$message]);}
}
