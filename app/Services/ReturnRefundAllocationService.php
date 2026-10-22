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
        if ($quantity < 1 || $quantity > (int) $item->quantity) throw ValidationException::withMessages(['quantity' => 'Invalid refund quantity.']);
        $share = fn (int $amount): int => intdiv($amount * $quantity, (int) $item->quantity);
        $gross = $share((int) $item->unit_price_minor * (int) $item->quantity);
        $seller = $share((int) ($item->seller_funded_discount_minor ?? 0));
        $platform = $share((int) ($item->platform_funded_discount_minor ?? 0));
        $commission = $share((int) $item->commission_minor);
        return ['gross_merchandise_minor'=>$gross,'seller_funded_discount_minor'=>$seller,'platform_funded_discount_minor'=>$platform,'commission_reversed_minor'=>$commission,'seller_adjustment_minor'=>max(0,$gross-$seller-$commission),'buyer_refund_due_minor'=>max(0,$gross-$seller-$platform)];
    }
}
