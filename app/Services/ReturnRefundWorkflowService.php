<?php
namespace App\Services;

use App\Models\{LogisticsHub,LogisticsProvider,OrderItem,ProductVariant,ReturnRefundRequest,ReturnRefundWorkflow,ReturnRefundEvent,Seller,User};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

class ReturnRefundWorkflowService
{
    public function schemaReady(): bool
    {
        if(!Schema::hasTable('return_refund_workflows') || !Schema::hasColumns('return_refund_workflows',['id','created_at','updated_at','return_refund_request_id','destination','parcel_status','return_address','courier','tracking_number','shipped_on','received_at','received_by','inspection_result','inspection_note']))return false;
        if(!collect(Schema::getIndexes('return_refund_workflows'))->contains(fn($i)=>$i['unique'] && $i['columns']===['return_refund_request_id']))return false;
        $fks=collect(Schema::getForeignKeys('return_refund_workflows'));
        foreach(['return_refund_request_id'=>'return_refund_requests','received_by'=>'users'] as $column=>$table){
            if(!$fks->contains(fn($fk)=>$fk['columns']===[$column] && $fk['foreign_table']===$table && $fk['foreign_columns']===['id'] && strtolower($fk['on_delete'])==='restrict'))return false;
        }
        return true;
    }
    public function sellerSchemaReady(): bool
    {
        if (!Schema::hasColumn('return_refund_requests','workflow_mode')) return false;
        if (DB::getDriverName() === 'pgsql') {
            $check = DB::selectOne("SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid='return_refund_requests'::regclass AND conname='return_refund_requests_workflow_mode_check' AND contype='c' AND convalidated");
            return $check && str_contains($check->definition, 'SELLER_REVIEW');
        }
        if (DB::getDriverName() === 'sqlite') {
            $table = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='return_refund_requests'");
            return $table && str_contains($table->sql, "'SELLER_REVIEW'");
        }
        return false;
    }

    public function available(string $mode = ReturnRefundRequest::SELLER_REVIEW): bool
    {
        if (!in_array($mode, [ReturnRefundRequest::SELLER_REVIEW, ReturnRefundRequest::ADMIN_REVIEW, ReturnRefundRequest::FINANCIAL], true)) return false;
        $flag = $mode === ReturnRefundRequest::FINANCIAL ? 'returns.backend_enabled' : 'returns.admin_review_enabled';
        return config($flag,false) === true && $this->schemaReady()
            && ($mode !== ReturnRefundRequest::SELLER_REVIEW || $this->sellerSchemaReady())
            && ($mode === ReturnRefundRequest::FINANCIAL || Schema::hasColumn('return_refund_requests','workflow_mode'))
            && app(FinancialSchemaReadiness::class)->inspect()['ready'];
    }
    private function requireAvailable(): void
    {
        if (!$this->available(ReturnRefundRequest::FINANCIAL)) throw ValidationException::withMessages(['request'=>'Return/refund actions are unavailable pending schema and financial activation review.']);
    }
    public function hubSchemaReadyForAuthorization(): bool
    {
        return Schema::hasColumns('return_refund_workflows', ['logistics_provider_id','return_hub_id','return_reference','logistics_state','hub_received_by','hub_received_at','dispatched_by','dispatched_at','seller_received_at','seller_received_by','inspected_by','inspected_at','stock_restored','stock_restored_at']);
    }
    public function returnAddress(OrderItem $item): ?string
    {
        $seller=$item->sellerOrder?->seller;$address=$seller?->pickupAddress;
        if (!$seller || $seller->status!=='approved' || !$address || (int)$address->user_id!==(int)$seller->user_id) return null;
        foreach(['line1','barangay','city','province','city_municipality_code','barangay_code'] as $field) if (!filled($address->$field)) return null;
        return implode(', ',array_filter([$address->recipient,$address->line1,$address->barangay,$address->city,$address->province,$address->postal_code]));
    }
    public function submit(User $buyer, OrderItem $item, array $data): ReturnRefundRequest
    {
        $this->requireAvailable();
        // This phase supports physical return + refund only; no implicit refund-only exception.
        if (($data['type'] ?? '') !== 'RETURN') throw ValidationException::withMessages(['type'=>'Only the return-and-refund workflow is supported.']);
        return DB::transaction(function()use($buyer,$item,$data){
            $r=app(ReturnRefundService::class)->submit($buyer,$item,$data);
            $address=$this->returnAddress($r->item);
            if (!$address) throw ValidationException::withMessages(['request'=>'The Seller must complete the approved pickup/return address before a parcel return can be requested. Contact support.']);
            ReturnRefundWorkflow::create(['return_refund_request_id'=>$r->id,'return_address'=>$address]);
            return $r;
        });
    }
    public function find(ReturnRefundRequest $r): ?ReturnRefundWorkflow
    {
        return $this->schemaReady() ? ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->first() : null;
    }
    private function locked(ReturnRefundRequest $request): array
    {
        $r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
        $w=ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->lockForUpdate()->first();
        if (!$w || !in_array($r->status,ReturnRefundRequest::ACTIVE_STATUSES,true)) throw ValidationException::withMessages(['request'=>'This request cannot accept further return details.']);
        return [$r,$w];
    }
    public function details(User $buyer,ReturnRefundRequest $request,array $data): void
    {
        $this->requireAvailable();
        DB::transaction(function()use($buyer,$request,$data){
            [$r,$w]=$this->locked($request);
            abort_unless($buyer->isBuyer() && (int)$r->buyer_id===(int)$buyer->id,403);
            if ($r->seller_recommendation!=='APPROVE' || $w->parcel_status==='RECEIVED') throw ValidationException::withMessages(['request'=>'Seller confirmation is required; received parcels cannot be edited.']);
            if ($w->parcel_status==='SHIPPED' && $data['parcel_status']!=='SHIPPED') throw ValidationException::withMessages(['parcel_status'=>'A shipped return cannot revert to shipping pending.']);
            $w->fill(['parcel_status'=>$data['parcel_status'],'courier'=>$data['parcel_status']==='SHIPPED'?$data['courier']:null,
                'tracking_number'=>$data['parcel_status']==='SHIPPED'?$data['tracking_number']:null,'shipped_on'=>$data['shipped_on'] ?? null])->save();
            ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$buyer->id,'action'=>$data['parcel_status']==='SHIPPED'?'RETURN_SHIPPED':'RETURN_DETAILS_SUBMITTED']);
            app(DomainNotificationService::class)->afterCommit($r->seller->user_id,'Return parcel update','The Buyer updated return parcel details. Review the official request.',['return_refund_request_id'=>$r->id]);
        });
    }
    public function receive(User $seller,ReturnRefundRequest $request,array $data): void
    {
        $this->requireAvailable();
        DB::transaction(function()use($seller,$request,$data){
            [$r,$w]=$this->locked($request);
            abort_unless($seller->isSeller() && (int)$r->seller->user_id===(int)$seller->id,403);
            if ($w->parcel_status!=='SHIPPED' || $r->seller_recommendation!=='APPROVE') throw ValidationException::withMessages(['request'=>'Only a confirmed, shipped return can be received once.']);
            $w->fill(['parcel_status'=>'RECEIVED','received_at'=>now(),'received_by'=>$seller->id,'inspection_result'=>$data['inspection_result'],'inspection_note'=>$data['inspection_note']])->save();
            ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$seller->id,'action'=>'RETURN_RECEIVED']);
            app(DomainNotificationService::class)->afterCommit($r->buyer_id,'Returned parcel received','Seller receipt and inspection were recorded. Admin financial review is still required.',['return_refund_request_id'=>$r->id]);
        });
    }
    public function assertApprovable(ReturnRefundRequest $r,bool $requireWorkflow=false): void
    {
        if ($r->isSimplified()) return;
        $w=$this->find($r);
        if (!$w && !$requireWorkflow) return; // historical internal accounting remains separately reviewable
        if (!$w || $r->seller_recommendation!=='APPROVE' || $w->parcel_status!=='RECEIVED'
            || !$w->received_at || (int)$w->received_by!==(int)$r->seller->user_id || !$w->inspection_result || !$w->courier || !$w->tracking_number) {
            throw ValidationException::withMessages(['request'=>'Seller confirmation, shipped parcel and Seller receipt/inspection are required before approval. Legacy requests require support review.']);
        }
    }

    public function receiveAtHub(ReturnRefundRequest $request, User $actor, LogisticsProvider $provider, LogisticsHub $hub, string $reference, ?string $note = null): ReturnRefundWorkflow
    {
        return $this->transitionHubState($request, $actor, $provider, $hub, 'AWAITING_BUYER_DROPOFF', 'RECEIVED_AT_RETURN_HUB', $reference, $note);
    }

    public function dispatchToSeller(ReturnRefundRequest $request, User $actor, LogisticsProvider $provider, LogisticsHub $hub, ?string $note = null): ReturnRefundWorkflow
    {
        return $this->transitionHubState($request, $actor, $provider, $hub, 'RECEIVED_AT_RETURN_HUB', 'IN_TRANSIT_TO_SELLER', null, $note);
    }

    private function transitionHubState(ReturnRefundRequest $request, User $actor, LogisticsProvider $provider, LogisticsHub $hub, string $from, string $to, ?string $reference, ?string $note): ReturnRefundWorkflow
    {
        return DB::transaction(function () use ($request,$actor,$provider,$hub,$from,$to,$reference,$note) {
            if (! $this->hubSchemaReadyForAuthorization()) throw ValidationException::withMessages(['request'=>'Return hub workflow is unavailable pending migration rollout.']);
            abort_unless($actor->hasRole('logistics') && $actor->status === 'active' && ! $actor->is_suspended && (int) $provider->user_id === (int) $actor->id && $provider->status === 'approved', 403);
            $r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);$w=ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'RETURN_AUTHORIZED' || (int)$w->logistics_provider_id !== (int)$provider->id || (int)$w->return_hub_id !== (int)$hub->id || ! $hub->is_active || (int)$hub->logistics_provider_id !== (int)$provider->id) throw ValidationException::withMessages(['request'=>'This return is not authorized for this provider and barangay hub.']);
            if ($w->logistics_state === $to) return $w;
            if ($w->logistics_state !== $from || ($reference !== null && ! hash_equals((string)$w->return_reference,trim($reference)))) throw ValidationException::withMessages(['state'=>'Invalid return logistics transition or reference.']);
            $updates=['logistics_state'=>$to];
            if($to==='RECEIVED_AT_RETURN_HUB')$updates+=['hub_received_by'=>$actor->id,'hub_received_at'=>now()];
            if($to==='IN_TRANSIT_TO_SELLER')$updates+=['dispatched_by'=>$actor->id,'dispatched_at'=>now()];
            $w->update($updates);ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>$to,'data'=>['from'=>$from,'to'=>$to,'hub_id'=>$hub->id,'note'=>trim((string)$note)]]);return $w->fresh();
        });
    }

    public function receiveBySeller(ReturnRefundRequest $request, User $actor, ?string $note = null): ReturnRefundWorkflow
    {
        return DB::transaction(function()use($request,$actor,$note){if(!$this->hubSchemaReadyForAuthorization())throw ValidationException::withMessages(['request'=>'Return hub workflow is unavailable pending migration rollout.']);$r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);$w=ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->lockForUpdate()->firstOrFail();$seller=Seller::findOrFail($r->seller_id);abort_unless($actor->isSeller() && $actor->status==='active'&&!$actor->is_suspended&&(int)$seller->user_id===(int)$actor->id,403);if($w->logistics_state==='RECEIVED_BY_SELLER')return $w;if($r->status!=='RETURN_AUTHORIZED'||$w->logistics_state!=='IN_TRANSIT_TO_SELLER')throw ValidationException::withMessages(['state'=>'Return must be dispatched by its hub before seller receipt.']);$w->update(['logistics_state'=>'RECEIVED_BY_SELLER','seller_received_at'=>now(),'seller_received_by'=>$actor->id]);ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>'RECEIVED_BY_SELLER','data'=>['from'=>'IN_TRANSIT_TO_SELLER','to'=>'RECEIVED_BY_SELLER','note'=>trim((string)$note)]]);return $w->fresh();});
    }

    public function inspect(ReturnRefundRequest $request, User $actor, string $outcome, string $note): ReturnRefundWorkflow
    {
        return DB::transaction(function()use($request,$actor,$outcome,$note){if(!$this->hubSchemaReadyForAuthorization())throw ValidationException::withMessages(['request'=>'Return hub workflow is unavailable pending migration rollout.']);$r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);$w=ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->lockForUpdate()->firstOrFail();$seller=Seller::findOrFail($r->seller_id);abort_unless($actor->isSeller() && $actor->status==='active'&&!$actor->is_suspended&&(int)$seller->user_id===(int)$actor->id,403);if(!in_array($outcome,['RESTOCKABLE','DAMAGED','INCOMPLETE','WRONG_ITEM','REJECTED_UNDER_POLICY'],true)||!filled($note))throw ValidationException::withMessages(['inspection'=>'A valid inspection outcome and note are required.']);if($w->logistics_state==='INSPECTED')return $w;if($r->status!=='RETURN_AUTHORIZED'||$w->logistics_state!=='RECEIVED_BY_SELLER')throw ValidationException::withMessages(['state'=>'Seller receipt is required before inspection.']);if($outcome==='RESTOCKABLE'&&!$w->stock_restored){ $item=OrderItem::lockForUpdate()->findOrFail($r->order_item_id);$variant=ProductVariant::withTrashed()->lockForUpdate()->findOrFail($item->product_variant_id);$variant->increment('stock',(int)$r->quantity);$w->stock_restored=true;$w->stock_restored_at=now();}$w->fill(['logistics_state'=>'INSPECTED','inspection_result'=>$outcome,'inspection_note'=>$note,'inspected_by'=>$actor->id,'inspected_at'=>now()])->save();ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>'INSPECTED','data'=>['from'=>'RECEIVED_BY_SELLER','to'=>'INSPECTED','outcome'=>$outcome,'note'=>$note,'stock_restored'=>$w->stock_restored]]);return $w->fresh();});
    }
}
