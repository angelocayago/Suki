<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CartController extends Controller
{
    public function increase(Request $request, int $cartItem, CheckoutService $checkout): RedirectResponse
    {
        return $this->changeQuantity($request, $cartItem, $checkout, 1);
    }

    public function decrease(Request $request, int $cartItem, CheckoutService $checkout): RedirectResponse
    {
        return $this->changeQuantity($request, $cartItem, $checkout, -1);
    }

    public function update(Request $request, int $cartItem, CheckoutService $checkout): RedirectResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        return $this->changeQuantity($request, $cartItem, $checkout);
    }

    private function changeQuantity(Request $request, int $cartItem, CheckoutService $checkout, ?int $delta = null): RedirectResponse
    {
        DB::transaction(function () use ($request, $cartItem, $checkout, $delta) {
            $owned = CartItem::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($cartItem);
            if ($delta === -1 && $owned->quantity === 1) {
                $owned->delete();
                return;
            }
            $quantity = $delta === null ? (int) $request->input('quantity') : $owned->quantity + $delta;
            $checkout->updateCartQuantity($owned, $quantity);
        }, 3);
        return back()->with('success', 'Cart updated successfully.');
    }

    public function remove(Request $request, int $cartItem): RedirectResponse
    {
        CartItem::where('user_id', $request->user()->id)->findOrFail($cartItem)->delete();
        return back()->with('success', 'Item removed from cart.');
    }

    public function index(Request $request, CheckoutService $checkout): View
    {
        return view('buyer.cart', $checkout->cartSummary($request->user(), $request->session()->get('checkout_voucher_codes', [])));
    }

    public function applyVoucher(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->validate(['voucher_code'=>['required','string','max:64']])['voucher_code']));
        $codes = $request->session()->get('checkout_voucher_codes', []);
        $codes = array_values(array_unique([...$codes, $code]));
        $summary = $checkout->cartSummary($request->user(), $codes);
        if ($summary['voucher_error']) {
            return back()->withErrors(['voucher_code' => $summary['voucher_error']])->withInput();
        }
        $request->session()->put('checkout_voucher_codes', $codes);
        $addsDiscount = collect($summary['applied_vouchers'])->contains('code', $code);
        return back()->with('success', $addsDiscount
            ? 'Voucher applied. It will be revalidated when you place the order.'
            : 'Voucher saved, but other vouchers already cover its eligible merchandise. No additional discount or usage will be applied.');
    }

    public function removeVoucher(Request $request, string $code): RedirectResponse
    {
        $request->session()->put('checkout_voucher_codes', array_values(array_filter($request->session()->get('checkout_voucher_codes', []), fn($item) => $item !== strtoupper($code))));
        return back()->with('success', 'Voucher removed.');
    }
}
