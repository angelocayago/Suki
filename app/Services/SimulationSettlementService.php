<?php

namespace App\Services;

use App\Models\{BuyerRefundFunding,BuyerWallet,BuyerWalletEntry,CustodySource,LogisticsCodRemittanceItem,Order,OrderItem,Payout,ReturnRefundRequest,Seller,SellerOrder,SellerWalletEntry,User};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

/** No routes, commands or automatic issuance hooks. All value is virtual. */
final class SimulationSettlementService
{
    public function installed(): bool
    {
        if (!Schema::hasTable('simulation_settlement_mutex')) return false;
        if (!Schema::hasTable('buyer_refund_fundings')) {
            if (DB::table('simulation_settlements')->exists()) $this->invalid('An enrolled simulation cannot fall back while its funding schema is unavailable.');
            return false;
        }
        return true;
    }

    private function enabled(): void
    {
        if (config('simulation_settlement.enabled') !== true) $this->invalid('Simulation settlement is disabled.');
    }

    private function admin(User $actor): User
    {
        $actor = User::findOrFail($actor->id);
        abort_unless($actor->isAdmin() && $actor->status==='active' && !$actor->is_suspended,403);
        return $actor;
    }

    private function operation(callable $callback): mixed
    {
        if (!$this->installed()) $this->invalid('Simulation schema is not deployed.');
        return DB::transaction(function () use ($callback) {
            $mutex=DB::table('simulation_settlement_mutex')->where('id',1)->lockForUpdate()->first();
            if (!$mutex || (int)$mutex->operation_open!==0) $this->invalid('Simulation coordination cannot be nested or bypassed.');
            DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>1]);
            $result=$callback();
            $this->verifyPayoutLedgers();
            $this->verifyRefundLinks();
            app(SimulationRefundApprovalGuard::class)->verifyCoverage();
            app(SimulationWalletCreditContract::class)->verify();
            // SQLite verifies cross-row invariants here; PostgreSQL also verifies
            // them with deferred constraints at the outer transaction commit.
            DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>0]);
            return $result;
        },3);
    }

    /** First cohort: one new native COD purchase, one previously unused Seller.
     * No existing Order can be tagged. No monetary amount is a method argument.
     */
    public function createScenario(User $actor, User $buyer, Seller $seller, array $address, string $scenarioKey, array $voucherCodes=[]): object
    {
        $actor=$this->admin($actor); $this->enabled();
        app(SimulationRefundApprovalGuard::class)->requireReady();
        if (!preg_match('/\A[A-Za-z0-9-]{8,80}\z/D',$scenarioKey)) $this->invalid('A unique scenario identity is required.');
        return $this->operation(function () use ($actor,$buyer,$seller,$address,$scenarioKey,$voucherCodes) {
            $seller=Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $existing=DB::table('simulation_settlements')->where('scenario_key',$scenarioKey)->first();
            if ($existing) {
                if ((int)$existing->seller_id!==(int)$seller->id || (int)$existing->buyer_id!==(int)$buyer->id || (int)$existing->created_by!==(int)$actor->id) $this->invalid('Scenario identity belongs to another purchase.');
                $this->verify($existing); return $existing;
            }
            $buyer=User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $owner=$seller->owner;
            if (!$buyer->isBuyer() || $buyer->status!=='active' || $buyer->is_suspended || $seller->status!=='approved' || !$owner || !$owner->isSeller() || $owner->status!=='active' || $owner->is_suspended) $this->invalid('Eligible Buyer and approved Seller required.');
            if (SellerOrder::where('seller_id',$seller->id)->exists() || SellerWalletEntry::where('seller_id',$seller->id)->exists() || Payout::where('seller_id',$seller->id)->exists() || ReturnRefundRequest::where('seller_id',$seller->id)->exists()) $this->invalid('Historical Seller accounting is excluded from this new cohort.');
            $summary=app(CheckoutService::class)->cartSummary($buyer,$voucherCodes);
            if (!$summary['cart'] || collect($summary['cart'])->contains(fn($line)=>(int)$line['seller_id']!==(int)$seller->id)) $this->invalid('The bounded scenario requires one Seller and a valid Cart.');
            $order=app(CheckoutService::class)->checkout($buyer,$address,'cod',$voucherCodes);
            $so=$order->sellerOrders()->sole();
            $quote=app(SimulationSettlementQuote::class)->calculate($so);
            $limit=config('simulation_settlement.max_scenario_minor');
            if (!is_int($limit) || $limit<1 || $quote['cap_minor']>$limit || (int)$order->total_minor!==$quote['cap_minor']) $this->invalid('Scenario exceeds the approved finite issuance limit.');
            $json=json_encode($quote,JSON_THROW_ON_ERROR);
            $id=DB::table('simulation_settlements')->insertGetId([
                'scenario_key'=>$scenarioKey,'seller_id'=>$seller->id,'buyer_id'=>$buyer->id,'order_id'=>$order->id,'seller_order_id'=>$so->id,'created_by'=>$actor->id,
                'policy'=>$quote['policy'],'currency'=>'PHP','quote'=>$json,'quote_hash'=>hash('sha256',$json),'cap_minor'=>$quote['cap_minor'],
                'seller_claim_minor'=>$quote['seller_minor'],'platform_claim_minor'=>$quote['platform_minor'],'shipping_claim_minor'=>$quote['shipping_minor'],'reverse_shipping_minor'=>0,
                'issued_minor'=>0,'seller_available_minor'=>0,'platform_available_minor'=>0,'shipping_protected_minor'=>0,'refund_reserved_minor'=>0,'payout_reserved_minor'=>0,'consumed_minor'=>0,'created_at'=>now(),
            ]);
            return DB::table('simulation_settlements')->find($id);
        });
    }

    /** Explicitly issue exactly the frozen native purchase, after accepted COD
     * and an existing AVAILABLE earning. Never release HELD earnings here.
     */
    public function issue(int $scenarioId, LogisticsCodRemittanceItem $item, User $actor): object
    {
        $actor=$this->admin($actor); $this->enabled();
        return $this->operation(function () use ($scenarioId,$item,$actor) {
            $book=$this->lockBook($scenarioId); $this->verify($book);
            app(SimulationRefundApprovalGuard::class)->assertMode(SellerOrder::findOrFail($book->seller_order_id),ReturnRefundRequest::SELLER_REVIEW);
            $seller=Seller::findOrFail($book->seller_id); $owner=$seller->owner;
            if ($seller->status!=='approved' || !$owner || !$owner->isSeller() || $owner->status!=='active' || $owner->is_suspended) $this->invalid('An eligible approved Seller is required for simulation issuance.');
            $source=app(CustodySourceService::class)->record($item,$actor);
            if ((int)$source->seller_order_id!==(int)$book->seller_order_id || (int)$source->accepted_minor!==(int)$book->cap_minor
                || $item->logisticsRemittance->items()->count()!==1) $this->invalid('Only the exact unmixed scenario acceptance can issue this source.');
            if ((int)$book->issued_minor>0) {
                if ((int)$book->custody_source_id!==(int)$source->id) $this->invalid('A different receipt cannot replay issuance.');
                return $book;
            }
            $so=SellerOrder::findOrFail($book->seller_order_id);
            $earning=SellerWalletEntry::where('seller_id',$book->seller_id)->where('seller_order_id',$so->id)->where('type','SALE_EARNING')->lockForUpdate()->get();
            $status=$so->workflow_status instanceof \BackedEnum ? $so->workflow_status->value : $so->workflow_status;
            if ($status!=='COMPLETED' || $earning->count()!==1 || $earning->first()->status!=='AVAILABLE'
                || (int)$earning->first()->amount_minor!==(int)$book->seller_claim_minor || SellerWalletEntry::where('seller_id',$book->seller_id)->where('type','!=','SALE_EARNING')->exists()
                || ReturnRefundRequest::where('seller_id',$book->seller_id)->exists()) $this->invalid('A complete, initially available earning with no prior obligations is required. Held releases stay disabled.');
            DB::table('simulation_settlements')->where('id',$book->id)->update(['custody_source_id'=>$source->id,'issued_by'=>$actor->id,'issued_at'=>now(),
                'issued_minor'=>$book->cap_minor,'seller_available_minor'=>$book->seller_claim_minor,'platform_available_minor'=>$book->platform_claim_minor,'shipping_protected_minor'=>$book->shipping_claim_minor]);
            $this->event($book->id,'ISSUE-'.$book->id,$actor->id);
            return DB::table('simulation_settlements')->find($book->id);
        });
    }

    /** Every native payout writer enters here before its Seller/order locks.
     * Legacy Sellers retain native behavior; an enrolled Seller never falls back.
     */
    public function payout(Seller $seller, string $action, callable $native, ?User $actor=null, ?Payout $payout=null, ?string $method=null): Payout
    {
        return $this->operation(function () use ($seller,$action,$native,$actor,$payout,$method) {
            Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $book=DB::table('simulation_settlements')->where('seller_id',$seller->id)->lockForUpdate()->first();
            if (!$book) return $native();
            app(SimulationRefundApprovalGuard::class)->requireReady();
            $this->enabled(); $this->verify($book);
            $currentSeller=Seller::findOrFail($book->seller_id);
            if ($action!=='REJECTED' && ($currentSeller->status!=='approved' || !$currentSeller->owner || $currentSeller->owner->status!=='active' || $currentSeller->owner->is_suspended)) $this->invalid('Simulation Seller is no longer eligible.');
            if ((int)$book->issued_minor<1) $this->invalid('The simulation source has not been issued.');
            if ($actor) $actor=$this->admin($actor);
            if ($action==='PAID' && $method!=='manual') $this->invalid('Simulation payouts cannot use bank or e-wallet transfer methods.');
            $before=$payout ? DB::table('simulation_commitments')->where('payout_id',$payout->id)->first() : null;
            if ($payout && (!$before || (int)$before->simulation_settlement_id!==(int)$book->id)) $this->invalid('Payout has no owned simulation reservation.');
            $result=$native(); $amount=(int)$result->amount_minor;
            if ($result->status==='PAID') {
                $result->update(['reference'=>'SIMULATION ONLY: '.mb_substr((string)$result->reference,0,80)]);
                SellerWalletEntry::where('payout_id',$result->id)->where('type','PAYOUT_COMPLETED')->update(['reference'=>$result->reference,'description'=>'Academic simulation payout; no external transfer']);
            }
            if ($action==='REQUEST') {
                if ($amount>(int)$book->seller_available_minor) $this->invalid('Amount exceeds uncommitted simulation Seller funds.');
                DB::table('simulation_commitments')->insert(['simulation_settlement_id'=>$book->id,'kind'=>'PAYOUT','payout_id'=>$result->id,'status'=>'PENDING','seller_minor'=>$amount,'platform_minor'=>0]);
                $book->seller_available_minor-=$amount; $book->payout_reserved_minor+=$amount;
            } else {
                if ((int)$before->seller_minor!==$amount) $this->invalid('Payout amount changed.');
                DB::table('simulation_commitments')->where('id',$before->id)->update(['status'=>$result->status]);
                if ($result->status==='REJECTED') { $book->payout_reserved_minor-=$amount; $book->seller_available_minor+=$amount; }
                if ($result->status==='PAID') { $book->payout_reserved_minor-=$amount; $book->consumed_minor+=$amount; }
            }
            $this->saveBalances($book);
            if ($result->status==='REJECTED') $this->allocatePending($book);
            $this->event($book->id,'PAYOUT-'.$result->id.'-'.$result->status,$actor?->id ?? Seller::findOrFail($seller->id)->user_id);
            return $result;
        });
    }

    public function sellerDecision(ReturnRefundRequest $request, User $actor, string $decision, callable $native): ReturnRefundRequest
    {
        return $this->operation(function () use ($request,$actor,$decision,$native) {
            $reference=ReturnRefundRequest::findOrFail($request->id);
            Seller::whereKey($reference->seller_id)->lockForUpdate()->firstOrFail();
            $book=DB::table('simulation_settlements')->where('seller_id',$reference->seller_id)->lockForUpdate()->first();
            if (!$book) return $native();
            app(SimulationRefundApprovalGuard::class)->assertMode(SellerOrder::findOrFail($reference->seller_order_id),$reference->workflow_mode ?? ReturnRefundRequest::FINANCIAL);
            $this->enabled(); $this->verify($book);
            $actor=User::findOrFail($actor->id);
            abort_unless($actor->isSeller() && $actor->status==='active' && !$actor->is_suspended && (int)Seller::findOrFail($book->seller_id)->user_id===(int)$actor->id,403);
            if ((int)$reference->seller_order_id!==(int)$book->seller_order_id || ($decision==='APPROVED' && (int)$book->issued_minor<1)) $this->invalid('A matching issued scenario is required before approval.');
            $result=$native();
            if ($result->status==='ADMIN_APPROVED') { $this->allocatePending($book); $this->event($book->id,'REFUND-'.$result->id,$actor->id); }
            return $result;
        });
    }

    /** Separate funding API; never a Buyer credit operation. */
    public function allocate(ReturnRefundRequest $request, User $actor): BuyerRefundFunding
    {
        $actor=$this->admin($actor); $this->enabled();
        return $this->operation(function () use ($request,$actor) {
            $book=DB::table('simulation_settlements')->where('seller_order_id',$request->seller_order_id)->first();
            if (!$book) $this->invalid('Historical refunds are excluded.');
            $book=$this->lockBook($book->id); $this->verify($book);
            if ((int)$book->issued_minor<1) $this->invalid('Scenario source has not been issued.');
            $this->allocatePending($book);
            return BuyerRefundFunding::findOrFail($request->id);
        });
    }

    private function allocatePending(object $book): void
    {
        foreach (ReturnRefundRequest::where('seller_order_id',$book->seller_order_id)->where('status','ADMIN_APPROVED')->orderBy('id')->get() as $request) {
            $c=DB::table('simulation_commitments')->where('return_refund_request_id',$request->id)->lockForUpdate()->first();
            if ($c && in_array($c->status,['RESERVED','WALLET_BACKED'],true)) continue;
            // The existing W5F assessment validates the full original obligation,
            // Seller debit and snapshots. It never invents a historical funding row.
            app(BuyerRefundFundingService::class)->assess($request,User::findOrFail($book->issued_by));
            $f=BuyerRefundFunding::findOrFail($request->id);
            $seller=(int)$f->seller_adjustment_minor;
            $platform=(int)$f->buyer_refund_minor-$seller;
            if ($platform<0) $this->invalid('Refund claims do not partition the original source.');
            $priorSeller=(int)($c->seller_minor ?? 0); $priorPlatform=(int)($c->platform_minor ?? 0);
            $ds=min($seller-$priorSeller,(int)$book->seller_available_minor);
            $dp=min($platform-$priorPlatform,(int)$book->platform_available_minor);
            if ($ds<0 || $dp<0) $this->invalid('Refund commitment exceeds its original entitlement.');
            $full=$priorSeller+$ds===$seller && $priorPlatform+$dp===$platform;
            $values=['simulation_settlement_id'=>$book->id,'kind'=>'REFUND','return_refund_request_id'=>$request->id,'status'=>$full?'RESERVED':'PARTIAL','seller_minor'=>$priorSeller+$ds,'platform_minor'=>$priorPlatform+$dp];
            if ($c) DB::table('simulation_commitments')->where('id',$c->id)->update($values); else DB::table('simulation_commitments')->insert($values);
            $book->seller_available_minor-=$ds; $book->platform_available_minor-=$dp; $book->refund_reserved_minor+=$ds+$dp;
            $this->saveBalances($book);
            if ($full && (int)$f->buyer_refund_minor>0) $f->forceFill(['status'=>BuyerRefundFunding::READY,'funded_minor'=>$f->buyer_refund_minor,'reserve_funded_minor'=>$f->buyer_refund_minor,
                'funding_source_type'=>'SIMULATION_SETTLEMENT','funding_source_reference'=>'SIM-'.$book->id,'verified_by'=>$book->issued_by,'verified_at'=>now()])->save();
        }
    }

    /** Internal, Admin-only transfer of existing reserved value. No amount or
     * destination is accepted; both are resolved from the locked original graph.
     */
    public function creditRefund(ReturnRefundRequest $request,User $actor): BuyerWalletEntry
    {
        $actor=$this->admin($actor);$this->enabled();
        if (config('suki_wallet.refund_credit_enabled')!==true) $this->invalid('Refund wallet credit is disabled.');
        $contract=app(SimulationWalletCreditContract::class);$contract->requireReady();
        return $this->operation(function()use($request,$actor,$contract) {
            $actor=$this->admin($actor); // recheck after waiting for the mutex
            $reference=ReturnRefundRequest::findOrFail($request->getKey());
            $refBook=DB::table('simulation_settlements')->where('seller_order_id',$reference->seller_order_id)->first();
            if (!$refBook) $this->invalid('Historical refunds are excluded from simulation credit.');
            $book=$this->lockBook($refBook->id);$this->verify($book);
            $r=ReturnRefundRequest::whereKey($reference->id)->lockForUpdate()->firstOrFail();
            $so=SellerOrder::findOrFail($book->seller_order_id);
            app(SimulationRefundApprovalGuard::class)->assertMode($so,$r->workflow_mode ?? ReturnRefundRequest::FINANCIAL);
            if ((int)$book->issued_minor<1 || (int)$r->buyer_id!==(int)$book->buyer_id || (int)$r->order_id!==(int)$book->order_id
                || (int)$r->seller_id!==(int)$book->seller_id || $r->status!=='ADMIN_APPROVED') $this->invalid('Only the original approved simulation refund can be credited.');
            $source=CustodySource::findOrFail($book->custody_source_id);
            $proof=app(CustodySourceService::class)->record(LogisticsCodRemittanceItem::findOrFail($source->logistics_cod_remittance_item_id),$actor);
            if ((int)$proof->id!==(int)$source->id || (int)$proof->seller_order_id!==(int)$so->id || (int)$proof->accepted_minor!==(int)$book->issued_minor) $this->invalid('Issued simulation provenance changed.');
            $item=OrderItem::whereKey($r->order_item_id)->lockForUpdate()->firstOrFail();
            $buyer=User::whereKey($book->buyer_id)->lockForUpdate()->firstOrFail();abort_unless($buyer->isBuyer(),403);
            app(ReturnRefundService::class)->inspectPurchase($buyer,$item);
            $quote=app(ReturnRefundAllocationService::class)->calculate($item,(int)$r->quantity);
            $f=BuyerRefundFunding::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $c=DB::table('simulation_commitments')->where('return_refund_request_id',$r->id)->lockForUpdate()->first();
            $amount=$quote['buyer_refund_due_minor'];
            foreach (['buyer_refund_minor'=>'buyer_refund_due_minor','merchandise_minor'=>'gross_merchandise_minor','seller_adjustment_minor'=>'seller_adjustment_minor','commission_reversal_minor'=>'commission_reversed_minor','seller_discount_minor'=>'seller_funded_discount_minor','platform_discount_minor'=>'platform_funded_discount_minor'] as $field=>$part) {
                if (filter_var($f->getRawOriginal($field),FILTER_VALIDATE_INT)!==$quote[$part]) $this->invalid('Refund purchase allocation changed.');
            }
            if ($amount<1 || !$c || (int)$c->simulation_settlement_id!==(int)$book->id || $c->kind!=='REFUND'
                || (int)$c->seller_minor!==$quote['seller_adjustment_minor'] || (int)$c->platform_minor!==$amount-$quote['seller_adjustment_minor']
                || (int)$f->buyer_id!==(int)$buyer->id || (int)$f->seller_id!==(int)$book->seller_id || (int)$r->refund_minor!==$amount
                || $f->funding_source_type!=='SIMULATION_SETTLEMENT' || $f->funding_source_reference!=='SIM-'.$book->id
                || $f->source_identity!=='BUYER-REFUND-RR-'.$r->id || (int)$f->verified_by!==(int)$book->issued_by
                || (int)$f->funded_minor!==$amount || (int)$f->reserve_funded_minor!==$amount || (int)$f->custody_funded_minor!==0 || (int)$f->seller_recovery_funded_minor!==0) $this->invalid('An exact, exclusive full refund reservation is required.');
            app(SimulationRefundApprovalGuard::class)->verifyCoverage();$contract->verify();
            $credits=BuyerWalletEntry::where('return_refund_request_id',$r->id)->lockForUpdate()->get();
            $backing=DB::table('simulation_wallet_backings')->where('return_refund_request_id',$r->id)->lockForUpdate()->first();
            if ($f->status===BuyerRefundFunding::CREDITED) {
                if ($c->status!=='WALLET_BACKED' || !$backing || $credits->count()!==1 || (int)$backing->buyer_wallet_entry_id!==(int)$credits->first()->id) $this->invalid('Recorded credit replay does not reconcile.');
                return $credits->first();
            }
            if ($f->status!==BuyerRefundFunding::READY || $c->status!=='RESERVED' || $backing || $credits->isNotEmpty() || $f->consumed_at!==null
                || (int)$book->refund_reserved_minor<$amount) $this->invalid('Refund reservation is not ready or has already been consumed.');
            $wallet=BuyerWallet::whereKey($buyer->id)->lockForUpdate()->first() ?? BuyerWallet::create(['buyer_id'=>$buyer->id]);
            $balance=app(BuyerWalletService::class)->summary($buyer)['balance_minor'];
            if ($balance>PHP_INT_MAX-$amount) $this->invalid('The wallet balance exceeds supported integer-centavo precision.');
            $time=now()->startOfSecond();
            $entry=new BuyerWalletEntry;$entry->forceFill(['buyer_id'=>$buyer->id,'type'=>'REFUND_CREDIT','status'=>'POSTED','amount_minor'=>$amount,
                'return_refund_request_id'=>$r->id,'source_identity'=>'SIM-REFUND-CREDIT-RR-'.$r->id,'created_at'=>$time])->save();
            DB::table('simulation_commitments')->where('id',$c->id)->update(['status'=>'WALLET_BACKED']);
            $book->refund_reserved_minor-=$amount;$book->consumed_minor+=$amount;$this->saveBalances($book);
            $f->forceFill(['status'=>BuyerRefundFunding::CREDITED,'consumed_at'=>$time])->save();
            $event=DB::table('simulation_settlement_events')->insertGetId(['simulation_settlement_id'=>$book->id,'operation_key'=>'REFUND-CREDIT-'.$r->id,'actor_id'=>$actor->id,
                'snapshot'=>json_encode(['kind'=>'BUYER_REFUND_BACKING','return_refund_request_id'=>$r->id,'buyer_wallet_entry_id'=>$entry->id,'simulation_commitment_id'=>$c->id,'buyer_id'=>$buyer->id,'amount_minor'=>$amount,'seller_minor'=>(int)$c->seller_minor,'platform_minor'=>(int)$c->platform_minor,'book'=>$book],JSON_THROW_ON_ERROR),'created_at'=>$time]);
            DB::table('simulation_wallet_backings')->insert(['simulation_commitment_id'=>$c->id,'simulation_settlement_id'=>$book->id,'return_refund_request_id'=>$r->id,
                'buyer_id'=>$buyer->id,'buyer_wallet_entry_id'=>$entry->id,'settlement_event_id'=>$event,'kind'=>'BUYER_REFUND_BACKING','currency'=>'PHP',
                'amount_minor'=>$amount,'seller_minor'=>$c->seller_minor,'platform_minor'=>$c->platform_minor,'source_identity'=>'SIM-WALLET-RR-'.$r->id,'credited_by'=>$actor->id,'created_at'=>$time]);
            $wallet->touch();return $entry;
        });
    }

    private function lockBook(int $id): object
    {
        $ref=DB::table('simulation_settlements')->find($id);
        if (!$ref) $this->invalid('Unknown simulation scenario.');
        Seller::whereKey($ref->seller_id)->lockForUpdate()->firstOrFail();
        Order::whereKey($ref->order_id)->lockForUpdate()->firstOrFail();
        SellerOrder::whereKey($ref->seller_order_id)->lockForUpdate()->firstOrFail();
        return DB::table('simulation_settlements')->where('id',$id)->lockForUpdate()->first();
    }

    private function verify(object $book): void
    {
        $so=SellerOrder::findOrFail($book->seller_order_id);
        $quote=app(SimulationSettlementQuote::class)->calculate($so);
        $json=json_encode($quote,JSON_THROW_ON_ERROR);
        if (!hash_equals($book->quote_hash,hash('sha256',$json)) || json_decode($book->quote,true,512,JSON_THROW_ON_ERROR)!==$quote
            || (int)$so->seller_id!==(int)$book->seller_id || (int)$so->order_id!==(int)$book->order_id
            || (int)$so->order->buyer_id!==(int)$book->buyer_id || (int)$so->order->total_minor!==$quote['cap_minor']
            || SellerOrder::where('seller_id',$book->seller_id)->where('id','!=',$so->id)->exists()
            || (int)$book->cap_minor!==$quote['cap_minor'] || (int)$book->seller_claim_minor!==$quote['seller_minor'] || (int)$book->platform_claim_minor!==$quote['platform_minor']) $this->invalid('Scenario ownership, original quote or exclusive accounting domain changed.');
    }

    private function saveBalances(object $book): void
    {
        $values=[];
        foreach (['seller_available_minor','platform_available_minor','refund_reserved_minor','payout_reserved_minor','consumed_minor'] as $field) $values[$field]=(int)$book->$field;
        DB::table('simulation_settlements')->where('id',$book->id)->update($values);
    }

    private function verifyPayoutLedgers(): void
    {
        $payouts=Payout::whereIn('seller_id',DB::table('simulation_settlements')->select('seller_id'))->get();
        foreach ($payouts as $payout) {
            $status=match($payout->status) { 'PAID'=>'SETTLED', 'REJECTED'=>'RELEASED', default=>'RESERVED' };
            $base=SellerWalletEntry::where('payout_id',$payout->id)->where('seller_id',$payout->seller_id)->where('amount_minor',$payout->amount_minor);
            if ((clone $base)->where('type','PAYOUT_RESERVATION')->where('status',$status)->count()!==1
                || ($payout->status==='PAID' && (clone $base)->where('type','PAYOUT_COMPLETED')->where('status','POSTED')->count()!==1)) $this->invalid('Native payout ledger does not match the simulation commitment.');
        }
    }

    private function verifyRefundLinks(): void
    {
        foreach (DB::table('simulation_commitments')->where('kind','REFUND')->get() as $c) {
            $book=DB::table('simulation_settlements')->find($c->simulation_settlement_id);
            $r=ReturnRefundRequest::find($c->return_refund_request_id); $f=BuyerRefundFunding::find($c->return_refund_request_id);
            if (!$book || !$r || !$f || (int)$r->seller_order_id!==(int)$book->seller_order_id || (int)$r->buyer_id!==(int)$book->buyer_id
                || (int)$f->seller_id!==(int)$book->seller_id || (int)$f->buyer_id!==(int)$book->buyer_id || $r->status!=='ADMIN_APPROVED'
                || (int)$c->seller_minor>(int)$f->seller_adjustment_minor || (int)$c->platform_minor>(int)$f->buyer_refund_minor-(int)$f->seller_adjustment_minor
                || ($c->status==='PARTIAL' && $f->status!==BuyerRefundFunding::AWAITING)
                || ($c->status==='WALLET_BACKED' && $f->status!==BuyerRefundFunding::CREDITED)
                || ($c->status==='RESERVED' && ($f->status!==BuyerRefundFunding::READY || $f->funding_source_type!=='SIMULATION_SETTLEMENT' || (int)$c->seller_minor+(int)$c->platform_minor!==(int)$f->buyer_refund_minor))) $this->invalid('Refund source ownership or commitment is inconsistent.');
        }
    }

    private function event(int $bookId, string $key, int $actorId): void
    {
        if (DB::table('simulation_settlement_events')->where('operation_key',$key)->exists()) return;
        DB::table('simulation_settlement_events')->insert(['simulation_settlement_id'=>$bookId,'operation_key'=>$key,'actor_id'=>$actorId,
            'snapshot'=>json_encode(DB::table('simulation_settlements')->find($bookId),JSON_THROW_ON_ERROR),'created_at'=>now()]);
    }

    private function invalid(string $message): never { throw ValidationException::withMessages(['simulation'=>$message]); }
}
