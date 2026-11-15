<?php

namespace App\Services;

use App\Models\SellerOrder;
use Illuminate\Validation\ValidationException;

/** Applies only to an explicitly created prospective simulation scenario. */
final class SimulationSettlementQuote
{
    public const POLICY = 'ACADEMIC_SIMULATION_V1_NO_REVERSE_CHARGE';

    public function calculate(SellerOrder $order): array
    {
        $seller = $platform = $refund = 0;
        $lines = [];
        foreach ($order->items()->orderBy('id')->get() as $item) {
            $q = app(ReturnRefundAllocationService::class)->calculate($item, (int) $item->quantity);
            $gross = $q['gross_merchandise_minor'];
            $ds = $q['seller_funded_discount_minor'];
            $dp = $q['platform_funded_discount_minor'];
            $commission = $q['commission_reversed_minor'];
            if ($commission > $gross - $ds || $dp > $commission) $this->invalid();
            foreach ([$q['seller_adjustment_minor'], $commission - $dp, $q['buyer_refund_due_minor']] as $amount) {
                if ($amount > intdiv(PHP_INT_MAX, 4)) $this->invalid();
            }
            $seller += $q['seller_adjustment_minor'];
            $platform += $commission - $dp;
            $refund += $q['buyer_refund_due_minor'];
            if (max($seller, $platform, $refund) > intdiv(PHP_INT_MAX, 4)) $this->invalid();
            $lines[(int) $item->id] = $q;
        }
        $shipping = filter_var($order->getRawOriginal('shipping_fee_minor'), FILTER_VALIDATE_INT);
        if (!$lines || $refund<1 || $shipping !== CheckoutService::SHIPPING_FEE_PER_SELLER_MINOR || $seller + $platform !== $refund) $this->invalid();
        return ['policy' => self::POLICY, 'currency' => 'PHP', 'reverse_shipping_minor' => 0,
            'reverse_shipping_payer' => 'SELLER', 'seller_minor' => $seller, 'platform_minor' => $platform,
            'shipping_minor' => $shipping, 'cap_minor' => $refund + $shipping, 'items' => $lines];
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['simulation' => 'This purchase cannot form a complete bounded simulation source. Historical amounts and unfunded subsidies are not admitted.']);
    }
}
