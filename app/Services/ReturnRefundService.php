<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\{Order, OrderItem, Product, ProductVariant, ReturnRefundEvent, ReturnRefundRequest, ReturnRefundWorkflow, Rider, RiderCodRemittance, Seller, SellerOrder, SellerWalletEntry, Shipment, User, VoucherRedemption};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Item-scoped obligations only. No cash transfer, voucher reversal or restocking. */
class ReturnRefundService
{
    private function enabled(string $mode = ReturnRefundRequest::FINANCIAL): void
    {
        $this->requireGate($mode);
        if (!app(FinancialSchemaReadiness::class)->inspect()['ready']) {
            $this->invalid('request','Required refund integrity migrations have not been verified. Processing remains unavailable.');
        }
    }

    private function requireGate(string $mode): void
    {
        $flag = match ($mode) {
            ReturnRefundRequest::ADMIN_REVIEW, ReturnRefundRequest::SELLER_REVIEW => 'returns.admin_review_enabled',
            ReturnRefundRequest::FINANCIAL => 'returns.backend_enabled',
            default => null,
        };
        if (!$flag || config($flag, false) !== true) $this->invalid('request', 'This return/refund workflow is disabled pending activation review.');
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** Seller is the payout serialization boundary; no remittance locks are acquired here. */
    private function lockedPurchase(int $itemId): array
    {
        $reference = OrderItem::with('sellerOrder')->findOrFail($itemId);
        if (!$reference->sellerOrder) $this->invalid('item', 'Historical purchase linkage is incomplete; contact support.');
        $seller = Seller::lockForUpdate()->findOrFail($reference->sellerOrder->seller_id);
        $order = Order::lockForUpdate()->findOrFail($reference->sellerOrder->order_id);
        $so = SellerOrder::lockForUpdate()->findOrFail($reference->seller_order_id);
        $shipment = Shipment::where('seller_order_id', $so->id)->lockForUpdate()->first();
        $items = $so->items()->orderBy('id')->lockForUpdate()->get();
        $item = $items->firstWhere('id', $itemId);
        if (!$item || (int)$so->order_id !== (int)$order->id || (int)$so->seller_id !== (int)$seller->id) $this->invalid('item', 'Purchase records are inconsistent; contact support.');
        $so->setRelation('order', $order)->setRelation('seller', $seller)->setRelation('shipment', $shipment)->setRelation('items', $items);
        return [$item, $so, $shipment];
    }

    /** Revalidate both at submission and immediately before approval. */
    private function validatePurchase(OrderItem $item, SellerOrder $so, ?Shipment $shipment): array
    {
        $order = $so->order;
        $workflow = OrderStatus::tryFrom((string)$so->getRawOriginal('workflow_status'));
        if (!in_array($workflow, [OrderStatus::Delivered, OrderStatus::Completed], true)
            || $so->status !== $workflow->legacySellerStatus() || strtoupper((string)$order->status) === 'CANCELLED'
            || !OrderStatus::tryFrom(strtoupper((string)$order->status)) || $order->cancelled_at) {
            $this->invalid('item', 'Only consistent delivered or completed purchases are eligible.');
        }
        if ($order->payment_method !== 'cod' || !in_array(strtoupper((string)$order->payment_status), ['PAID', 'UNPAID'], true)) $this->invalid('payment', 'Payment records require support review.');
        if (!$shipment || $shipment->getRawOriginal('workflow_status') !== $workflow->value
            || $shipment->status !== $workflow->legacyShipmentStatus() || !$shipment->cod_collected
            || !$shipment->cod_collected_at || !$shipment->proof_path || !$shipment->proof_delivered_at || !$shipment->delivered_by) {
            $this->invalid('payment', 'Verified parcel delivery and COD collection evidence are required.');
        }
        if ($order->payments()->exists()) $this->invalid('payment', 'Additional payment records require support review; no automatic refund is supported.');
        if (strtoupper((string)$order->payment_status) === 'UNPAID'
            && !Shipment::whereHas('sellerOrder', fn($q) => $q->where('order_id', $order->id))->where('cod_collected', false)->exists()) {
            $this->invalid('payment', 'Parent payment status contradicts completed parcel collections.');
        }
        $totals = ['gross'=>0, 'seller'=>0, 'platform'=>0, 'commission'=>0];
        foreach ($so->items as $line) {
            $variant = ProductVariant::withTrashed()->find($line->product_variant_id);
            $product = $variant ? Product::withTrashed()->find($variant->product_id) : null;
            if ((int)$line->order_id !== (int)$order->id || (int)$line->seller_id !== (int)$so->seller->user_id
                || !$variant || !$product || (int)$line->product_id !== (int)$product->id || (int)$product->seller_id !== (int)$so->seller_id) {
                $this->invalid('item', 'Exact purchased item and variant linkage cannot be verified; contact support.');
            }
            $c = app(ReturnRefundAllocationService::class)->calculate($line, (int)$line->quantity);
            foreach (['gross'=>'gross_merchandise_minor','seller'=>'seller_funded_discount_minor','platform'=>'platform_funded_discount_minor','commission'=>'commission_reversed_minor'] as $key=>$field) {
                if ($c[$field] > PHP_INT_MAX - $totals[$key]) $this->invalid('accounting','Purchase totals exceed supported precision.');
                $totals[$key] += $c[$field];
            }
        }
        $discounts = VoucherRedemption::where('seller_order_id', $so->id)->selectRaw('scope_snapshot, SUM(discount_minor) AS total')->groupBy('scope_snapshot')->get()->pluck('total','scope_snapshot');
        $merchandise = $totals['gross'] - $totals['seller'] - $totals['platform'];
        if ((int)$so->shipping_fee_minor > PHP_INT_MAX - $merchandise) $this->invalid('accounting','Parcel total exceeds supported precision.');
        $expected = $merchandise + (int)$so->shipping_fee_minor;
        if ($totals['gross'] !== (int)$so->subtotal_minor || $totals['commission'] !== (int)$so->commission_minor
            || $totals['seller'] !== (int)($discounts['SELLER'] ?? 0) || $totals['platform'] !== (int)($discounts['PLATFORM'] ?? 0)
            || $discounts->keys()->diff(['SELLER','PLATFORM'])->isNotEmpty() || (int)$so->shipping_fee_minor < 0
            || $expected < 1 || $expected !== (int)$shipment->cod_amount_minor || $expected !== (int)$shipment->cod_collected_minor) {
            $this->invalid('accounting', 'Purchase allocations and collected COD do not reconcile; contact support.');
        }
        $rider = Rider::where('user_id', $shipment->delivered_by)->first();
        $liability = RiderCodRemittance::where('shipment_id', $shipment->id)->first();
        if (!$rider || !$liability || (int)$liability->seller_order_id !== (int)$so->id
            || (int)$liability->rider_id !== (int)$rider->id || (int)$shipment->rider_id !== (int)$rider->id
            || (int)$liability->logistics_provider_id !== (int)$shipment->logistics_provider_id
            || (int)$rider->logistics_provider_id !== (int)$shipment->logistics_provider_id
            || (int)$liability->expected_minor !== $expected || !in_array($liability->status, ['PENDING','OUTSTANDING','SUBMITTED','VERIFIED','ACCEPTED'], true)
            || ($liability->submitted_minor !== null && (int)$liability->submitted_minor !== $expected)
            || ($liability->accepted_minor !== null && (int)$liability->accepted_minor !== $expected)
            || !$shipment->events()->where('status','COD_COLLECTED')->where('user_id',$shipment->delivered_by)->exists()) {
            $this->invalid('payment', 'COD custody evidence is incomplete or contradictory; contact support.');
        }
        // Absence of upstream remittance is permitted: collection and remittance
        // are separate facts. Existing upstream records must not contradict them.
        $upstream = \App\Models\LogisticsCodRemittanceItem::with('logisticsRemittance.items')
            ->where('rider_cod_remittance_id',$liability->id)->get();
        if ($upstream->count() > 1) $this->invalid('payment','Duplicated COD settlement linkage requires support review.');
        foreach ($upstream as $allocation) {
            $remittance = $allocation->logisticsRemittance;
            if (!$remittance || !in_array($liability->status,['VERIFIED','ACCEPTED'],true) || $allocation->amount_minor !== $expected
                || (int)$remittance->logistics_provider_id !== (int)$shipment->logistics_provider_id
                || !in_array($remittance->status,['VERIFIED','SUBMITTED','ACCEPTED'],true)
                || (int)$remittance->expected_minor !== (int)$remittance->items->sum('amount_minor')
                || (int)$remittance->submitted_minor !== (int)$remittance->expected_minor
                || (in_array($remittance->status,['VERIFIED','ACCEPTED'],true) && (int)$remittance->accepted_minor !== (int)$remittance->expected_minor)) {
                $this->invalid('payment','Upstream COD settlement is inconsistent; contact support.');
            }
        }
        return app(ReturnRefundAllocationService::class)->calculate($item, (int)$item->quantity);
    }

    private function assertSubmissionWindow(?Shipment $shipment): void
    {
        $days = (int) config('returns.request_window_days', 7);
        $deliveredAt = $shipment?->proof_delivered_at;

        if ($days < 1 || ! $deliveredAt || $deliveredAt->lt(now()->subDays($days))) {
            $this->invalid('item', 'Return/refund requests must be submitted within '.$days.' days of verified delivery.');
        }
    }

    /** Purchase/collection proof only. Does not establish unencumbered funding. */
    public function codCustodySnapshot(SellerOrder $so): array
    {
        $so->loadMissing(['items','shipment','order','seller']);
        if($so->items->isEmpty())$this->invalid('accounting','Complete purchased-item snapshots are required.');
        $this->validatePurchase($so->items->first(),$so,$so->shipment);
        $totals=['merchandise_minor'=>0,'seller_discount_minor'=>0,'platform_discount_minor'=>0,'commission_minor'=>0];
        foreach($so->items as$item){
            $quote=app(ReturnRefundAllocationService::class)->calculate($item,(int)$item->quantity);
            foreach(['merchandise_minor'=>'gross_merchandise_minor','seller_discount_minor'=>'seller_funded_discount_minor','platform_discount_minor'=>'platform_funded_discount_minor','commission_minor'=>'commission_reversed_minor']as$key=>$field){
                if($quote[$field]>PHP_INT_MAX-$totals[$key])$this->invalid('accounting','Purchase totals exceed supported precision.');
                $totals[$key]+=$quote[$field];
            }
        }
        return $totals+['shipping_minor'=>(int)$so->shipping_fee_minor,'collected_minor'=>(int)$so->shipment->cod_collected_minor];
    }

    /** Shared read-only proof for earning reconciliation; no processing activation. */
    public function verifiedCompletedEarning(SellerOrder $so): int
    {
        if ($so->getRawOriginal('workflow_status') !== 'COMPLETED' || $so->items->isEmpty()) $this->invalid('accounting','A completed purchase is required.');
        $this->validatePurchase($so->items->first(),$so,$so->shipment);
        return max(0,(int)$so->subtotal_minor - (int)$so->commission_minor - (int)$so->items->sum('seller_funded_discount_minor'));
    }

    public function submit(User $buyer, OrderItem $item, array $data): ReturnRefundRequest
    {
        return $this->submitPurchase($buyer, $item, $data, ReturnRefundRequest::FINANCIAL);
    }

    public function submitForAdmin(User $buyer, OrderItem $item, array $data): ReturnRefundRequest
    {
        if (!app(ReturnRefundWorkflowService::class)->available(ReturnRefundRequest::ADMIN_REVIEW)
            || !Schema::hasColumn('return_refund_requests', 'workflow_mode')) {
            $this->invalid('request', 'Return/refund submission is unavailable until activation and schema readiness are verified.');
        }
        return $this->submitPurchase($buyer, $item, $data, ReturnRefundRequest::ADMIN_REVIEW);
    }

    public function submitForSeller(User $buyer, OrderItem $item, array $data): ReturnRefundRequest
    {
        if (!app(ReturnRefundWorkflowService::class)->available(ReturnRefundRequest::SELLER_REVIEW)) {
            $this->invalid('request','Return/refund submission is unavailable until activation and schema readiness are verified.');
        }
        return $this->submitPurchase($buyer,$item,$data,ReturnRefundRequest::SELLER_REVIEW);
    }

    private function submitPurchase(User $buyer, OrderItem $item, array $data, string $mode): ReturnRefundRequest
    {
        $this->enabled($mode);
        return DB::transaction(function () use ($buyer, $item, $data, $mode) {
            [$item, $so, $shipment] = $this->lockedPurchase($item->id);
            app(SimulationRefundApprovalGuard::class)->assertMode($so,$mode);
            abort_unless($buyer->isBuyer() && $buyer->status === 'active' && !$buyer->is_suspended && (int)$so->order->buyer_id === (int)$buyer->id, 403);
            if (ReturnRefundRequest::where('order_item_id',$item->id)->exists()) $this->invalid('item','A lifetime request already exists for this item. View the existing request; resubmission and appeals are not supported.');
            if ((int)$data['quantity'] !== (int)$item->quantity) $this->invalid('quantity','Only the full purchased quantity is supported; partial returns are unavailable.');
            if (!in_array($data['type'], ['RETURN','REFUND'],true)) $this->invalid('type','Choose RETURN or REFUND.');
            $quote = $this->validatePurchase($item, $so, $shipment);
            $this->assertSubmissionWindow($shipment);
            $r = new ReturnRefundRequest(['buyer_id'=>$buyer->id,'order_id'=>$so->order_id,'seller_order_id'=>$so->id,'order_item_id'=>$item->id,'seller_id'=>$so->seller_id,'type'=>$data['type'],'status'=>'SUBMITTED','quantity'=>$item->quantity,'buyer_reason'=>$data['reason'],'buyer_description'=>$data['description']]);
            if (Schema::hasColumn('return_refund_requests', 'workflow_mode')) $r->workflow_mode = $mode;
            if ($mode !== ReturnRefundRequest::FINANCIAL) $r->refund_minor = $quote['buyer_refund_due_minor'];
            $r->save();
            $this->event($r,$buyer,'SUBMITTED');
            if ($mode === ReturnRefundRequest::ADMIN_REVIEW) {
                app(DomainNotificationService::class)->adminsAfterCommit('Return/refund awaiting review', 'A Buyer submitted a request for Admin review.', ['return_refund_request_id'=>$r->id]);
            }
            app(DomainNotificationService::class)->afterCommit($so->seller->user_id,'Return/refund request','A buyer submitted a request.',['return_refund_request_id'=>$r->id]);
            return $r;
        },3);
    }

    public function inspectPurchase(User $buyer,OrderItem $item): array
    {
        $so=$item->sellerOrder;abort_unless($buyer->isBuyer() && $so && (int)$so->order->buyer_id===(int)$buyer->id,403);
        $so->load(['seller','order','items','shipment']);
        $calculation = $this->validatePurchase($item,$so,$so->shipment);
        $this->assertSubmissionWindow($so->shipment);
        return $calculation;
    }

    public function sellerRespond(ReturnRefundRequest $request, User $seller, array $data): ReturnRefundRequest
    {
        return DB::transaction(function () use ($request,$seller,$data) {
            $r = ReturnRefundRequest::with('seller')->lockForUpdate()->findOrFail($request->id);
            abort_unless($seller->isSeller() && $seller->status === 'active' && ! $seller->is_suspended && (int)$r->seller->user_id === (int)$seller->id,403);
            if ($r->isSimplified()) $this->invalid('request','Use the authorized decision workflow for this request.');
            $this->requireGate(ReturnRefundRequest::FINANCIAL);
            if ($r->status !== 'SUBMITTED') $this->invalid('request','Request is no longer awaiting seller response.');
            $r->update(['status'=>'SELLER_RESPONDED','seller_recommendation'=>$data['recommendation'],'seller_note'=>$data['note'],'seller_responded_at'=>now()]);
            $this->event($r,$seller,'SELLER_RESPONDED');
            app(DomainNotificationService::class)->afterCommit($r->buyer_id,'Return/refund response','The seller responded; Admin review is required.',['return_refund_request_id'=>$r->id]);
            return $r;
        },3);
    }

    public function requestMoreEvidence(ReturnRefundRequest $request, User $admin, string $reason): ReturnRefundRequest
    {
        return $this->recordAdminReviewAction($request, $admin, 'EVIDENCE_REQUESTED', $reason);
    }

    public function escalate(ReturnRefundRequest $request, User $admin, string $reason): ReturnRefundRequest
    {
        return $this->recordAdminReviewAction($request, $admin, 'ESCALATED', $reason);
    }

    public function authorizePhysicalReturn(ReturnRefundRequest $request, User $admin, string $reason): ReturnRefundRequest
    {
        abort_unless($admin->isAdmin() && $admin->status === 'active' && ! $admin->is_suspended, 403);
        return DB::transaction(function () use ($request, $admin, $reason) {
            if (! app(ReturnRefundWorkflowService::class)->hubSchemaReadyForAuthorization()) $this->invalid('request', 'Physical return routing is unavailable pending migration rollout.');
            $locked = ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
            if ($locked->status === 'RETURN_AUTHORIZED') return $locked;
            if ($locked->type !== 'RETURN' || ! in_array($locked->status, ['SELLER_RESPONDED', 'UNDER_REVIEW'], true)) $this->invalid('request', 'This request cannot be authorized for physical return.');
            $workflow = ReturnRefundWorkflow::where('return_refund_request_id', $locked->id)->lockForUpdate()->first();
            $shipment = Shipment::where('seller_order_id', $locked->seller_order_id)->first();
            $hub = $shipment?->destinationHub;
            if (! $workflow || ! $hub?->is_active || ! $shipment?->logistics_provider_id || (int) $hub->logistics_provider_id !== (int) $shipment->logistics_provider_id) $this->invalid('request', 'A valid active destination barangay hub is required for buyer drop-off.');
            $reference = 'RR-'.str_pad((string) $locked->id, 8, '0', STR_PAD_LEFT).'-'.strtoupper(bin2hex(random_bytes(16)));
            $locked->update(['status' => 'RETURN_AUTHORIZED']);
            $workflow->update(['logistics_provider_id'=>$shipment->logistics_provider_id,'return_hub_id'=>$hub->id,'return_reference'=>$reference,'logistics_state'=>'AWAITING_BUYER_DROPOFF']);
            ReturnRefundEvent::create(['return_refund_request_id'=>$locked->id,'actor_id'=>$admin->id,'action'=>'RETURN_AUTHORIZED','data'=>['reason'=>trim($reason),'return_reference'=>$reference,'hub_id'=>$hub->id]]);
            return $locked->fresh();
        }, 3);
    }

    public function recordOfflineCodRefund(ReturnRefundRequest $request, User $actor, string $reference, string $note): void
    {
        DB::transaction(function()use($request,$actor,$reference,$note){
            if(!$actor->isLogistics()||$actor->status!=='active'||$actor->is_suspended)abort(403);
            $r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
            if($r->status!=='ADMIN_APPROVED')$this->invalid('request','Only a financially approved refund can be recorded as paid.');
            if($r->type==='RETURN' && ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->whereNotNull('inspected_at')->where('logistics_state','INSPECTED')->doesntExist())$this->invalid('request','A physical return must be inspected before its COD refund can be recorded.');
            $shipment=Shipment::where('seller_order_id',$r->seller_order_id)->lockForUpdate()->firstOrFail();
            if((int)$shipment->logistics_provider_id!==(int)$actor->logisticsProvider?->id)abort(403);
            $accounting=DB::table('return_refund_accounting')->where('return_refund_request_id',$r->id)->lockForUpdate()->first();
            if(!$accounting||$accounting->refund_status==='PAID')return;
            if($accounting->refund_status!=='REFUND_DUE'||!filled($reference)||!filled($note))$this->invalid('refund','Offline refund reference and proof note are required.');
            DB::table('return_refund_accounting')->where('id',$accounting->id)->update(['refund_status'=>'PAID','refund_paid_by'=>$actor->id,'refund_paid_at'=>now(),'refund_paid_reference'=>trim($reference),'refund_paid_note'=>trim($note),'updated_at'=>now()]);
            ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>'COD_REFUND_PAID','data'=>['reference'=>trim($reference),'note'=>trim($note)]]);
        },3);
    }

    private function recordAdminReviewAction(ReturnRefundRequest $request, User $admin, string $action, string $reason): ReturnRefundRequest
    {
        abort_unless($admin->isAdmin() && $admin->status === 'active' && ! $admin->is_suspended, 403);
        if (! filled($reason)) $this->invalid('reason', 'A review reason is required.');

        return DB::transaction(function () use ($request, $admin, $action, $reason) {
            $locked = ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
            if (! in_array($locked->status, ReturnRefundRequest::ACTIVE_STATUSES, true)) {
                $this->invalid('request', 'A final request cannot be reviewed again.');
            }

            $locked->update(['status' => 'UNDER_REVIEW']);
            ReturnRefundEvent::create([
                'return_refund_request_id' => $locked->id,
                'actor_id' => $admin->id,
                'action' => $action,
                'data' => ['reason' => trim($reason)],
            ]);
            app(DomainNotificationService::class)->afterCommit(
                $locked->buyer_id,
                $action === 'EVIDENCE_REQUESTED' ? 'More return/refund evidence requested' : 'Return/refund request escalated',
                trim($reason),
                ['return_refund_request_id' => $locked->id]
            );

            return $locked->fresh();
        }, 3);
    }

    public function decide(ReturnRefundRequest $request, User $admin, string $decision, string $reason): ReturnRefundRequest
    {
        abort_unless($admin->isAdmin() && $admin->status === 'active' && !$admin->is_suspended,403);
        abort_if($request->fresh()->isSellerReview(),403);
        if (!in_array($decision,['APPROVED','REJECTED'],true) || !filled($reason)) $this->invalid('decision','A valid decision and reason are required.');
        return DB::transaction(function () use ($request,$admin,$decision,$reason) {
            $reference = ReturnRefundRequest::findOrFail($request->id);
            [$item,$so,$shipment] = $this->lockedPurchase($reference->order_item_id);
            $r = ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
            abort_if($r->isSellerReview(),403);
            if ((int)$r->order_id !== (int)$so->order_id || (int)$r->seller_order_id !== (int)$so->id
                || (int)$r->seller_id !== (int)$so->seller_id || (int)$r->buyer_id !== (int)$so->order->buyer_id) $this->invalid('request','Request ownership linkage is inconsistent.');
            app(SimulationRefundApprovalGuard::class)->assertMode($so,$r->workflow_mode ?? ReturnRefundRequest::FINANCIAL);
            if (in_array($r->status,['ADMIN_APPROVED','ADMIN_REJECTED'],true)) {
                if ($r->status !== 'ADMIN_'.$decision) $this->invalid('decision','A conflicting final decision cannot replace the recorded decision.');
                return $r;
            }
            if (!in_array($r->status,ReturnRefundRequest::ACTIVE_STATUSES,true)) $this->invalid('request','Request cannot be finalized.');
            if ($r->isAdminReview()) {
                if (!app(ReturnRefundWorkflowService::class)->available(ReturnRefundRequest::ADMIN_REVIEW)) $this->invalid('request','Return/refund review is unavailable until activation and schema readiness are verified.');
                $amount = $r->refund_minor;
                if ($decision === 'APPROVED') {
                    $quote = $this->validatePurchase($item, $so, $shipment);
                    if ((int)$r->quantity !== (int)$item->quantity) $this->invalid('quantity','The request must match the full purchased quantity.');
                    $this->recordRefundAdjustment($r, $so, $quote);
                    $amount = $quote['buyer_refund_due_minor'];
                }
                $r->fill(['status'=>'ADMIN_'.$decision,'admin_decision'=>$decision,'admin_reason'=>trim($reason),
                    'reviewed_by'=>$admin->id,'decided_at'=>now(),'refund_minor'=>$amount])->save();
                $this->event($r,$admin,$r->status);
                app(SellerWalletService::class)->reconcileSellerOrderResult($so);
                foreach ([$r->buyer_id, $so->seller->user_id] as $recipient) {
                    app(DomainNotificationService::class)->afterCommit($recipient, 'Return/refund decision',
                        $decision === 'APPROVED' ? 'Admin approved the request. Contact the other party through Chat; approval does not confirm payment.' : 'Admin rejected the request. View the decision reason.', ['return_refund_request_id'=>$r->id]);
                }
                return $r->fresh();
            }
            if ($r->workflow_mode !== null && $r->workflow_mode !== ReturnRefundRequest::FINANCIAL) $this->invalid('request','Unsupported request workflow. Contact support.');
            // Includes rejection: it must not enter reconciliation via the other gate.
            $this->requireGate(ReturnRefundRequest::FINANCIAL);
            if ($decision === 'APPROVED') {
                $this->enabled();
                app(ReturnRefundWorkflowService::class)->assertApprovable($r);
                if ((int)$r->quantity !== (int)$item->quantity) $this->invalid('quantity','Historical partial requests require support review.');
                $c = $this->validatePurchase($item,$so,$shipment);
                $this->recordRefundAdjustment($r, $so, $c);
            }
            $r->fill(['status'=>'ADMIN_'.$decision,'admin_decision'=>$decision,'admin_reason'=>trim($reason),'reviewed_by'=>$admin->id,'decided_at'=>now()])->save();
            $this->event($r,$admin,$r->status);
            app(SellerWalletService::class)->reconcileSellerOrderResult($so);
            app(DomainNotificationService::class)->afterCommit($r->buyer_id,'Return/refund decision','Your return/refund request was '.strtolower($decision).'.',['return_refund_request_id'=>$r->id]);
            app(DomainNotificationService::class)->afterCommit($so->seller->user_id,'Return/refund decision','Admin finalized a return/refund request.',['return_refund_request_id'=>$r->id]);
            return $r->fresh();
        },3);
    }

    public function sellerDecide(ReturnRefundRequest $request, User $actor, string $decision, string $reason): ReturnRefundRequest
    {
        $reference=ReturnRefundRequest::findOrFail($request->id);
        app(SimulationRefundApprovalGuard::class)->assertMode(SellerOrder::findOrFail($reference->seller_order_id),$reference->workflow_mode ?? ReturnRefundRequest::FINANCIAL);
        $simulation=app(SimulationSettlementService::class);
        return $simulation->installed()
            ? $simulation->sellerDecision($request,$actor,$decision,fn()=>$this->sellerDecideNative($request,$actor,$decision,$reason))
            : $this->sellerDecideNative($request,$actor,$decision,$reason);
    }

    private function sellerDecideNative(ReturnRefundRequest $request, User $actor, string $decision, string $reason): ReturnRefundRequest
    {
        abort_unless($actor->isSeller() && $actor->status === 'active' && !$actor->is_suspended,403);
        if (!in_array($decision,['APPROVED','REJECTED'],true) || !filled($reason)) $this->invalid('decision','A valid decision and reason are required.');
        return DB::transaction(function()use($request,$actor,$decision,$reason){
            $reference=ReturnRefundRequest::findOrFail($request->id);
            [$item,$so,$shipment]=$this->lockedPurchase($reference->order_item_id);
            $r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless((int)$so->seller->user_id===(int)$actor->id && (int)$r->seller_id===(int)$so->seller_id,404);
            abort_unless($r->isSellerReview(),403);
            if ((int)$r->order_id!==(int)$so->order_id || (int)$r->seller_order_id!==(int)$so->id || (int)$r->buyer_id!==(int)$so->order->buyer_id) $this->invalid('request','Request ownership linkage is inconsistent.');
            if (in_array($r->status,['ADMIN_APPROVED','ADMIN_REJECTED'],true)) {
                if ($r->status!=='ADMIN_'.$decision) $this->invalid('decision','A conflicting final decision cannot replace the recorded decision.');
                return $r;
            }
            if (!app(ReturnRefundWorkflowService::class)->available(ReturnRefundRequest::SELLER_REVIEW)) $this->invalid('request','Seller review is unavailable until activation and schema readiness are verified.');
            if ($r->status!=='SUBMITTED') $this->invalid('request','Request cannot be finalized.');
            if ($decision === 'REJECTED') {
                $r->fill(['status'=>'ADMIN_REJECTED','seller_recommendation'=>'REJECT','seller_note'=>trim($reason),'seller_responded_at'=>now(),'reviewed_by'=>$actor->id,'decided_at'=>now()])->save();
                $this->event($r,$actor,'SELLER_REJECTED');
                return $r->fresh();
            }
            if ($r->type !== 'RETURN') $this->invalid('decision','A refund-only request requires the authorized financial review workflow.');
            if (!app(ReturnRefundWorkflowService::class)->hubSchemaReadyForAuthorization()) $this->invalid('request','Physical return routing is unavailable pending migration rollout.');
            $workflow=ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->lockForUpdate()->first();
            $hub=$shipment?->destinationHub;
            if (!$workflow || !$hub?->is_active || !$shipment?->logistics_provider_id || (int)$hub->logistics_provider_id !== (int)$shipment->logistics_provider_id) $this->invalid('request','A valid active designated return hub is required.');
            $reference=$workflow->return_reference ?: 'RR-'.str_pad((string)$r->id,8,'0',STR_PAD_LEFT).'-'.strtoupper(bin2hex(random_bytes(12)));
            $r->fill(['status'=>'RETURN_AUTHORIZED','seller_recommendation'=>'APPROVE','seller_note'=>trim($reason),'seller_responded_at'=>now()])->save();
            $workflow->update(['logistics_provider_id'=>$shipment->logistics_provider_id,'return_hub_id'=>$hub->id,'return_reference'=>$reference,'logistics_state'=>'FOR_PICKUP','virtual_shipping_fee_minor'=>(int)config('returns.virtual_return_shipping_fee_minor',0)]);
            $this->event($r,$actor,'RETURN_AUTHORIZED');
            ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>'RETURN_PICKUP_QUEUED','data'=>['return_reference'=>$reference,'hub_id'=>$hub->id]]);
            foreach ([$r->buyer_id,$actor->id] as $recipient) app(DomainNotificationService::class)->afterCommit($recipient,'Physical return authorized','Logistics will arrange return pickup. No refund payment has been recorded.',['return_refund_request_id'=>$r->id]);
            return $r->fresh();
        },3);
    }

    /** All review modes share the same request-scoped obligation, not a payment. */
    private function recordRefundAdjustment(ReturnRefundRequest $r, SellerOrder $so, array $c): void
    {
        app(SimulationRefundApprovalGuard::class)->assertCoordinated($so,$r);
        // Caller holds Seller, purchase and request locks in one transaction.
        if (!Schema::hasColumn('seller_wallet_entries','source_wallet_entry_id')) $this->invalid('accounting','Earning-release schema has not been reviewed and applied.');
        $entries = SellerWalletEntry::where('seller_order_id',$so->id)->where('type','REFUND_ADJUSTMENT')->orderBy('id')->lockForUpdate()->get();
        if ($entries->contains(fn($entry)=>(int)$entry->seller_id !== (int)$so->seller_id || $entry->amount_minor > 0
            || (int)$entry->return_refund_request_id === (int)$r->id || $entry->reference === 'RR-'.$r->id)) $this->invalid('accounting','Refund adjustment history is inconsistent; contact support.');
        $budget = max(0, (int)$so->subtotal_minor - (int)$so->commission_minor - (int)$so->items->sum('seller_funded_discount_minor'));
        if (-$entries->sum('amount_minor') + $c['seller_adjustment_minor'] > $budget) $this->invalid('accounting','Refund adjustments exceed authoritative seller earnings.');
        DB::table('return_refund_accounting')->insert(['return_refund_request_id'=>$r->id,'merchandise_minor'=>$c['gross_merchandise_minor'],'seller_adjustment_minor'=>$c['seller_adjustment_minor'],'commission_reversal_minor'=>$c['commission_reversed_minor'],'seller_voucher_minor'=>$c['seller_funded_discount_minor'],'platform_voucher_minor'=>$c['platform_funded_discount_minor'],'refund_status'=>'REFUND_DUE','created_at'=>now(),'updated_at'=>now()]);
        SellerWalletEntry::create(['return_refund_request_id'=>$r->id,'seller_id'=>$so->seller_id,'seller_order_id'=>$so->id,'type'=>'REFUND_ADJUSTMENT','status'=>'AVAILABLE','amount_minor'=>-$c['seller_adjustment_minor'],'reference'=>'RR-'.$r->id,'description'=>'Approved refund obligation; no buyer disbursement recorded']);
        $r->refund_minor = $c['buyer_refund_due_minor'];
    }

    private function event(ReturnRefundRequest $r, User $actor, string $action): void
    {
        ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>$action]);
    }
}
