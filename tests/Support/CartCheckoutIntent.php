<?php

namespace Tests\Support;

use App\Models\{CartItem, User};
use App\Services\CheckoutService;

/** Explicit server-side purchase-intent fixtures for existing web Checkout tests.
 * New selection tests exercise the real selection endpoint independently.
 */
trait CartCheckoutIntent
{
    private array $cartIntentFixtures = [];

    protected function cartCheckoutIntent(?User $buyer = null): array
    {
        $buyer ??= auth()->user();
        if (!$buyer || !$buyer->isBuyer()) return [];
        $ids = CartItem::where('user_id', $buyer->id)->orderBy('id')->pluck('id')->all();
        if (!$ids) return $this->cartIntentFixtures[$buyer->id]['parameters'] ?? [];
        $codes = session('checkout_voucher_codes', []);
        $summary = app(CheckoutService::class)->cartSummary($buyer, $codes, $ids);
        $fingerprints = app(CheckoutService::class)->fingerprints($summary['cart']);
        $identity = [$fingerprints, $codes];
        if (($this->cartIntentFixtures[$buyer->id]['identity'] ?? null) === $identity) return $this->cartIntentFixtures[$buyer->id]['parameters'];
        $token = bin2hex(random_bytes(32));
        $this->withSession([
            'cart_selection.'.$buyer->id => ['ids' => $ids, 'revision' => 1],
            'cart_checkouts.'.$token => ['buyer_id' => $buyer->id, 'ids' => $ids,
                'fingerprints' => $fingerprints, 'voucher_codes' => $codes,
                'total_minor' => (int) round($summary['total'] * 100), 'expires_at' => now()->addMinutes(30)->timestamp],
        ]);
        $parameters = ['cart_selection' => $token];
        $this->cartIntentFixtures[$buyer->id] = compact('identity','parameters');
        return $parameters;
    }

    protected function selectedCartUrl(): string
    {
        $this->cartCheckoutIntent();
        return route('buyer.cart');
    }
}
