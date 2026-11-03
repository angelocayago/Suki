<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function resolve(array $codes, Collection $sellerSubtotals, ?User $buyer = null): array
    {
        return $this->calculate($codes, $sellerSubtotals, true, $buyer);
    }

    public function preview(array $codes, Collection $sellerSubtotals, ?User $buyer = null): array
    {
        try {
            return ['redemptions' => $this->calculate($codes, $sellerSubtotals, false, $buyer), 'error' => null];
        } catch (ValidationException $exception) {
            return ['redemptions' => [], 'error' => collect($exception->errors()['voucher'] ?? [])->first()];
        }
    }

    private function calculate(array $codes, Collection $sellerSubtotals, bool $lock, ?User $buyer): array
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($code) => strtoupper(trim($code)), $codes))));
        if (! $codes) return [];

        // Stable priority and lock order for both preview and checkout.
        $query = Voucher::query()->whereIn('code', $codes)->orderBy('id');
        if ($lock) $query->lockForUpdate();
        $vouchers = $query->get();
        if ($vouchers->count() !== count($codes)) {
            throw ValidationException::withMessages(['voucher' => 'One or more voucher codes are invalid.']);
        }
        $remaining = $sellerSubtotals->map(fn ($subtotal) => (int) $subtotal);
        if ($remaining->contains(fn ($subtotal) => $subtotal < 0)) {
            throw ValidationException::withMessages(['voucher' => 'Invalid eligible merchandise amount.']);
        }
        $now = now();
        $out = [];
        foreach ($vouchers as $voucher) {
            if ($voucher->scope === 'SELLER' && ($voucher->audience !== null || $voucher->per_buyer_limit !== null)) {
                app(ShopVoucherService::class)->assertRedeemable($voucher, $buyer);
            }
            if (! $voucher->is_active || $voucher->archived_at
                || ($voucher->starts_at && $voucher->starts_at->gt($now))
                || ($voucher->expires_at && $voucher->expires_at->lt($now))
                || ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit)) {
                throw ValidationException::withMessages(['voucher' => 'Voucher '.$voucher->code.' is unavailable.']);
            }
            if (! in_array($voucher->scope, ['SELLER', 'PLATFORM'], true)) {
                throw ValidationException::withMessages(['voucher' => 'Unsupported voucher scope.']);
            }
            $eligible = $voucher->scope === 'SELLER'
                ? collect([$voucher->seller_id => (int) $sellerSubtotals->get($voucher->seller_id, 0)])
                : $sellerSubtotals;
            if ($voucher->scope === 'SELLER' && (! $voucher->seller_id || ! $eligible->sum())) {
                throw ValidationException::withMessages(['voucher' => $voucher->audience !== null ? 'This voucher is only valid for products from '.$voucher->seller->name.'.' : 'Store voucher does not match this cart.']);
            }
            // Preserve the tested pre-discount minimum-spend and percentage
            // basis, but consume each seller's merchandise capacity only once.
            $base = (int) $eligible->sum();
            if ($base < (int) $voucher->minimum_spend_minor) {
                throw ValidationException::withMessages(['voucher' => 'Voucher '.$voucher->code.' requires at least PHP '.number_format($voucher->minimum_spend_minor / 100, 2).' of eligible merchandise'.($voucher->audience !== null ? ' from '.$voucher->seller->name : '').'.']);
            }
            $requested = min($base, $this->amount($base, $voucher));
            if ($requested <= 0) {
                throw ValidationException::withMessages(['voucher' => 'Voucher has no eligible merchandise.']);
            }
            $capacity = $remaining->only($eligible->keys()->all());
            $amount = min($requested, (int) $capacity->sum());
            // Fully overlapped codes provide no discount and consume no usage.
            if ($amount === 0) continue;
            $allocation = $this->allocate($capacity, $amount);
            foreach ($allocation as $sellerId => $share) $remaining[$sellerId] -= $share;
            $out[] = ['voucher' => $voucher, 'amount' => $amount, 'allocation' => $allocation];
        }
        return $out;
    }

    private function amount(int $base, Voucher $voucher): int
    {
        $amount = match ($voucher->discount_type) {
            'PERCENTAGE' => intdiv($base * $voucher->discount_bps + 5000, 10000),
            'FIXED' => (int) $voucher->discount_minor,
            default => throw ValidationException::withMessages(['voucher' => 'Unsupported voucher discount type.']),
        };
        return $voucher->max_discount_minor !== null ? min($amount, $voucher->max_discount_minor) : $amount;
    }

    private function allocate(Collection $subtotals, int $amount): array
    {
        $left = $amount;
        $out = [];
        foreach ($subtotals->sortKeys() as $sellerId => $subtotal) {
            $part = min((int) $subtotal, $left);
            $out[$sellerId] = $part;
            $left -= $part;
        }
        if ($left !== 0) {
            throw ValidationException::withMessages(['voucher' => 'Discount allocation could not be reconciled.']);
        }
        return $out;
    }
}
