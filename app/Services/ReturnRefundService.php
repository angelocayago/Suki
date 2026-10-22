<?php
namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\ReturnRefundEvent;
use App\Models\ReturnRefundRequest;
use App\Models\User;
use App\Models\SellerWalletEntry;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Item-scoped return workflow.  Cash custody is deliberately never mutated here. */
class ReturnRefundService {
    public function submit(User $buyer, OrderItem $item, array $data): ReturnRefundRequest {
        return DB::transaction(function () use ($buyer,$item,$data) {
            $item=OrderItem::query()->with('sellerOrder.order','sellerOrder.seller')->lockForUpdate()->findOrFail($item->id);
            $sellerOrder=$item->sellerOrder; abort_unless($sellerOrder && (int)$sellerOrder->order->buyer_id===(int)$buyer->id,403);
            $status=$sellerOrder->workflow_status instanceof OrderStatus ? $sellerOrder->workflow_status->value : (string)$sellerOrder->workflow_status;
            if ($status!==OrderStatus::Delivered->value && $status!==OrderStatus::Completed->value) throw ValidationException::withMessages(['item'=>'Only delivered merchandise may be requested.']);
            if ((int)$data['quantity']<1 || (int)$data['quantity']>(int)$item->quantity) throw ValidationException::withMessages(['quantity'=>'Invalid return quantity.']);
            if (ReturnRefundRequest::query()->where('order_item_id',$item->id)->whereIn('status',['SUBMITTED','SELLER_RESPONDED','UNDER_REVIEW'])->exists()) throw ValidationException::withMessages(['item'=>'An active request already exists for this item.']);
            $r=ReturnRefundRequest::create(['buyer_id'=>$buyer->id,'order_id'=>$sellerOrder->order_id,'seller_order_id'=>$sellerOrder->id,'order_item_id'=>$item->id,'seller_id'=>$sellerOrder->seller_id,'type'=>$data['type'],'status'=>'SUBMITTED','quantity'=>$data['quantity'],'buyer_reason'=>$data['reason'],'buyer_description'=>$data['description']]);
            $this->event($r,$buyer,'SUBMITTED'); app(DomainNotificationService::class)->afterCommit($sellerOrder->seller->user_id,'Return/refund request','A buyer submitted a request.',['return_refund_request_id'=>$r->id]); return $r;
        },3);
    }
    public function sellerRespond(ReturnRefundRequest $request, User $seller, array $data): ReturnRefundRequest { return DB::transaction(function()use($request,$seller,$data){$r=ReturnRefundRequest::query()->with('seller')->lockForUpdate()->findOrFail($request->id); abort_unless((int)$r->seller->user_id===(int)$seller->id,403); if($r->status!=='SUBMITTED') throw ValidationException::withMessages(['request'=>'Request is no longer awaiting seller response.']); $r->update(['status'=>'SELLER_RESPONDED','seller_recommendation'=>$data['recommendation'],'seller_note'=>$data['note'],'seller_responded_at'=>now()]);$this->event($r,$seller,'SELLER_RESPONDED');app(DomainNotificationService::class)->afterCommit($r->buyer_id,'Return/refund response','The seller responded; Admin review is required.',['return_refund_request_id'=>$r->id]);return $r;},3); }
    public function decide(ReturnRefundRequest $request, User $admin, string $decision, string $reason): ReturnRefundRequest { return DB::transaction(function()use($request,$admin,$decision,$reason){ abort_unless($admin->isAdmin() && $admin->status==='active' && !$admin->is_suspended,403); if(!filled($reason)) throw ValidationException::withMessages(['reason'=>'A decision reason is required.']); $r=ReturnRefundRequest::query()->with('item','sellerOrder.seller')->lockForUpdate()->findOrFail($request->id); if(in_array($r->status,['ADMIN_APPROVED','ADMIN_REJECTED'],true)) return $r; if(!in_array($r->status,['SUBMITTED','SELLER_RESPONDED'],true)) throw ValidationException::withMessages(['request'=>'Request cannot be finalized.']); if($decision==='REJECTED'){ $r->update(['status'=>'ADMIN_REJECTED','admin_decision'=>'REJECTED','admin_reason'=>trim($reason),'reviewed_by'=>$admin->id,'decided_at'=>now()]); $this->event($r,$admin,'ADMIN_REJECTED'); } else { $calculation=app(ReturnRefundAllocationService::class)->calculate($r->item,(int)$r->quantity); DB::table('return_refund_accounting')->insert(['return_refund_request_id'=>$r->id,'merchandise_minor'=>$calculation['gross_merchandise_minor'],'seller_adjustment_minor'=>$calculation['seller_adjustment_minor'],'commission_reversal_minor'=>$calculation['commission_reversed_minor'],'seller_voucher_minor'=>$calculation['seller_funded_discount_minor'],'platform_voucher_minor'=>$calculation['platform_funded_discount_minor'],'refund_status'=>'REFUND_DUE','created_at'=>now(),'updated_at'=>now()]); SellerWalletEntry::create(['seller_id'=>$r->seller_id,'seller_order_id'=>$r->seller_order_id,'type'=>'REFUND_ADJUSTMENT','status'=>'AVAILABLE','amount_minor'=>-$calculation['seller_adjustment_minor'],'reference'=>'RR-'.$r->id,'description'=>'Approved refund adjustment; refund remains due to buyer']); $r->update(['status'=>'ADMIN_APPROVED','admin_decision'=>'APPROVED','admin_reason'=>trim($reason),'reviewed_by'=>$admin->id,'decided_at'=>now(),'refund_minor'=>$calculation['buyer_refund_due_minor']]); $this->event($r,$admin,'ADMIN_APPROVED'); } app(DomainNotificationService::class)->afterCommit($r->buyer_id,'Return/refund decision','Your return/refund request was '.strtolower($decision).'.',['return_refund_request_id'=>$r->id]); app(DomainNotificationService::class)->afterCommit($r->seller->user_id,'Return/refund decision','Admin finalized a return/refund request.',['return_refund_request_id'=>$r->id]); return $r->fresh();},3); }
    private function event(ReturnRefundRequest $r, User $actor,string $action):void { ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>$action]); }
}
