<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VoucherCalculationService
{
    /**
     * Preview a single voucher against checkout CartItem models.
     * All money values are integer centavos; no database writes occur.
     *
     * @return array{voucher_id:int,code:string,owner_type:string,funded_by:string,eligible_subtotal_minor:int,discount_minor:int,seller_discount_minor:int,platform_discount_minor:int,items:array<int,array{product_id:int,seller_id:int,eligible_subtotal_minor:int,discount_minor:int}>}
     */
    public function calculate(string $code, Collection $cartItems, int $userId): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw ValidationException::withMessages(['voucher_code' => 'Enter a voucher code.']);
        }

        $voucher = Voucher::query()
            ->with('products:id')
            ->where('code', $code)
            ->first();

        if (!$voucher || !$voucher->is_enabled || !$voucher->starts_at || !$voucher->ends_at ||
            $voucher->starts_at->isFuture() || !$voucher->ends_at->isFuture()) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher is invalid or not currently available.']);
        }

        if (!in_array($voucher->owner_type, ['seller', 'platform'], true)) {
            throw ValidationException::withMessages(['voucher_code' => 'Unsupported voucher owner.']);
        }

        if ($voucher->owner_type === 'seller' && (!$voucher->seller_id || $voucher->seller?->status !== 'approved')) {
            throw ValidationException::withMessages(['voucher_code' => 'This seller voucher is unavailable.']);
        }

        if ($voucher->usage_limit !== null && $voucher->usage_count >= $voucher->usage_limit) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher has reached its usage limit.']);
        }

        $usedByBuyer = $voucher->redemptions()
            ->where('user_id', $userId)
            ->where('status', 'redeemed')
            ->count();
        if ($voucher->per_user_limit !== null && $usedByBuyer >= $voucher->per_user_limit) {
            throw ValidationException::withMessages(['voucher_code' => 'You have reached your usage limit for this voucher.']);
        }

        $selectedIds = $voucher->product_scope === 'selected'
            ? $voucher->products->pluck('id')->map(fn ($id) => (int) $id)->all()
            : null;

        if (!in_array($voucher->product_scope, ['all', 'selected'], true)) {
            throw ValidationException::withMessages(['voucher_code' => 'Invalid voucher product scope.']);
        }

        $eligible = [];
        foreach ($cartItems as $index => $item) {
            $product = $item->product;
            if (!$product) {
                continue;
            }
            $sellerId = (int) $product->seller_id;
            $productId = (int) $product->id;
            if ($voucher->owner_type === 'seller' && $sellerId !== (int) $voucher->seller_id) {
                continue;
            }
            if ($selectedIds !== null && !in_array($productId, $selectedIds, true)) {
                continue;
            }
            $quantity = (int) $item->quantity;
            $priceMinor = (int) ($item->variation
                ? $item->variation->effective_price_minor
                : $product->effective_price_minor);
            if ($quantity <= 0 || $priceMinor < 0) {
                throw ValidationException::withMessages(['voucher_code' => 'Invalid checkout item quantity or price.']);
            }
            $amount = $priceMinor * $quantity;
            if ($amount > 0) {
                $eligible[] = [
                    'cart_index' => (int) $index,
                    'product_id' => $productId,
                    'seller_id' => $sellerId,
                    'eligible_subtotal_minor' => $amount,
                    'discount_minor' => 0,
                ];
            }
        }

        $subtotal = array_sum(array_column($eligible, 'eligible_subtotal_minor'));
        if ($subtotal <= 0) {
            throw ValidationException::withMessages(['voucher_code' => 'No eligible products for this voucher.']);
        }
        if ($subtotal < (int) $voucher->minimum_spend_minor) {
            throw ValidationException::withMessages(['voucher_code' => 'The eligible subtotal does not meet the voucher minimum spend.']);
        }

        if ($voucher->discount_type === 'fixed') {
            $discount = min($subtotal, (int) $voucher->discount_value_minor);
        } elseif ($voucher->discount_type === 'percentage') {
            $discount = intdiv($subtotal * (int) $voucher->discount_percent_bps + 5000, 10000);
            if ($voucher->maximum_discount_minor !== null) {
                $discount = min($discount, (int) $voucher->maximum_discount_minor);
            }
            $discount = min($subtotal, $discount);
        } else {
            throw ValidationException::withMessages(['voucher_code' => 'Unsupported voucher discount type.']);
        }
        if ($discount <= 0) {
            throw ValidationException::withMessages(['voucher_code' => 'This voucher provides no discount for these items.']);
        }

        // Proportional allocation in centavos; last item receives remainder.
        $allocated = 0;
        $lastIndex = count($eligible) - 1;
        foreach ($eligible as $i => &$row) {
            $part = $i === $lastIndex
                ? $discount - $allocated
                : intdiv($discount * $row['eligible_subtotal_minor'], $subtotal);
            $row['discount_minor'] = $part;
            $allocated += $part;
        }
        unset($row);

        $isSellerFunded = $voucher->owner_type === 'seller';
        return [
            'voucher_id' => (int) $voucher->id,
            'code' => $voucher->code,
            'owner_type' => $voucher->owner_type,
            'funded_by' => $isSellerFunded ? 'seller' : 'platform',
            'eligible_subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'seller_discount_minor' => $isSellerFunded ? $discount : 0,
            'platform_discount_minor' => $isSellerFunded ? 0 : $discount,
            'items' => $eligible,
        ];
    }
}
