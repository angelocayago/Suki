<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\{Order, OrderItem, Product, ProductVariant, ReturnRefundEvent, ReturnRefundRequest, Rider, RiderCodRemittance, Seller, SellerOrder, SellerWalletEntry, Shipment, User, VoucherRedemption};
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
            ReturnRefundRequest::ADMIN_REVIEW => 'returns.admin_review_enabled',
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
            || (int)$liability->expected_minor !== $expected || !in_array($liability->status, ['OUTSTANDING','SUBMITTED','ACCEPTED'], true)
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
            if (!$remittance || $liability->status !== 'ACCEPTED' || $allocation->amount_minor !== $expected
                || (int)$remittance->logistics_provider_id !== (int)$shipment->logistics_provider_id
                || !in_array($remittance->status,['SUBMITTED','ACCEPTED'],true)
                || (int)$remittance->expected_minor !== (int)$remittance->items->sum('amount_minor')
                || (int)$remittance->submitted_minor !== (int)$remittance->expected_minor
                || ($remittance->status === 'ACCEPTED' && (int)$remittance->accepted_minor !== (int)$remittance->expected_minor)) {
                $this->invalid('payment','Upstream COD settlement is inconsistent; contact support.');
            }
        }
        return app(ReturnRefundAllocationService::class)->calculate($item, (int)$item->quantity);
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
        if (!app(ReturnRefundWorkflowService::class)->available()
            || !Schema::hasColumn('return_refund_requests', 'workflow_mode')) {
            $this->invalid('request', 'Return/refund submission is unavailable until activation and schema readiness are verified.');
        }
        return $this->submitPurchase($buyer, $item, $data, ReturnRefundRequest::ADMIN_REVIEW);
    }

    private function submitPurchase(User $buyer, OrderItem $item, array $data, string $mode): ReturnRefundRequest
    {
        $this->enabled($mode);
        return DB::transaction(function () use ($buyer, $item, $data, $mode) {
            [$item, $so, $shipment] = $this->lockedPurchase($item->id);
            abort_unless($buyer->isBuyer() && $buyer->status === 'active' && !$buyer->is_suspended && (int)$so->order->buyer_id === (int)$buyer->id, 403);
            if (ReturnRefundRequest::where('order_item_id',$item->id)->exists()) $this->invalid('item','A lifetime request already exists for this item. View the existing request; resubmission and appeals are not supported.');
            if ((int)$data['quantity'] !== (int)$item->quantity) $this->invalid('quantity','Only the full purchased quantity is supported; partial returns are unavailable.');
            if (!in_array($data['type'], ['RETURN','REFUND'],true)) $this->invalid('type','Choose RETURN or REFUND.');
            $quote = $this->validatePurchase($item, $so, $shipment);
            $r = new ReturnRefundRequest(['buyer_id'=>$buyer->id,'order_id'=>$so->order_id,'seller_order_id'=>$so->id,'order_item_id'=>$item->id,'seller_id'=>$so->seller_id,'type'=>$data['type'],'status'=>'SUBMITTED','quantity'=>$item->quantity,'buyer_reason'=>$data['reason'],'buyer_description'=>$data['description']]);
            if (Schema::hasColumn('return_refund_requests', 'workflow_mode')) $r->workflow_mode = $mode;
            if ($mode === ReturnRefundRequest::ADMIN_REVIEW) $r->refund_minor = $quote['buyer_refund_due_minor'];
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
        $so->load(['seller','order','items','shipment']);return $this->validatePurchase($item,$so,$so->shipment);
    }

    public function sellerRespond(ReturnRefundRequest $request, User $seller, array $data): ReturnRefundRequest
    {
        return DB::transaction(function () use ($request,$seller,$data) {
            $r = ReturnRefundRequest::with('seller')->lockForUpdate()->findOrFail($request->id);
            abort_unless((int)$r->seller->user_id === (int)$seller->id,403);
            if ($r->isAdminReview()) $this->invalid('request','This request is reviewed by Admin. Use Chat to coordinate after approval.');
            $this->requireGate(ReturnRefundRequest::FINANCIAL);
            if ($r->status !== 'SUBMITTED') $this->invalid('request','Request is no longer awaiting seller response.');
            $r->update(['status'=>'SELLER_RESPONDED','seller_recommendation'=>$data['recommendation'],'seller_note'=>$data['note'],'seller_responded_at'=>now()]);
            $this->event($r,$seller,'SELLER_RESPONDED');
            app(DomainNotificationService::class)->afterCommit($r->buyer_id,'Return/refund response','The seller responded; Admin review is required.',['return_refund_request_id'=>$r->id]);
            return $r;
        },3);
    }

    public function decide(ReturnRefundRequest $request, User $admin, string $decision, string $reason): ReturnRefundRequest
    {
        abort_unless($admin->isAdmin() && $admin->status === 'active' && !$admin->is_suspended,403);
        if (!in_array($decision,['APPROVED','REJECTED'],true) || !filled($reason)) $this->invalid('decision','A valid decision and reason are required.');
        return DB::transaction(function () use ($request,$admin,$decision,$reason) {
            $reference = ReturnRefundRequest::findOrFail($request->id);
            [$item,$so,$shipment] = $this->lockedPurchase($reference->order_item_id);
            $r = ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
            if ((int)$r->order_id !== (int)$so->order_id || (int)$r->seller_order_id !== (int)$so->id
                || (int)$r->seller_id !== (int)$so->seller_id || (int)$r->buyer_id !== (int)$so->order->buyer_id) $this->invalid('request','Request ownership linkage is inconsistent.');
            if (in_array($r->status,['ADMIN_APPROVED','ADMIN_REJECTED'],true)) {
                if ($r->status !== 'ADMIN_'.$decision) $this->invalid('decision','A conflicting final decision cannot replace the recorded decision.');
                return $r;
            }
            if (!in_array($r->status,ReturnRefundRequest::ACTIVE_STATUSES,true)) $this->invalid('request','Request cannot be finalized.');
            if ($r->isAdminReview()) {
                if (!app(ReturnRefundWorkflowService::class)->available()) $this->invalid('request','Return/refund review is unavailable until activation and schema readiness are verified.');
                $amount = $r->refund_minor;
                if ($decision === 'APPROVED') {
                    $quote = $this->validatePurchase($item, $so, $shipment);
                    if ((int)$r->quantity !== (int)$item->quantity) $this->invalid('quantity','The request must match the full purchased quantity.');
                    $amount = $quote['buyer_refund_due_minor'];
                }
                $r->fill(['status'=>'ADMIN_'.$decision,'admin_decision'=>$decision,'admin_reason'=>trim($reason),
                    'reviewed_by'=>$admin->id,'decided_at'=>now(),'refund_minor'=>$amount])->save();
                $this->event($r,$admin,$r->status);
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
                // Original HELD rows are preserved; the common reconciler may
                // append a release only after this and all other holds clear.
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
            $r->fill(['status'=>'ADMIN_'.$decision,'admin_decision'=>$decision,'admin_reason'=>trim($reason),'reviewed_by'=>$admin->id,'decided_at'=>now()])->save();
            $this->event($r,$admin,$r->status);
            app(SellerWalletService::class)->reconcileSellerOrderResult($so);
            app(DomainNotificationService::class)->afterCommit($r->buyer_id,'Return/refund decision','Your return/refund request was '.strtolower($decision).'.',['return_refund_request_id'=>$r->id]);
            app(DomainNotificationService::class)->afterCommit($so->seller->user_id,'Return/refund decision','Admin finalized a return/refund request.',['return_refund_request_id'=>$r->id]);
            return $r->fresh();
        },3);
    }

    private function event(ReturnRefundRequest $r, User $actor, string $action): void
    {
        ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>$action]);
    }
}
