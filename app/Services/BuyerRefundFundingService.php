<?php
namespace App\Services;
use App\Models\{BuyerRefundFunding,BuyerWalletEntry,Order,OrderItem,ReturnRefundRequest,Seller,SellerOrder,SellerWalletEntry,User};
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

class BuyerRefundFundingService {
    /** Only called within a NEW Seller decision's existing purchase/request locks. */
    public function recordPending(ReturnRefundRequest $request,User $seller): void{
        // Backward-compatible deployment: never activate wallets or backfill old approvals.
        if(!Schema::hasTable('buyer_refund_fundings'))return;
        if(!(new BuyerWalletService)->schemaReady() || DB::transactionLevel()<1)$this->invalid('Buyer refund funding schema/transaction is unavailable.');
        $request->loadMissing(['item','seller']);
        abort_unless($request->isSellerReview() && $request->status==='ADMIN_APPROVED' && (int)$request->seller->user_id===(int)$seller->id && (int)$request->reviewed_by===(int)$seller->id,403);
        $quote=app(ReturnRefundAllocationService::class)->calculate($request->item,(int)$request->quantity);
        $entry=SellerWalletEntry::where('return_refund_request_id',$request->id)->where('type','REFUND_ADJUSTMENT')->lockForUpdate()->first();
        $accounting=DB::table('return_refund_accounting')->where('return_refund_request_id',$request->id)->first();
        if(!$entry || !$accounting || (int)$entry->seller_id!==(int)$request->seller_id || (int)$entry->seller_order_id!==(int)$request->seller_order_id || $entry->status!=='AVAILABLE' || $entry->amount_minor!==-$quote['seller_adjustment_minor'] || (int)$request->refund_minor!==$quote['buyer_refund_due_minor'] || (int)$accounting->seller_adjustment_minor!==$quote['seller_adjustment_minor'])$this->invalid('Seller refund obligation cannot be reconciled.');
        if(BuyerRefundFunding::whereKey($request->id)->exists())$this->invalid('A funding record already exists for this new decision.');
        $funding=new BuyerRefundFunding;
        $funding->forceFill(['return_refund_request_id'=>$request->id,'buyer_id'=>$request->buyer_id,'seller_id'=>$request->seller_id,'seller_wallet_entry_id'=>$entry->id,'source_identity'=>'BUYER-REFUND-RR-'.$request->id,
            'status'=>BuyerRefundFunding::AWAITING,'buyer_refund_minor'=>$quote['buyer_refund_due_minor'],'merchandise_minor'=>$quote['gross_merchandise_minor'],'seller_adjustment_minor'=>$quote['seller_adjustment_minor'],'commission_reversal_minor'=>$quote['commission_reversed_minor'],'seller_discount_minor'=>$quote['seller_funded_discount_minor'],'platform_discount_minor'=>$quote['platform_funded_discount_minor']])->save();
        // Approval, Seller debit and pending funding metadata share one transaction.
        // No remittance balance, reserve or recovery is presumed to have been collected.
    }

    /** A locked obligation assessment, NOT a custody verification or a reservation. */
    public function assess(ReturnRefundRequest $request, User $actor): array
    {
        $actor=User::find($actor->getKey());
        abort_unless($actor && $actor->isAdmin() && $actor->status==='active' && !$actor->is_suspended,403);
        if(!(new BuyerWalletService)->schemaReady())$this->invalid('Refund funding schema is unavailable.');

        return DB::transaction(function()use($request){
            $reference=ReturnRefundRequest::findOrFail($request->getKey());
            // Same Seller-before-purchase order used by payouts and refund decisions.
            $seller=Seller::whereKey($reference->seller_id)->lockForUpdate()->firstOrFail();
            $order=Order::whereKey($reference->order_id)->lockForUpdate()->firstOrFail();
            $sellerOrder=SellerOrder::whereKey($reference->seller_order_id)->lockForUpdate()->firstOrFail();
            $item=OrderItem::whereKey($reference->order_item_id)->lockForUpdate()->firstOrFail();
            $r=ReturnRefundRequest::whereKey($reference->id)->lockForUpdate()->firstOrFail();
            if(!$r->isSellerReview() || $r->status!=='ADMIN_APPROVED' || $r->seller_recommendation!=='APPROVE'
                || (int)$r->reviewed_by!==(int)$seller->user_id || !$r->decided_at
                || (int)$r->buyer_id!==(int)$order->buyer_id || (int)$r->order_id!==(int)$order->id
                || (int)$r->seller_id!==(int)$seller->id || (int)$sellerOrder->seller_id!==(int)$seller->id
                || (int)$sellerOrder->order_id!==(int)$order->id || (int)$r->seller_order_id!==(int)$sellerOrder->id
                || (int)$r->order_item_id!==(int)$item->id || (int)$item->order_id!==(int)$order->id
                || (int)$item->seller_order_id!==(int)$sellerOrder->id)
                $this->invalid('Only an approved, correctly owned Seller refund can be assessed.');
            $quote=app(ReturnRefundAllocationService::class)->calculate($item,(int)$r->quantity);
            $entry=SellerWalletEntry::where('return_refund_request_id',$r->id)->where('type','REFUND_ADJUSTMENT')->lockForUpdate()->first();
            $accounting=DB::table('return_refund_accounting')->where('return_refund_request_id',$r->id)->lockForUpdate()->first();
            $funding=BuyerRefundFunding::whereKey($r->id)->lockForUpdate()->first();
            if(!$entry || !$accounting || !$funding)$this->invalid('An existing reconciled refund obligation is required; historical refunds are not backfilled.');
            if((int)$entry->seller_id!==(int)$seller->id || (int)$entry->seller_order_id!==(int)$sellerOrder->id
                || $entry->status!=='AVAILABLE' || $entry->amount_minor!==-$quote['seller_adjustment_minor']
                || $entry->payout_id || $entry->source_wallet_entry_id || $accounting->refund_status!=='REFUND_DUE'
                || (int)$r->refund_minor!==$quote['buyer_refund_due_minor']
                || (int)$funding->buyer_id!==(int)$r->buyer_id || (int)$funding->seller_id!==(int)$seller->id
                || (int)$funding->seller_wallet_entry_id!==(int)$entry->id || $funding->source_identity!=='BUYER-REFUND-RR-'.$r->id)
                $this->invalid('Refund ownership or Seller obligation does not reconcile.');
            foreach(['merchandise_minor'=>'gross_merchandise_minor','seller_adjustment_minor'=>'seller_adjustment_minor',
                'commission_reversal_minor'=>'commission_reversed_minor','seller_voucher_minor'=>'seller_funded_discount_minor',
                'platform_voucher_minor'=>'platform_funded_discount_minor']as$field=>$component){
                if(filter_var($accounting->$field,FILTER_VALIDATE_INT)!==$quote[$component])$this->invalid('Refund accounting components do not reconcile.');
            }
            foreach(['buyer_refund_minor'=>'buyer_refund_due_minor','merchandise_minor'=>'gross_merchandise_minor',
                'seller_adjustment_minor'=>'seller_adjustment_minor','commission_reversal_minor'=>'commission_reversed_minor',
                'seller_discount_minor'=>'seller_funded_discount_minor','platform_discount_minor'=>'platform_funded_discount_minor']as$field=>$component){
                if(filter_var($funding->getRawOriginal($field),FILTER_VALIDATE_INT)!==$quote[$component])$this->invalid('Funding obligation components do not reconcile.');
            }
            // No writer in this phase can produce an authoritative READY/CREDITED record.
            if($funding->status!==BuyerRefundFunding::AWAITING || BuyerWalletEntry::where('return_refund_request_id',$r->id)->exists())
                $this->invalid('This funding state cannot be verified by the current source allocator.');
            foreach(['funded_minor','custody_funded_minor','reserve_funded_minor','seller_recovery_funded_minor']as$field)
                if(filter_var($funding->getRawOriginal($field),FILTER_VALIDATE_INT)!==0)$this->invalid('Unverified funding amounts cannot be allocated.');
            foreach(['verified_by','verified_at','consumed_at','funding_source_type','funding_source_reference']as$field)
                if($funding->$field!==null)$this->invalid('Unverified source claims cannot be accepted as funding.');

            return ['return_refund_request_id'=>$r->id,'status'=>BuyerRefundFunding::AWAITING,
                'buyer_refund_minor'=>$quote['buyer_refund_due_minor'],'seller_adjustment_minor'=>$quote['seller_adjustment_minor'],
                'commission_reversal_minor'=>$quote['commission_reversed_minor'],'verified_allocatable_minor'=>0,
                'can_allocate'=>false,'can_credit'=>false,'blocker'=>'MISSING_EXCLUSIVE_FUNDING_SOURCE'];
        },3);
    }

    /** No browser-supplied reference, manual status, reserve or remittance can bypass this. */
    public function allocate(ReturnRefundRequest $request, User $actor): BuyerRefundFunding
    {
        if(Schema::hasTable('simulation_settlements') && DB::table('simulation_settlements')->where('seller_order_id',$request->seller_order_id)->exists())
            return app(SimulationSettlementService::class)->allocate($request,$actor);
        $this->assess($request,$actor);
        $this->invalid('No verified unencumbered funding source is available. No funds were reserved; the refund remains awaiting funding.');
    }

    public function credit(ReturnRefundRequest $request,User $actor): BuyerWalletEntry{
        $reference=ReturnRefundRequest::findOrFail($request->getKey());
        if(Schema::hasTable('simulation_settlements') && DB::table('simulation_settlements')->where('seller_order_id',$reference->seller_order_id)->exists())
            return app(SimulationSettlementService::class)->creditRefund($reference,$actor);
        $this->assess($request,$actor);
        if(config('suki_wallet.refund_credit_enabled',false)!==true)$this->invalid('Refund wallet credit is disabled pending verified funding reconciliation.');
        // A label/status/actor/reference is NOT authoritative evidence of an unspent allocation.
        // No trusted custody/reserve/recovery allocator exists in the current project.
        $this->invalid('No verified unconsumed funding allocation is available. The refund remains pending; no wallet credit was posted.');
    }
    private function invalid(string $message): never{throw ValidationException::withMessages(['wallet_funding'=>$message]);}
}
