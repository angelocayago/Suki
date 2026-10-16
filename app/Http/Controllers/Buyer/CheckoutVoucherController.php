<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CheckoutController;
use App\Services\VoucherCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CheckoutVoucherController extends Controller
{
    public function apply(
        Request $request,
        VoucherCalculationService $voucherService,
        CheckoutController $checkoutController
    ) {
        $validated = $request->validate([
            'voucher_code' => ['required', 'string', 'max:50'],
        ]);

        $code = strtoupper(trim($validated['voucher_code']));

        $cartItems = $checkoutController->checkoutCartItems($request);

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Your checkout cart is empty.',
            ]);
        }

        // Calculate preview only; no redemption or order changes.
        $result = $voucherService->calculate(
            $code,
            $cartItems,
            (int) Auth::id()
        );

        if ((int) ($result['discount_minor'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'voucher_code' => 'This voucher does not apply to your checkout.',
            ]);
        }

        $request->session()->put('checkout.voucher_code', $code);

        return redirect()
            ->route('checkout.index')
            ->with('success', 'Voucher applied for preview.');
    }

    public function remove(Request $request)
    {
        $request->session()->forget('checkout.voucher_code');

        return redirect()
            ->route('checkout.index')
            ->with('success', 'Voucher removed.');
    }
}
