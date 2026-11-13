<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Buyer\ShipmentTrackingController;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Services\OrderCancellationService;
use App\Services\OrderWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuyerOrdersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('buyer_id', $request->user()->id)
            ->with(['items', 'sellerOrders.items', 'sellerOrders.seller', 'sellerOrders.shipment.events'])
            ->latest('created_at')->limit(100)->get();

        return $this->ok(['orders' => $orders->map(fn (Order $order) => $this->present($order))->values()]);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        return $this->ok(['order' => $this->present($this->buyerOrder($request, $order))]);
    }

    public function tracking(Request $request, string $order, string $sellerOrder): JsonResponse
    {
        $buyerOrder = $this->buyerOrder($request, $order);
        $parcel = $buyerOrder->sellerOrders()->with('shipment.events')->findOrFail($sellerOrder);
        $data = app(ShipmentTrackingController::class)->latest($request, $buyerOrder, $parcel)->getData(true);
        return $this->ok($data);
    }

    public function cancel(Request $request, string $order, OrderCancellationService $cancellation): JsonResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', Rule::in(array_keys(OrderCancellationService::REASONS))],
        ]);
        $buyerOrder = $this->buyerOrder($request, $order);
        $cancellation->cancelByBuyer($buyerOrder, $request->user(), $data['cancel_reason']);
        return $this->ok(['message' => 'Your order has been cancelled successfully.']);
    }

    public function confirmReceipt(Request $request, string $order, OrderWorkflowService $workflow): JsonResponse
    {
        $buyerOrder = $this->buyerOrder($request, $order);
        $workflow->confirmReceipt($buyerOrder, $request->user());
        return $this->ok(['message' => 'Receipt confirmed.']);
    }

    private function buyerOrder(Request $request, string $reference): Order
    {
        return Order::query()
            ->where('buyer_id', $request->user()->id)
            ->where(fn ($query) => $query->whereKey($reference)->orWhere('order_number', $reference))
            ->with(['items', 'sellerOrders.items', 'sellerOrders.seller', 'sellerOrders.shipment.events'])
            ->firstOrFail();
    }

    private function present(Order $order): array
    {
        $status = strtoupper((string) $order->getRawOriginal('status'));
        $cancellationReason = app(OrderCancellationService::class)->rejectionReason($order);
        $items = $order->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->product_name,
            'quantity' => $item->quantity,
            'price' => $this->lineTotal($item),
            'line_total' => $this->lineTotal($item),
            'image' => $item->image,
            'variation' => $item->variation,
        ])->values();
        $sellerOrders = $order->sellerOrders->map(function (SellerOrder $sellerOrder) {
            $shipment = $sellerOrder->shipment;
            $workflowStatus = $sellerOrder->workflow_status;
            return [
                'id' => $sellerOrder->id,
                'seller_name' => $sellerOrder->seller?->name,
                'status' => strtolower($workflowStatus?->value ?? (string) $workflowStatus),
                'shipment_id' => $shipment?->id,
                'tracking_code' => $shipment?->tracking_code,
                'has_delivery_proof' => (bool) $shipment?->proof_path,
                'items' => $sellerOrder->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'line_total' => $this->lineTotal($item),
                ])->values(),
            ];
        })->values();

        $subtotal = $order->subtotal !== null
            ? (float) $order->subtotal
            : (int) $order->sellerOrders->sum('subtotal_minor') / 100;
        $shipping = $order->shipping_fee !== null
            ? (float) $order->shipping_fee
            : (int) $order->sellerOrders->sum('shipping_fee_minor') / 100;
        $discount = (float) ($order->discount_amount ?? 0);
        $total = $order->total_amount !== null
            ? (float) $order->total_amount
            : ($order->total_minor !== null ? (int) $order->total_minor / 100 : $subtotal + $shipping - $discount);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => strtolower($status),
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
            'item_count' => (int) $items->sum('quantity'),
            'items' => $items,
            'seller_orders' => $sellerOrders,
            'can_cancel' => $cancellationReason === null,
            'cancellation_unavailable_reason' => $cancellationReason,
            'can_buy_again' => in_array(strtolower($status), ['cancelled', 'returned', 'completed'], true),
            'can_confirm_receipt' => $status === 'DELIVERED',
        ];
    }

    private function lineTotal(OrderItem $item): float
    {
        if ($item->line_total !== null) {
            return (float) $item->line_total;
        }
        if ($item->unit_price !== null) {
            return (float) $item->unit_price * (int) $item->quantity;
        }

        return (int) $item->unit_price_minor * (int) $item->quantity / 100;
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
