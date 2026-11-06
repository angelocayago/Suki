<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\VoucherRedemption;
use Illuminate\Validation\ValidationException;

class ReturnRefundAllocationService
{
    public function calculate(OrderItem $item, int $quantity): array
    {
        $hasVoucher = VoucherRedemption::query()->where('seller_order_id', $item->seller_order_id)->exists();
        if ($hasVoucher && ($item->getRawOriginal('seller_funded_discount_minor') === null || $item->getRawOriginal('platform_funded_discount_minor') === null)) {
            throw ValidationException::withMessages(['accounting' => 'Authoritative refund allocation is unavailable for this legacy order.']);
        }
        if ($quantity < 1 || $quantity !== (int) $item->quantity) throw ValidationException::withMessages(['quantity' => 'This MVP requires the full purchased quantity; partial returns are not supported.']);
        foreach (['unit_price_minor','commission_minor','seller_funded_discount_minor','platform_funded_discount_minor'] as $field) {
            $raw = $item->getRawOriginal($field) ?? $item->getAttributes()[$field] ?? null;
            if ($raw === null || !ctype_digit((string)$raw)) throw ValidationException::withMessages(['accounting' => 'Complete nonnegative purchase snapshots are required; contact support for review.']);
        }
        if ((int)$item->unit_price_minor > intdiv(PHP_INT_MAX, $quantity)) throw ValidationException::withMessages(['accounting'=>'Purchase amount exceeds supported precision.']);
        // Full quantity consumes exact saved allocations, with no proration or
        // intermediate amount*quantity multiplication that could overflow.
        $gross = (int) $item->unit_price_minor * $quantity;
        $seller = (int) $item->seller_funded_discount_minor;
        $platform = (int) $item->platform_funded_discount_minor;
        $commission = (int) $item->commission_minor;
        if ($seller > $gross || $platform > $gross - $seller || $commission > $gross) {
            throw ValidationException::withMessages(['accounting'=>'Purchase allocations do not reconcile; support review is required.']);
        }
        return ['gross_merchandise_minor'=>$gross,'seller_funded_discount_minor'=>$seller,'platform_funded_discount_minor'=>$platform,'commission_reversed_minor'=>$commission,'seller_adjustment_minor'=>max(0,$gross-$seller-$commission),'buyer_refund_due_minor'=>max(0,$gross-$seller-$platform)];
    }
}
