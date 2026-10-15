<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function index(Request $request, CheckoutService $checkout): View|RedirectResponse
    {
        $summary = $checkout->cartSummary($request->user());
        $summary['voucher_codes'] = $request->session()->get('checkout_voucher_codes', []);

        if ($summary['cart'] === []) {
            return redirect()
                ->route('buyer.cart')
                ->with('error', 'Your cart is empty.');
        }

        $databaseAddresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (Address $address) => [
                'id' => $address->id,
                'name' => $address->recipient,
                'phone' => $address->phone,
                'province' => $address->province,
                'municipality' => $address->city,
                'barangay' => $address->barangay,
                'street' => $address->line1,
                'house_number' => null,
                'postal_code' => $address->postal_code,
                'label' => $address->label,
                'is_default' => $address->is_default,
            ]);

        $addresses = $databaseAddresses->isNotEmpty()
            ? $databaseAddresses->all()
            : $request->session()->get('buyer_addresses', []);
        $defaultAddress = collect($addresses)->firstWhere('is_default', true)
            ?? ($addresses[0] ?? null);

        return view('buyer.checkout', array_merge($summary, [
            'addresses' => $addresses,
            'defaultAddress' => $defaultAddress,
        ]));
    }

    public function store(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $validated = $request->validate([
            'address_id' => ['required'],
            'payment_method' => ['required', 'in:cod'],
        ]);

        $buyer = $request->user();
        $address = $this->resolveAddress($request, $validated['address_id']);

        try {
            $order = $checkout->checkout($buyer, $address, $validated['payment_method'], $request->session()->get('checkout_voucher_codes', []));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('buyer.checkout')
                ->with('error', 'Unable to place your order. Please try again.');
        }

        $request->session()->forget('checkout_voucher_codes');
        return redirect()
            ->route('buyer.order-success', ['order' => $order->order_number])
            ->with('success', 'Your order has been placed successfully!');
    }

    private function resolveAddress(Request $request, mixed $addressId): array
    {
        $databaseAddress = Address::query()
            ->where('user_id', $request->user()->id)
            ->find($addressId);

        if ($databaseAddress) {
            return [
                'recipient_name' => $databaseAddress->recipient,
                'recipient_phone' => $databaseAddress->phone,
                'province' => $databaseAddress->province,
                'municipality' => $databaseAddress->city,
                'barangay' => $databaseAddress->barangay,
                'street_address' => $databaseAddress->line1,
                'house_number' => null,
                'postal_code' => $databaseAddress->postal_code,
                'address_label' => $databaseAddress->label,
                'delivery_notes' => null,
                'source_address_id' => $databaseAddress->id,
            ];
        }

        $sessionAddress = collect($request->session()->get('buyer_addresses', []))
            ->first(fn (array $address) => (string) ($address['id'] ?? '') === (string) $addressId);

        if (! $sessionAddress) {
            throw ValidationException::withMessages([
                'address_id' => 'Please select a valid delivery address.',
            ]);
        }

        return [
            'recipient_name' => $sessionAddress['name'] ?? '',
            'recipient_phone' => $sessionAddress['phone'] ?? '',
            'province' => $sessionAddress['province'] ?? '',
            'municipality' => $sessionAddress['municipality'] ?? '',
            'barangay' => $sessionAddress['barangay'] ?? '',
            'street_address' => $sessionAddress['street'] ?? '',
            'house_number' => $sessionAddress['house_number'] ?? null,
            'postal_code' => $sessionAddress['postal_code'] ?? null,
            'address_label' => $sessionAddress['label'] ?? null,
            'delivery_notes' => null,
            'source_address_id' => null,
        ];
    }
}
