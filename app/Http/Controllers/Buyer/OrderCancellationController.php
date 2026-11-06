<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderCancellationController extends Controller
{
    public function store(
        Request $request,
        string $order,
        OrderCancellationService $cancellation
    ): RedirectResponse {
        $request->merge(['cancel_order_reference' => $order]);
        $validated = $request->validateWithBag('cancellation', [
            'cancel_reason' => [
                'required',
                Rule::in(array_keys(OrderCancellationService::REASONS)),
            ],
        ]);

        $buyerOrder = Order::query()
            ->where('buyer_id', $request->user()->id)
            ->where('order_number', $order)
            ->firstOrFail();

        try {
            $cancellation->cancelByBuyer($buyerOrder, $request->user(), $validated['cancel_reason']);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('cancellation');
        }

        return redirect()
            ->route('buyer.order-details', $buyerOrder->order_number)
            ->with('success', 'Your order has been cancelled successfully.');
    }
}
