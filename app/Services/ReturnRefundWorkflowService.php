<?php
namespace App\Services;

use App\Models\{OrderItem,ReturnRefundRequest,ReturnRefundWorkflow,ReturnRefundEvent,User};
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
    public function available(string $mode = ReturnRefundRequest::ADMIN_REVIEW): bool
    {
        if (!in_array($mode, [ReturnRefundRequest::ADMIN_REVIEW, ReturnRefundRequest::FINANCIAL], true)) return false;
        $flag = $mode === ReturnRefundRequest::ADMIN_REVIEW ? 'returns.admin_review_enabled' : 'returns.backend_enabled';
        return config($flag,false) === true && $this->schemaReady()
            && ($mode === ReturnRefundRequest::FINANCIAL || Schema::hasColumn('return_refund_requests','workflow_mode'))
            && app(FinancialSchemaReadiness::class)->inspect()['ready'];
    }
    private function requireAvailable(): void
    {
        if (!$this->available(ReturnRefundRequest::FINANCIAL)) throw ValidationException::withMessages(['request'=>'Return/refund actions are unavailable pending schema and financial activation review.']);
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
            $w->fill(['destination'=>array_intersect_key($data,array_flip(['method','holder','account','bank'])),
                'parcel_status'=>$data['parcel_status'],'courier'=>$data['parcel_status']==='SHIPPED'?$data['courier']:null,
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
        if ($r->isAdminReview()) return;
        $w=$this->find($r);
        if (!$w && !$requireWorkflow) return; // historical internal accounting remains separately reviewable
        if (!$w || $r->seller_recommendation!=='APPROVE' || $w->parcel_status!=='RECEIVED' || !$w->destination
            || !$w->received_at || (int)$w->received_by!==(int)$r->seller->user_id || !$w->inspection_result || !$w->courier || !$w->tracking_number) {
            throw ValidationException::withMessages(['request'=>'Seller confirmation, private refund destination, shipped parcel and Seller receipt/inspection are required before approval. Legacy requests require support review.']);
        }
    }
}
