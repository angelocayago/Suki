<?php

namespace App\Services;

use App\Models\{BuyerWallet, BuyerWalletEntry, DeliveryEvent, Order, Payment, ReturnRefundRequest, Seller, SellerOrder, SellerWalletEntry, Shipment, User};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

final class AcademicWalletPaymentService
{
    public function enabled(): bool
    {
        return config('suki_wallet.enabled')===true && config('suki_wallet.checkout_enabled')===true
            && config('simulation_settlement.enabled')===true && app(AcademicWalletPaymentContract::class)->ready();
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['payment_method'=>$message]);
    }

    /** One serialization boundary shared with refund credits and simulation payouts. */
    private function operation(callable $callback): mixed
    {
        if (!app(AcademicWalletPaymentContract::class)->ready()) $this->invalid('Wallet payment protection is not installed.');
        return DB::transaction(function () use ($callback) {
            $mutex=DB::table('simulation_settlement_mutex')->where('id',1)->lockForUpdate()->first();
            if (!$mutex || (int)$mutex->operation_open!==0) $this->invalid('Wallet payment coordination cannot be nested.');
            DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>1]);
            $result=$callback();
            app(SimulationWalletCreditContract::class)->verify();
            DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>0]);
            app(AcademicWalletPaymentContract::class)->verify();
            return $result;
        },1);
    }

    /** Read-only eligible backing pool; unclassified ledger balances cannot fund checkout. */
    public function available(User $buyer): int
    {
        if (!app(AcademicWalletPaymentContract::class)->ready()) return 0;
        $rows=$this->backings($buyer->id,false);$available=0;
        foreach ($rows as $row) {
            $unused=$this->unused($row);
            if ($unused<0 || $unused>PHP_INT_MAX-$available) $this->invalid('The wallet backing balance cannot be verified.');
            $available+=$unused;
        }
        $ledger=filter_var(BuyerWalletEntry::where('buyer_id',$buyer->id)->where('status','POSTED')->sum('amount_minor'),FILTER_VALIDATE_INT);
        if ($ledger===false) $this->invalid('The wallet ledger exceeds supported precision.');
        return min($available,max(0,$ledger));
    }

    private function backings(int $buyerId,bool $lock)
    {
        $q=DB::table('simulation_wallet_backings')->where('buyer_id',$buyerId)->orderBy('simulation_commitment_id');
        return ($lock?$q->lockForUpdate():$q)->get();
    }

    private function unused(object $backing): int
    {
        $used=DB::table('academic_wallet_allocations as a')->leftJoin('academic_wallet_reversals as r','r.payment_id','=','a.payment_id')
            ->where('a.simulation_commitment_id',$backing->simulation_commitment_id)->whereNull('r.payment_id')->sum('a.amount_minor');
        $used=filter_var($used,FILTER_VALIDATE_INT);
        if ($used===false) $this->invalid('Allocated wallet value cannot be verified.');
        return (int)$backing->amount_minor-$used;
    }

    public function supportsSummary(array $summary): ?string
    {
        if (!$this->enabled()) return 'Suki Wallet checkout is not enabled.';
        $lines=collect($summary['cart']??[]);
        if ($summary['has_unavailable_items']??false) return 'Review unavailable items before paying with Suki Wallet.';
        if ($lines->isEmpty() || $lines->pluck('seller_id')->unique()->count()!==1) return 'Suki Wallet currently supports one shop per purchase. Select items from one shop.';
        if (DB::table('simulation_settlements')->where('seller_id',$lines->first()['seller_id'])->exists()) return 'Wallet payments are not supported for this shop yet.';
        if (!array_key_exists('wallet_allocation_issue',$summary) || $summary['wallet_allocation_issue']==='unsupported_earning_or_shipping') return 'This purchase is outside the supported Suki Wallet earning and shipping limits.';
        if ($summary['wallet_allocation_issue']==='additional_subsidy_required') return 'This voucher needs additional funding that Suki Wallet does not support. Choose Cash on Delivery.';
        return null;
    }

    public function checkout(User $buyer,?string $reference,callable $native): Order
    {
        if (!$this->enabled()) $this->invalid('Suki Wallet checkout is disabled or its protection is unavailable.');
        if (!$reference || strlen($reference)>100) $this->invalid('A stable checkout identity is required for a wallet payment.');
        return $this->operation(function () use ($buyer,$reference,$native) {
            $buyer=User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            abort_unless($buyer->isBuyer() && $buyer->status==='active' && !$buyer->is_suspended,403);
            if ($existing=Order::where('buyer_id',$buyer->id)->where('order_number',$reference)->first()) {
                if ($existing->payment_method!=='wallet' || !DB::table('academic_wallet_payments')->where('order_id',$existing->id)->exists()) $this->invalid('Checkout identity belongs to a different payment.');
                return $existing->fresh(['sellerOrders.shipment','items']);
            }
            if (!BuyerWallet::whereKey($buyer->id)->lockForUpdate()->first()) $this->invalid('Insufficient Suki Wallet balance, including shipping.');
            $backings=$this->backings($buyer->id,true);
            foreach ($backings as $b) app(SimulationSettlementService::class)->verifyWalletSource((int)$b->simulation_settlement_id);
            app(SimulationWalletCreditContract::class)->verify();
            $available=$this->available($buyer);
            $order=$native();$order->load(['sellerOrders.items','sellerOrders.shipment','items']);
            if ($order->sellerOrders->count()!==1) $this->invalid('Suki Wallet currently supports one shop per purchase.');
            $so=$order->sellerOrders->sole();
            Seller::whereKey($so->seller_id)->lockForUpdate()->firstOrFail();
            if (DB::table('simulation_settlements')->where('seller_id',$so->seller_id)->exists()) $this->invalid('Wallet payments are not supported for this shop yet.');
            $total=(int)$order->total_minor;$shipping=(int)$so->shipping_fee_minor;
            $seller=(int)$so->subtotal_minor-(int)$so->commission_minor-(int)$so->items->sum('seller_funded_discount_minor');
            $platform=$total-$shipping-$seller;
            if ($total<1 || $seller<1 || $shipping!==5000 || $platform<0) $this->invalid('This discount or shipping allocation requires unsupported additional funding.');
            if ($available<$total) $this->invalid('Insufficient Suki Wallet balance for the complete order total, including shipping.');
            $time=now()->startOfSecond();$identity='WALLET-PAY-ORDER-'.$order->id;
            $entry=new BuyerWalletEntry;$entry->forceFill(['buyer_id'=>$buyer->id,'type'=>'ORDER_PAYMENT','status'=>'POSTED','amount_minor'=>-$total,'order_id'=>$order->id,'source_identity'=>$identity,'created_at'=>$time])->save();
            $payment=Payment::create(['order_id'=>$order->id,'method'=>'wallet','amount_minor'=>$total,'status'=>'PAID','provider_ref'=>$identity]);
            $earning=SellerWalletEntry::create(['seller_id'=>$so->seller_id,'seller_order_id'=>$so->id,'type'=>'SALE_EARNING','status'=>'HELD','amount_minor'=>$seller,'reference'=>'SO-'.$so->id,'description'=>'Wallet-funded earning held pending completion and verified settlement']);
            $id=DB::table('academic_wallet_payments')->insertGetId(['order_id'=>$order->id,'seller_order_id'=>$so->id,'buyer_id'=>$buyer->id,'payment_entry_id'=>$entry->id,'native_payment_id'=>$payment->id,'earning_entry_id'=>$earning->id,
                'total_minor'=>$total,'seller_minor'=>$seller,'platform_minor'=>$platform,'shipping_minor'=>$shipping,'created_at'=>$time]);
            $remaining=$total;
            foreach ($backings as $b) {
                $part=min($remaining,$this->unused($b));if($part<1)continue;
                DB::table('academic_wallet_allocations')->insert(['payment_id'=>$id,'simulation_commitment_id'=>$b->simulation_commitment_id,'amount_minor'=>$part]);$remaining-=$part;if($remaining===0)break;
            }
            if ($remaining!==0) $this->invalid('The complete payment cannot be exclusively allocated.');
            $order->update(['payment_status'=>'PAID']);
            return $order->fresh(['sellerOrders.items','sellerOrders.shipment','items','payments']);
        });
    }

    public function reversible(Order $order): bool
    {
        if (!app(AcademicWalletPaymentContract::class)->ready()) return false;
        $p=DB::table('academic_wallet_payments')->where('order_id',$order->id)->first();
        return $p && $order->payment_method==='wallet' && $order->payment_status==='PAID'
            && $order->status==='PLACED' && !DB::table('academic_wallet_reversals')->where('payment_id',$p->id)->exists()
            && !DB::table('academic_wallet_settlements')->where('payment_id',$p->id)->exists();
    }

    /** Native whole-order cancellation supplies inventory/order changes; no partial reversals. */
    public function cancel(Order $order,User $buyer,callable $native): Order
    {
        return $this->operation(function () use ($order,$buyer,$native) {
            $buyer=User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            abort_unless($buyer->isBuyer() && $buyer->status==='active' && !$buyer->is_suspended,403);
            BuyerWallet::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $p=DB::table('academic_wallet_payments')->where('order_id',$order->id)->first();
            if (!$p || (int)$p->buyer_id!==$buyer->id) abort(403);
            $so=SellerOrder::findOrFail($p->seller_order_id);Seller::whereKey($so->seller_id)->lockForUpdate()->firstOrFail();
            $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (DB::table('academic_wallet_reversals')->where('payment_id',$p->id)->exists()) {
                if ($locked->status!=='CANCELLED') $this->invalid('Recorded reversal does not match the order.');
                return $locked->fresh(['sellerOrders','items']);
            }
            if (!$this->reversible($locked)) $this->invalid('Only a whole wallet purchase before processing can be reversed. Completed or settled purchases require support review.');
            $balance=filter_var(BuyerWalletEntry::where('buyer_id',$buyer->id)->where('status','POSTED')->sum('amount_minor'),FILTER_VALIDATE_INT);
            if ($balance===false || $balance>PHP_INT_MAX-(int)$p->total_minor) $this->invalid('The wallet reversal exceeds supported balance precision. Contact support; the order has not been cancelled.');
            $cancelled=$native();
            $entry=new BuyerWalletEntry;$entry->forceFill(['buyer_id'=>$buyer->id,'type'=>'PAYMENT_REVERSAL','status'=>'POSTED','amount_minor'=>$p->total_minor,
                'order_id'=>$order->id,'source_entry_id'=>$p->payment_entry_id,'source_identity'=>'WALLET-REVERSE-ORDER-'.$order->id,'created_at'=>now()->startOfSecond()])->save();
            DB::table('academic_wallet_reversals')->insert(['payment_id'=>$p->id,'wallet_entry_id'=>$entry->id,'cancelled_by'=>$buyer->id,'created_at'=>now()->startOfSecond()]);
            Payment::whereKey($p->native_payment_id)->update(['status'=>'REFUNDED']);
            SellerWalletEntry::whereKey($p->earning_entry_id)->update(['status'=>'VOID']);
            $cancelled->update(['payment_status'=>'REFUNDED']);
            // Preserve native voucher-redemption history and its once-used policy,
            // exactly like existing whole-order COD cancellation; no second use is invented.
            return $cancelled->fresh(['sellerOrders','items','payments']);
        });
    }

    public function release(SellerOrder $sellerOrder): bool
    {
        if (config('returns.earning_release_enabled')!==true) return false;
        return $this->operation(function () use ($sellerOrder) {
            $p=DB::table('academic_wallet_payments')->where('seller_order_id',$sellerOrder->id)->first();if(!$p)return false;
            Seller::whereKey($sellerOrder->seller_id)->lockForUpdate()->firstOrFail();
            $order=Order::whereKey($p->order_id)->lockForUpdate()->firstOrFail();$so=SellerOrder::whereKey($p->seller_order_id)->lockForUpdate()->firstOrFail();
            if (DB::table('academic_wallet_settlements')->where('payment_id',$p->id)->exists()) return false;
            if (DB::table('academic_wallet_reversals')->where('payment_id',$p->id)->exists() || $order->status!=='COMPLETED' || !$order->completed_at
                || $so->getRawOriginal('workflow_status')!=='COMPLETED' || $so->status!=='completed' || !$so->completed_at) return false;
            if (DB::selectOne('SELECT EXISTS ('.AcademicWalletPaymentContract::settlementHold('?').') AS held',[$p->id])->held) return false;
            $sh=Shipment::where('seller_order_id',$so->id)->lockForUpdate()->first();
            if (!$sh || $sh->getRawOriginal('workflow_status')!=='COMPLETED' || $sh->status!=='delivered' || !$sh->proof_path || !$sh->proof_delivered_at || !$sh->delivered_by || $sh->cod_collected
                || !DeliveryEvent::where('shipment_id',$sh->id)->where('status','DELIVERED')->where('user_id',$sh->delivered_by)->exists()
                || !\App\Models\RiderShippingFeeEarning::where('seller_order_id',$so->id)->where('shipment_id',$sh->id)->where('amount_minor',$p->shipping_minor)->exists()) return false;
            $earning=SellerWalletEntry::whereKey($p->earning_entry_id)->lockForUpdate()->firstOrFail();
            if ($earning->status!=='HELD' || (int)$earning->amount_minor!==(int)$p->seller_minor || SellerWalletEntry::where('source_wallet_entry_id',$earning->id)->exists()) return false;
            $release=SellerWalletEntry::create(['seller_id'=>$so->seller_id,'seller_order_id'=>$so->id,'source_wallet_entry_id'=>$earning->id,'type'=>'EARNING_RELEASE','status'=>'POSTED','amount_minor'=>$earning->amount_minor,'reference'=>'ER-'.$earning->id,'description'=>'Completed wallet purchase settled from its exclusive payment claim']);
            DB::table('academic_wallet_settlements')->insert(['payment_id'=>$p->id,'release_entry_id'=>$release->id,'shipment_id'=>$sh->id,'delivered_by'=>$sh->delivered_by,'proof_path'=>$sh->proof_path,'created_at'=>now()->startOfSecond()]);
            return true;
        });
    }
}
