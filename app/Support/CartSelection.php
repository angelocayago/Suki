<?php

namespace App\Support;

use App\Models\{CartItem, Order};
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Buyer-session draft and independent, bounded web Checkout attempts. */
final class CartSelection
{
    private function key(Request $request): string
    {
        abort_unless($request->user()?->isBuyer() && $request->user()->status === 'active' && !$request->user()->is_suspended, 403);
        return 'cart_selection.'.$request->user()->id;
    }

    public function draft(Request $request): array
    {
        $key = $this->key($request);
        $draft = $request->session()->get($key, ['ids' => [], 'revision' => 0]);
        $owned = CartItem::where('user_id', $request->user()->id)->whereIn('id', $draft['ids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($owned);
        if ($owned !== $draft['ids']) {
            $draft = ['ids' => $owned, 'revision' => $draft['revision'] + 1];
            $request->session()->put($key, $draft);
            $request->session()->flash('info', 'Removed Cart items were removed from your selection. Review your remaining selection.');
        }
        return $draft;
    }

    public function save(Request $request, CheckoutService $checkout): array
    {
        $data = $request->validate(['draft_revision' => ['required','integer','min:0'], 'cart_item_ids' => ['sometimes','array'], 'cart_item_ids.*' => ['required','integer','min:1','distinct']]);
        $draft = $this->draft($request);
        if ((int) $data['draft_revision'] !== $draft['revision']) {
            throw ValidationException::withMessages(['draft_revision' => 'Your selection changed in another tab. The latest selection has been restored. Please review it.'])->status(409);
        }
        $ids = array_map('intval', $data['cart_item_ids'] ?? []);
        if (count($ids) !== count(array_unique($ids))) throw ValidationException::withMessages(['cart_item_ids' => 'Select each Cart row only once.']);
        sort($ids);
        $summary = $checkout->cartSummary($request->user(), [], $ids);
        foreach ($summary['cart'] as $line) {
            if (!$line['available'] && !in_array($line['id'], $draft['ids'], true)) {
                throw ValidationException::withMessages(['cart_item_ids' => $line['name'].': '.$line['availability_issue']]);
            }
        }
        $draft = ['ids' => $ids, 'revision' => $draft['revision'] + 1];
        $request->session()->put($this->key($request), $draft);
        return $draft;
    }

    /** A changed row keeps its membership, but older draft submissions must reconcile. */
    public function advanceRevision(Request $request, array $draft): void
    {
        $request->session()->put($this->key($request), ['ids' => $draft['ids'], 'revision' => $draft['revision'] + 1]);
    }

    public function token(Request $request): string
    {
        $this->key($request);
        return $request->validate(['cart_selection' => ['required','string','regex:/^[a-f0-9]{64}$/']])['cart_selection'];
    }

    public function state(Request $request, string $token): array
    {
        $this->key($request);
        $state = $request->session()->get('cart_checkouts.'.$token);
        if (!$state || (int) $state['buyer_id'] !== (int) $request->user()->id || $state['expires_at'] <= now()->timestamp) {
            throw ValidationException::withMessages(['cart_selection' => 'Your Checkout selection expired or is unavailable. Review your Cart and choose Checkout again.']);
        }
        return $state;
    }

    public function start(Request $request, array $summary, array $ids): string
    {
        $this->key($request);
        if (!$ids) throw ValidationException::withMessages(['cart_selection' => 'Select at least one item to check out.']);
        if ($summary['has_unavailable_items']) throw ValidationException::withMessages(['cart_selection' => $this->invalidMessage($summary)]);
        if ($summary['voucher_error']) throw ValidationException::withMessages(['voucher_code' => $summary['voucher_error']]);
        $states = array_filter($request->session()->get('cart_checkouts', []), fn ($state) => $state['expires_at'] > now()->timestamp && (int) $state['buyer_id'] === (int) $request->user()->id);
        $token = bin2hex(random_bytes(32));
        $states[$token] = ['buyer_id' => $request->user()->id, 'ids' => $ids,
            'fingerprints' => app(CheckoutService::class)->fingerprints($summary['cart']),
            'total_minor' => (int) round($summary['total'] * 100),
            'voucher_codes' => $summary['voucher_codes'], 'expires_at' => now()->addMinutes(30)->timestamp];
        $request->session()->put('cart_checkouts', array_slice($states, -5, null, true));
        return $token;
    }

    public function preview(Request $request, string $token, CheckoutService $checkout): array
    {
        $state = $this->state($request, $token);
        $summary = $checkout->cartSummary($request->user(), $state['voucher_codes'], $state['ids']);
        if ($checkout->fingerprints($summary['cart']) !== $state['fingerprints']) {
            throw ValidationException::withMessages(['cart_selection' => 'A selected item changed in quantity, price, variant or store. Return to Cart to review and confirm your selection again.']);
        }
        if ((int) round($summary['total'] * 100) !== $state['total_minor'] && !$summary['voucher_error'] && !$summary['has_unavailable_items']) {
            throw ValidationException::withMessages(['cart_selection' => 'Your selected total changed. Return to Cart to review and confirm it again.']);
        }
        return $summary;
    }

    public function reference(Request $request, string $token): string
    {
        return 'CC-'.strtoupper(substr(hash_hmac('sha256', $request->user()->id.'|'.$token, (string) config('app.key')), 0, 32));
    }

    public function completed(Request $request, string $token): ?Order
    {
        $this->key($request);
        return Order::where('buyer_id', $request->user()->id)->where('order_number', $this->reference($request, $token))->first();
    }

    public function putCodes(Request $request, string $token, array $codes, CheckoutService $checkout): array
    {
        $this->preview($request, $token, $checkout);
        $state = $this->state($request, $token);
        $summary = $checkout->cartSummary($request->user(), $codes, $state['ids']);
        if ($summary['voucher_error']) throw ValidationException::withMessages(['voucher_code' => $summary['voucher_error']]);
        $request->session()->put('cart_checkouts.'.$token, array_replace($state, ['voucher_codes' => $summary['voucher_codes'], 'total_minor' => (int) round($summary['total'] * 100)]));
        return $summary;
    }

    public function invalidMessage(array $summary): string
    {
        $invalid = array_filter($summary['cart'], fn ($line) => !$line['available']);
        return 'Please fix '.count($invalid).' selected item'.(count($invalid) === 1 ? '' : 's').' before checkout: '.implode('; ', array_map(fn ($line) => $line['name'].($line['variant'] ? ' — '.$line['variant'] : '').': '.$line['availability_issue'], $invalid));
    }
}
