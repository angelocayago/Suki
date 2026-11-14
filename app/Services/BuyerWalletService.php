<?php
namespace App\Services;
use App\Models\{BuyerWalletEntry,User};
use Illuminate\Support\Facades\Schema;

/** Read-only foundation. There is deliberately no top-up, debit or credit API. */
class BuyerWalletService {
    public function schemaReady(): bool{
        return Schema::hasTable('buyer_wallets') && Schema::hasColumns('buyer_wallet_entries',['buyer_id','type','status','amount_minor','order_id','return_refund_request_id','source_identity','created_at'])
            && Schema::hasColumns('buyer_refund_fundings',['return_refund_request_id','buyer_id','seller_wallet_entry_id','buyer_refund_minor','status','funded_minor']);
    }
    public function summary(User $buyer): array{
        abort_unless($buyer->isBuyer(),403);
        $raw=BuyerWalletEntry::where('buyer_id',$buyer->id)->where('status','POSTED')->sum('amount_minor');
        $balance=filter_var($raw,FILTER_VALIDATE_INT);
        if($balance===false)throw \Illuminate\Validation\ValidationException::withMessages(['wallet'=>'Wallet balance cannot be verified. Contact support.']);
        return ['balance_minor'=>$balance,'available_minor'=>max(0,$balance)];
    }
    // The flag is a rollout prerequisite, not permission to bypass an unfinished payment contract.
    public function checkoutAvailable(): bool{return false;}
}
