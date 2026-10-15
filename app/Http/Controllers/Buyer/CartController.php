<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CartController extends Controller
{
    public function index(Request $request, CheckoutService $checkout): View
    {
        return view('buyer.cart', $checkout->cartSummary($request->user()));
    }

    public function applyVoucher(Request $request): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->validate(['voucher_code'=>['required','string','max:64']])['voucher_code']));
        $codes = $request->session()->get('checkout_voucher_codes', []);
        $request->session()->put('checkout_voucher_codes', array_values(array_unique([...$codes, $code])));
        return back()->with('success', 'Voucher will be validated when you place the order.');
    }

    public function removeVoucher(Request $request, string $code): RedirectResponse
    {
        $request->session()->put('checkout_voucher_codes', array_values(array_filter($request->session()->get('checkout_voucher_codes', []), fn($item) => $item !== strtoupper($code))));
        return back()->with('success', 'Voucher removed.');
    }
}
