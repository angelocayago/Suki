<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Buyer\CartController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Services\CheckoutService;
use App\Services\OrderCancellationService;
use App\Services\OrderWorkflowService;
use App\Services\ProductCatalogService;
use App\Support\BuyerOrderTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Versioned Buyer order adapter. All mutations delegate to the established
 * marketplace workflows; this class deliberately contains no status or stock
 * mutation logic of its own.
 */
class BuyerOrdersController
{
    private const DEFAULT_PER_PAGE = 24;
    private const MAXIMUM_PER_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAXIMUM_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in($this->statuses())],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
        ]);

        $query = Order::query()
            ->where('buyer_id', $request->user()->id)
            ->with($this->orderRelations());

        if ($status = $data['status'] ?? null) {
            $query->whereRaw('UPPER(status) = ?', [strtoupper($status)]);
        }

        $orders = ($data['sort'] ?? 'newest') === 'oldest'
            ? $query->oldest('placed_at')->oldest('id')
            : $query->latest('placed_at')->latest('id');

        $orders = $orders->paginate((int) ($data['per_page'] ?? self::DEFAULT_PER_PAGE))
            ->through(fn (Order $order) => $this->summary($order));

        return $this->ok(['orders' => $orders]);
    }

    public function show(Request $request, string $order): JsonResponse
    {
        return $this->ok(['order' => $this->detail($this->buyerOrder($request, $order))]);
    }

    public function tracking(Request $request, string $order): JsonResponse
    {
        $buyerOrder = $this->buyerOrder($request, $order);

        return $this->ok(['tracking' => app(BuyerOrderTracking::class)->present($buyerOrder)]);
    }

    /** Compatibility alias for the existing Flutter per-seller tracking call. */
    public function sellerOrderTracking(Request $request, string $order, string $sellerOrder): JsonResponse
    {
        $buyerOrder = $this->buyerOrder($request, $order);
        $parcel = $buyerOrder->sellerOrders->firstWhere('id', (int) $sellerOrder);
        abort_unless($parcel, 404);

        $tracking = app(BuyerOrderTracking::class)->present($buyerOrder);
        $package = collect($tracking['packages'])->first(function (array $package) use ($parcel): bool {
            return $package['tracking_code'] === $parcel->shipment?->tracking_code;
        });

        return $this->ok([
            'tracking_code' => $parcel->shipment?->tracking_code,
            'status' => BuyerOrderTracking::status($parcel->shipment ?: $parcel),
            'tracking' => $package,
        ]);
    }

    public function cancel(Request $request, string $order, OrderCancellationService $cancellation): JsonResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', Rule::in(array_keys(OrderCancellationService::REASONS))],
        ]);

        $cancelled = $cancellation->cancelByBuyer(
            $this->buyerOrder($request, $order), $request->user(), $data['cancel_reason'],
        );

        return $this->ok([
            'message' => 'Your order has been cancelled successfully.',
            'order' => $this->detail($cancelled->load($this->orderRelations())),
        ]);
    }

    public function received(Request $request, string $order, OrderWorkflowService $workflow): JsonResponse
    {
        $buyerOrder = $this->buyerOrder($request, $order);
        $workflow->confirmReceipt($buyerOrder, $request->user());

        return $this->ok([
            'message' => 'Receipt confirmed.',
            'order' => $this->detail($buyerOrder->fresh($this->orderRelations())),
        ]);
    }

    /** Compatibility alias for the Flutter client's established action name. */
    public function confirmReceipt(Request $request, string $order, OrderWorkflowService $workflow): JsonResponse
    {
        return $this->received($request, $order, $workflow);
    }

    public function buyAgain(Request $request, string $order, CartController $cart, CheckoutService $checkout, ProductCatalogService $catalog): JsonResponse
    {
        $buyerOrder = $this->buyerOrder($request, $order);

        return $cart->buyAgain($request, $buyerOrder->order_number, $checkout, $catalog, true);
    }

    private function buyerOrder(Request $request, string $reference): Order
    {
        return Order::query()
            ->where('buyer_id', $request->user()->id)
            ->where(function ($query) use ($reference): void {
                $query->where('order_number', $reference);
                if (ctype_digit($reference)) $query->orWhere('id', (int) $reference);
            })
            ->with($this->orderRelations())
            ->firstOrFail();
    }

    private function orderRelations(): array
    {
        return [
            'items',
            'sellerOrders.items',
            'sellerOrders.seller',
            'sellerOrders.shipment.events' => fn ($query) => $query->orderBy('id'),
        ];
    }

    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'placed_at' => ($order->placed_at ?? $order->created_at)?->toIso8601String(),
            'status' => strtolower((string) $order->getRawOriginal('status')),
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'total_amount' => $this->total($order),
            'item_count' => (int) $order->items->sum('quantity'),
            'seller_order_count' => $order->sellerOrders->count(),
        ];
    }

    private function detail(Order $order): array
    {
        $tracking = app(BuyerOrderTracking::class)->present($order);
        $subtotal = $order->subtotal !== null ? (float) $order->subtotal : $order->sellerOrders->sum('subtotal_minor') / 100;
        $shipping = $order->shipping_fee !== null ? (float) $order->shipping_fee : $order->sellerOrders->sum('shipping_fee_minor') / 100;

        return $this->summary($order) + [
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping,
            'discount_amount' => (float) ($order->discount_amount ?? 0),
            'delivery_address' => [
                'recipient_name' => $order->recipient_name,
                'recipient_phone' => $order->recipient_phone,
                'street_address' => $order->street_address,
                'barangay' => $order->barangay,
                'municipality' => $order->municipality,
                'province' => $order->province,
                'postal_code' => $order->postal_code,
                'address_label' => $order->address_label,
            ],
            'cod' => $order->payment_method === 'cod' ? [
                'amount' => $this->total($order),
                'payment_status' => $order->payment_status,
            ] : null,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_slug' => $item->product_slug,
                'product_name' => $item->product_name,
                'product_variant_id' => $item->product_variant_id,
                'variant_name' => $item->variant_name,
                'options' => $item->variation,
                'quantity' => $item->quantity,
                'unit_price' => $this->unitPrice($item),
                'line_total' => $this->lineTotal($item),
            ])->values(),
            'seller_orders' => $order->sellerOrders->map(fn (SellerOrder $sellerOrder) => [
                'id' => $sellerOrder->id,
                'store_name' => $sellerOrder->seller?->name,
                'status' => strtolower((string) ($sellerOrder->workflow_status?->value ?? $sellerOrder->getRawOriginal('workflow_status'))),
                'shipment' => $sellerOrder->shipment ? [
                    'tracking_code' => $sellerOrder->shipment->tracking_code,
                    'status' => strtolower((string) ($sellerOrder->shipment->workflow_status?->value ?? $sellerOrder->shipment->getRawOriginal('workflow_status'))),
                ] : null,
                'item_count' => (int) $sellerOrder->items->sum('quantity'),
            ])->values(),
            'tracking' => $tracking,
            'can_cancel' => app(OrderCancellationService::class)->rejectionReason($order) === null,
            'can_buy_again' => in_array(strtoupper((string) $order->getRawOriginal('status')), ['CANCELLED', 'RETURNED', 'COMPLETED'], true),
            'can_confirm_receipt' => strtoupper((string) $order->getRawOriginal('status')) === 'DELIVERED',
        ];
    }

    private function unitPrice(OrderItem $item): float
    {
        return $item->unit_price !== null ? (float) $item->unit_price : (int) $item->unit_price_minor / 100;
    }

    private function lineTotal(OrderItem $item): float
    {
        return $item->line_total !== null ? (float) $item->line_total : $this->unitPrice($item) * (int) $item->quantity;
    }

    private function total(Order $order): float
    {
        return $order->total_amount !== null
            ? (float) $order->total_amount
            : ($order->total_minor !== null ? (int) $order->total_minor / 100 : (float) ($order->sellerOrders->sum('subtotal_minor') + $order->sellerOrders->sum('shipping_fee_minor')) / 100);
    }

    private function statuses(): array
    {
        return ['cancelled', 'returned', ...array_map(fn ($status) => strtolower($status->value), \App\Enums\OrderStatus::cases())];
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
