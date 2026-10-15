<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\OrderWorkflowService;
use App\Services\SellerSalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FulfillmentController extends Controller
{
    public function dashboard(Request $request, SellerSalesService $sales): View
    {
        $seller = $this->sellerFor($request->user());
        $databaseOrders = $this->databaseOrders($seller);
        $databaseProducts = $this->databaseInventory($seller);

        return view('seller.dashboard', [
            'orders' => $databaseOrders->isNotEmpty()
                ? $databaseOrders->all()
                : $request->session()->get('orders', []),
            'products' => $databaseProducts->isNotEmpty()
                ? $databaseProducts->all()
                : $request->session()->get('seller_products', []),
            'completedSales' => $sales->completedSummary($seller),
        ]);
    }

    public function orders(Request $request): View
    {
        $databaseOrders = $this->databaseOrders($this->sellerFor($request->user()));

        $orders = $databaseOrders->isNotEmpty()
            ? $databaseOrders->all()
            : array_reverse($request->session()->get('orders', []), true);

        return view('seller.orders', compact('orders'));
    }

    public function inventory(Request $request): View
    {
        $databaseProducts = $this->databaseInventory($this->sellerFor($request->user()));

        return view('seller.inventory', [
            'products' => $databaseProducts->isNotEmpty()
                ? $databaseProducts->all()
                : $request->session()->get('seller_products', []),
            'databaseInventory' => $databaseProducts->isNotEmpty(),
        ]);
    }

    public function update(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $seller = $this->sellerFor($request->user());

        abort_unless((int) $sellerOrder->seller_id === (int) $seller->id, 403);

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    OrderStatus::Confirmed->value,
                    OrderStatus::Preparing->value,
                    OrderStatus::ReadyForPickup->value,
                    strtolower(OrderStatus::Confirmed->value),
                    strtolower(OrderStatus::Preparing->value),
                    strtolower(OrderStatus::ReadyForPickup->value),
                ]),
            ],
        ]);

        $status = OrderStatus::from(strtoupper($validated['status']));
        $workflow->transition($sellerOrder, $request->user(), $status);

        return back()->with('success', 'Order status updated successfully.');
    }

    private function sellerFor(User $user): Seller
    {
        $seller = $user->sellers()
            ->where('status', 'approved')
            ->first();

        abort_unless($seller, 403, 'No approved seller profile is available.');

        return $seller;
    }

    private function databaseOrders(Seller $seller): Collection
    {
        return $seller->orders()
            ->with([
                'order.buyer',
                'items.product',
                'items.productVariant',
                'shipment',
            ])
            ->latest()
            ->get()
            ->map(fn (SellerOrder $sellerOrder) => $this->presentOrder($sellerOrder));
    }

    private function presentOrder(SellerOrder $sellerOrder): array
    {
        $order = $sellerOrder->order;
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];

        return [
            'id' => $sellerOrder->id,
            'seller_order_id' => $sellerOrder->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'source' => 'database',
            'status' => $sellerOrder->status === 'cancelled'
                ? 'cancelled'
                : strtolower($sellerOrder->workflow_status->value),
            'payment_method' => strtolower((string) $order->payment_method),
            'payment_status' => strtolower((string) $order->payment_status),
            'buyer_name' => $order->buyer?->full_name ?? $order->recipient_name,
            'customer_name' => $order->recipient_name,
            'shipping_address' => [
                'name' => $order->recipient_name,
                'phone' => $order->recipient_phone,
                'house_number' => $order->house_number ?? ($address['house_number'] ?? null),
                'street' => $order->street_address,
                'barangay' => $order->barangay,
                'municipality' => $order->municipality,
                'province' => $order->province,
                'postal_code' => $order->postal_code,
            ],
            'items' => $sellerOrder->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->product_name,
                'image' => $item->image,
                'variant' => $item->variant_name,
                'variation' => $item->variation,
                'quantity' => $item->quantity,
                'price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'sku' => $item->productVariant?->sku,
            ])->all(),
            'subtotal' => $sellerOrder->subtotal_minor / 100,
            'shipping_fee' => $sellerOrder->shipping_fee_minor / 100,
            'total' => ($sellerOrder->subtotal_minor + $sellerOrder->shipping_fee_minor) / 100,
            'tracking_code' => $sellerOrder->shipment?->tracking_code,
            'shipment' => [
                'tracking_code' => $sellerOrder->shipment?->tracking_code,
                'status' => strtolower($sellerOrder->shipment?->workflow_status?->value ?? 'placed'),
                'pickup_rider_id' => $sellerOrder->shipment?->pickup_rider_id,
                'delivery_rider_id' => $sellerOrder->shipment?->rider_id,
                'logistics_provider_id' => $sellerOrder->shipment?->logistics_provider_id,
            ],
            'pickup_address' => $this->pickupAddress($sellerOrder->seller),
            'created_at' => $sellerOrder->created_at?->toDateTimeString(),
            'confirmed_at' => $sellerOrder->confirmed_at?->toDateTimeString(),
            'preparing_at' => $sellerOrder->preparing_at?->toDateTimeString(),
            'ready_for_pickup_at' => $sellerOrder->ready_for_pickup_at?->toDateTimeString(),
        ];
    }

    private function databaseInventory(Seller $seller): Collection
    {
        return $seller->products()
            ->with(['category', 'images', 'variants'])
            ->orderBy('name')
            ->get()
            ->flatMap(function ($product) use ($seller) {
                return $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'variant_id' => $variant->id,
                    'source' => 'database',
                    'product_id' => $product->id,
                    'name' => $variant->name === 'Default'
                        ? $product->name
                        : $product->name.' - '.$variant->name,
                    'brand' => $seller->name,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'sku' => $variant->sku,
                    'price' => $variant->price_minor / 100,
                    'stock' => $variant->stock,
                    'status' => $product->is_active && $variant->is_active ? 'active' : 'inactive',
                    'image' => $product->images->first()?->path,
                    'updated_at' => $variant->updated_at?->toDateTimeString(),
                ]);
            })
            ->values();
    }

    private function pickupAddress(Seller $seller): ?array
    {
        $address = $seller->pickupAddress;

        if (! $address) {
            return null;
        }

        return [
            'recipient' => $address->recipient,
            'phone' => $address->phone,
            'line1' => $address->line1,
            'barangay' => $address->barangay,
            'municipality' => $address->city,
            'province' => $address->province,
            'postal_code' => $address->postal_code,
        ];
    }
}
