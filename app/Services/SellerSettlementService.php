<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\SellerSettlement;
use Illuminate\Support\Facades\DB;

class SellerSettlementService
{
    /**
     * Read-only settlement eligibility assessment.
     * Does not create settlements, approve payouts,
     * or transfer money.
     */
    public function assess(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing('seller', 'shipment', 'order');

        $reasons = [];

        $parentOrder = $sellerOrder->order;
        $seller = $sellerOrder->seller;
        $shipment = $sellerOrder->shipment;
        $sellerUserId = $seller?->user_id;

        // 1. Check complaints and disputes.
        if ($sellerUserId) {
            $hasFinancialCase = DB::table('support_tickets')
                ->where('order_id', $sellerOrder->order_id)
                ->whereIn('type', ['complaint', 'dispute'])
                ->where(function ($query) use ($sellerUserId) {
                    $query->where('against_user_id', $sellerUserId)
                        ->orWhereNull('against_user_id');
                })
                ->exists();

            if ($hasFinancialCase) {
                $reasons[] = 'Complaint or dispute requires financial clearance.';
            }
        }

        // 2. Validate parent order.
        if (!$parentOrder) {
            $reasons[] = 'Parent order is missing.';
        } else {
            if (!in_array(
                strtolower((string) $parentOrder->status),
                ['delivered', 'completed'],
                true
            )) {
                $reasons[] = 'Parent order is not delivered/completed.';
            }

            if ($parentOrder->cancelled_at !== null) {
                $reasons[] = 'Parent order has been cancelled.';
            }

            if (!in_array(
                strtolower((string) $parentOrder->return_status),
                ['none', ''],
                true
            )) {
                $reasons[] = 'Parent order has a return status requiring review.';
            }
        }

        // 3. Validate seller order.
        if (!in_array(
            strtolower((string) $sellerOrder->status),
            ['delivered', 'completed'],
            true
        )) {
            $reasons[] = 'Seller order is not delivered/completed.';
        }

        if (strtolower((string) $sellerOrder->return_status) !== 'none') {
            $reasons[] = 'Seller order has a return status requiring review.';
        }

        // 4. Validate seller.
        if (!$seller || !$seller->user_id) {
            $reasons[] = 'Seller owner is missing.';
        }

        // 5. Validate shipment.
        if (!$shipment) {
            $reasons[] = 'Shipment is missing.';
        } elseif (
            strtolower((string) $shipment->status) !== 'delivered'
            || !$shipment->delivered_at
        ) {
            $reasons[] = 'Shipment delivery is not verified.';
        }

        // 6. Find commission record.
        // commissions.seller_id references sellers.user_id.
        $commission = null;

        if ($sellerUserId) {
            $commission = DB::table('commissions')
                ->where('order_id', $sellerOrder->order_id)
                ->where('seller_id', $sellerUserId)
                ->first();
        }

        if (!$commission) {
            $reasons[] = 'Commission record is missing.';
        }

        // 7. Validate voucher discount amounts.
        $originalSubtotalMinor = (int) $sellerOrder->subtotal_minor;

        $sellerDiscountMinor = (int) (
            $sellerOrder->seller_discount_minor ?? 0
        );

        $platformDiscountMinor = (int) (
            $sellerOrder->platform_discount_minor ?? 0
        );

        $totalDiscountMinor = (int) (
            $sellerOrder->discount_minor ?? 0
        );

        if (
            $originalSubtotalMinor <= 0
            || $sellerDiscountMinor < 0
            || $platformDiscountMinor < 0
            || $totalDiscountMinor !==
            $sellerDiscountMinor + $platformDiscountMinor
            || $totalDiscountMinor > $originalSubtotalMinor
        ) {
            $reasons[] = 'Invalid seller voucher discount amounts.';
        }

        // Seller-funded discount reduces seller revenue.
        // Platform-funded discount does not.
        $subtotalMinor = max(
            0,
            $originalSubtotalMinor - $sellerDiscountMinor
        );

        $commissionMinor = $commission
            ? (int) $commission->amount_minor
            : 0;

        $expectedCommissionMinor = intdiv(
            $subtotalMinor * 10 + 50,
            100
        );

        if ($commission && (
            $commissionMinor !== $expectedCommissionMinor
            || (int) $commission->order_total_minor !== $subtotalMinor
        )) {
            $reasons[] = 'Commission amount does not match seller revenue.';
        }

        // Do not release platform-funded voucher settlements
        // until a verified subsidy funding mechanism exists.
        if ($platformDiscountMinor > 0) {
            $reasons[] =
                'Platform voucher subsidy funding requires financial verification.';
        }

        // 8. Validate COD payment and confirmed remittance.
        $payment = null;

        if ($shipment) {
            $payments = DB::table('payments')
                ->where('order_id', $sellerOrder->order_id)
                ->where('shipment_id', $shipment->id)
                ->where('method', 'cod')
                ->where('status', 'paid')
                ->get();

            if ($payments->count() !== 1) {
                $reasons[] = 'Expected exactly one paid COD payment for shipment.';
            } else {
                $payment = $payments->first();

                $confirmedMinor = (int) DB::table(
                    'cod_remittance_payments as crp'
                )
                    ->join(
                        'cod_remittances as cr',
                        'cr.id',
                        '=',
                        'crp.cod_remittance_id'
                    )
                    ->where('crp.payment_id', $payment->id)
                    ->where('cr.status', 'confirmed')
                    ->sum('crp.amount_minor');

                if (
                    (int) $payment->amount_minor <= 0
                    || $confirmedMinor !== (int) $payment->amount_minor
                ) {
                    $reasons[] = 'COD payment is not fully confirmed as remitted.';
                }

                // Rider must collect exactly the discounted
                // shipment total, not the original amount.
                if (
                    (int) $payment->amount_minor !==
                    (int) $sellerOrder->total_minor
                ) {
                    $reasons[] =
                        'COD payment does not match discounted shipment total.';
                }
            }
        }

        // 9. Return assessment without database writes.
        return [
            'eligible' => count($reasons) === 0,
            'seller_order_id' => $sellerOrder->id,
            'seller_id' => $sellerOrder->seller_id,
            'payment_id' => $payment?->id,
            'subtotal_minor' => $subtotalMinor,
            'commission_minor' => $commissionMinor,
            'net_minor' => $commission
                ? max(0, $subtotalMinor - $commissionMinor)
                : null,
            'reasons' => $reasons,
        ];
    }

    /**
     * Create a settlement only when eligible.
     * Never overwrite existing settlements.
     */
    public function createIfEligible(int $sellerOrderId): array
    {
        return DB::transaction(function () use ($sellerOrderId) {
            $sellerOrder = SellerOrder::query()
                ->whereKey($sellerOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            $assessment = $this->assess($sellerOrder);

            if (!$assessment['eligible']) {
                return [
                    'created' => false,
                    'eligible' => false,
                    'settlement_id' => null,
                    'reasons' => $assessment['reasons'],
                ];
            }

            $existing = SellerSettlement::query()
                ->where('seller_order_id', $sellerOrder->id)
                ->first();

            if ($existing) {
                return [
                    'created' => false,
                    'eligible' => true,
                    'settlement_id' => $existing->id,
                    'status' => $existing->status,
                    'reasons' => [],
                ];
            }

            $settlement = SellerSettlement::create([
                'seller_order_id' => $sellerOrder->id,
                'seller_id' => $assessment['seller_id'],
                'subtotal_minor' => $assessment['subtotal_minor'],
                'commission_minor' => $assessment['commission_minor'],
                'net_minor' => $assessment['net_minor'],
                'status' => 'eligible',
                'approved_by' => null,
                'approved_at' => null,
                'paid_at' => null,
                'payout_reference' => null,
            ]);

            return [
                'created' => true,
                'eligible' => true,
                'settlement_id' => $settlement->id,
                'status' => $settlement->status,
                'reasons' => [],
            ];
        }, 3);
    }
}
