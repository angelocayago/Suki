<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderCancellationController extends Controller
{
    public function store(
        Request $request,
        string $order,
        OrderCancellationService $cancellation
    ): RedirectResponse {
        $validated = $request->validate([
            'cancel_reason' => [
                'required',
                Rule::in([
                    'changed_mind',
                    'ordered_by_mistake',
                    'found_better_price',
                    'wrong_product',
                    'seller_requested',
                    'other',
                ]),
            ],
        ]);

        $buyerOrder = Order::query()
            ->where('buyer_id', $request->user()->id)
            ->where('order_number', $order)
            ->firstOrFail();

        $cancellation->cancelByBuyer(
            $buyerOrder,
            $request->user(),
            $validated['cancel_reason']
        );

        return redirect()
            ->route('buyer.order-details', $buyerOrder->order_number)
            ->with('success', 'Your order has been cancelled successfully.');
    }
}
