<?php
namespace App\Services;
use App\Models\{BuyerRefundFunding,ReturnRefundRequest,SellerWalletEntry,User};
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

    public function credit(ReturnRefundRequest $request,User $actor): never{
        abort_unless($actor->isAdmin() && $actor->status==='active' && !$actor->is_suspended,403);
        if(config('suki_wallet.refund_credit_enabled',false)!==true)$this->invalid('Refund wallet credit is disabled pending verified funding reconciliation.');
        // A label/status/actor/reference is NOT authoritative evidence of an unspent allocation.
        // No trusted custody/reserve/recovery allocator exists in the current project.
        $this->invalid('No verified unconsumed funding allocation is available. The refund remains pending; no wallet credit was posted.');
    }
    private function invalid(string $message): never{throw ValidationException::withMessages(['wallet_funding'=>$message]);}
}
