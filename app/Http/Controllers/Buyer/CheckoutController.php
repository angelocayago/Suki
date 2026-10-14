<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function store(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $validated = $request->validate([
            'address_id' => ['required'],
            'payment_method' => ['required', 'in:cod,gcash'],
        ]);

        $buyer = $request->user();
        $address = $this->resolveAddress($request, $validated['address_id']);

        try {
            $order = $checkout->checkout($buyer, $address, $validated['payment_method']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('buyer.checkout')
                ->with('error', 'Unable to place your order. Please try again.');
        }

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
