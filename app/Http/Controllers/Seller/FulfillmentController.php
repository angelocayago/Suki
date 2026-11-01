<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\OrderWorkflowService;
use App\Services\SellerSalesService;
use App\Services\SellerWalletService;
use App\Support\SellerDashboard;
use App\Support\SellerOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FulfillmentController extends Controller
{
    public function dashboard(Request $request, SellerSalesService $sales, SellerWalletService $wallet, SellerDashboard $dashboard): View
    {
        $seller = $this->sellerFor($request->user());
        return view('seller.dashboard', $dashboard->data($seller) + [
            'wallet' => $wallet->summary($seller),
        ]);
    }

    public function orders(Request $request, SellerOrders $presentation): View
    {
        return view('seller.orders', $presentation->listing($this->sellerFor($request->user()), $request));
    }

    public function show(Request $request, SellerOrder $sellerOrder, SellerOrders $presentation): View
    {
        $seller = $this->sellerFor($request->user());
        abort_unless((int) $sellerOrder->seller_id === (int) $seller->id, 403);
        $sellerOrder->load(['order', 'items', 'shipment.events', 'voucherRedemptions']);

        return view('seller.order-details', [
            'order' => $presentation->present($sellerOrder, true),
            'backUrl' => $presentation->backUrl($request),
        ]);
    }

    public function inventory(Request $request): View
    {
        $seller = $this->sellerFor($request->user());
        $criteria = array_replace(['search'=>'', 'category'=>'', 'stock'=>'all'], array_filter($request->validate([
            'search'=>['nullable', 'string', 'max:100'],
            'category'=>['nullable', 'integer', 'min:1'],
            'stock'=>['nullable', Rule::in(['all','in-stock','low-stock','out-of-stock'])],
        ]), fn ($value) => $value !== null));
        $totals = $seller->products()->withSum(['variants'=>fn ($query) => $query->where('is_active', true)], 'stock')->get();
        $inventorySummary = [
            'total'=>$totals->count(), 'units'=>(int)$totals->sum('variants_sum_stock'),
            'in_stock'=>$totals->filter(fn ($product) => $product->variants_sum_stock > 10)->count(),
            'low_stock'=>$totals->filter(fn ($product) => $product->variants_sum_stock >= 1 && $product->variants_sum_stock <= 10)->count(),
            'out_of_stock'=>$totals->filter(fn ($product) => ($product->variants_sum_stock ?? 0) <= 0)->count(),
        ];
        $inventoryCategories = \App\Models\Category::query()->whereIn('id', $seller->products()->select('category_id'))->orderBy('name')->get(['id','name']);
        $databaseProducts = $this->databaseInventory($seller, $criteria);
        $productUnits = $totals->pluck('variants_sum_stock', 'id');
        $databaseProducts = $databaseProducts->map(function ($row) use ($productUnits) {
            $row['product_stock'] = (int) ($productUnits[$row['product_id']] ?? 0);
            return $row;
        });

        return view('seller.inventory', [
            'products' => $databaseProducts->all(),
            'databaseInventory' => true,
            'inventorySummary'=>$inventorySummary, 'inventoryCategories'=>$inventoryCategories, 'criteria'=>$criteria,
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

    private function databaseInventory(Seller $seller, array $criteria): Collection
    {
        $term = mb_strtolower(trim($criteria['search']));
        $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $variants = function ($query) use ($criteria, $term) {
            if (trim($criteria['search']) !== '') {
                $query->where(fn ($query) => $query->whereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereHas('product', fn ($product) => $product->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$term])));
            }
            match ($criteria['stock']) {
                'in-stock'=>$query->where('stock','>',10),
                'low-stock'=>$query->whereBetween('stock',[1,10]),
                'out-of-stock'=>$query->where('stock','<=',0),
                default=>null,
            };
        };
        return $seller->products()
            ->when($criteria['category'] !== '', fn ($query) => $query->where('category_id', $criteria['category']))
            ->whereHas('variants', $variants)
            ->with(['category', 'images', 'variants'=>$variants])
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
                    'image' => ($path = $product->images->first()?->path)
                        ? (\Illuminate\Support\Str::startsWith($path, ['https://','http://']) ? $path : \Illuminate\Support\Facades\Storage::disk('public')->url($path)) : null,
                    // This is a general variant update, not a stock-only audit event.
                    'updated_at' => $variant->updated_at?->copy()->setTimezone('Asia/Manila'),
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
