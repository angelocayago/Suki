<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Services\OrderWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FulfillmentController extends Controller
{
    public function dashboard(Request $request): View
    {
        $provider = $this->providerFor($request);
        $orders = $this->databaseOrders($provider);

        return view('logistics.dashboard', [
            'application' => ['partner' => $provider->name, 'status' => $provider->status],
            'partner' => $provider->name,
            'orders' => $orders->isNotEmpty() ? $orders->all() : $request->session()->get('orders', []),
        ]);
    }

    public function parcels(Request $request): View
    {
        $orders = $this->databaseOrders($this->providerFor($request));

        return view('logistics.parcels', [
            'orders' => $orders->isNotEmpty() ? $orders->all() : $request->session()->get('orders', []),
        ]);
    }

    public function sorting(Request $request): View
    {
        $provider = $this->providerFor($request);
        $orders = $this->databaseOrders($provider)
            ->filter(fn (array $order) => in_array($order['status'], [
                'at_sorting_center', 'sorted', 'assigned_to_rider',
            ], true));

        return view('logistics.sorting', [
            'orders' => $orders->isNotEmpty() ? $orders->all() : $request->session()->get('orders', []),
            'areas' => ['Area A', 'Area B', 'Area C'],
            'riders' => $this->activeRiders($provider),
        ]);
    }

    public function assignments(Request $request): View
    {
        $provider = $this->providerFor($request);

        return view('logistics.assignments', [
            'orders' => $this->databaseOrders($provider)
                ->filter(fn (array $order) => in_array($order['status'], ['sorted', 'assigned_to_rider'], true)),
            'riders' => $this->activeRiders($provider),
        ]);
    }

    public function receive(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $workflow->receiveAtSortingCenter($sellerOrder, $this->providerFor($request), $request->user());

        return back()->with('success', 'Parcel arrival verified and logistics ownership assigned.');
    }

    public function update(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $provider = $this->providerFor($request);
        abort_unless((int) $sellerOrder->shipment?->logistics_provider_id === (int) $provider->id, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['SORTED', 'ASSIGNED_TO_RIDER', 'sorted', 'assigned_to_rider'])],
            'sorting_area' => ['nullable', 'required_if:status,SORTED,sorted', 'string', 'max:100'],
            'rider_id' => ['nullable', 'required_if:status,ASSIGNED_TO_RIDER,assigned_to_rider', 'integer'],
        ]);

        $status = OrderStatus::from(strtoupper($validated['status']));
        $context = [];
        if ($status === OrderStatus::Sorted) {
            $context['sorting_area'] = $validated['sorting_area'];
        }
        if ($status === OrderStatus::AssignedToRider) {
            $context['rider'] = Rider::query()->findOrFail($validated['rider_id']);
        }

        $workflow->transition($sellerOrder, $request->user(), $status, $context);

        return back()->with('success', 'Logistics status updated successfully.');
    }

    private function providerFor(Request $request): LogisticsProvider
    {
        $provider = $request->user()->logisticsProvider()->where('status', 'approved')->first();
        abort_unless($provider, 403, 'No approved logistics provider is available.');

        return $provider;
    }

    private function databaseOrders(LogisticsProvider $provider): Collection
    {
        return SellerOrder::query()
            ->whereHas('shipment', function ($query) use ($provider) {
                $query->where(function ($query) {
                    $query->where('workflow_status', OrderStatus::ReadyForPickup->value)
                        ->whereNull('logistics_provider_id')
                        ->whereNull('pickup_rider_id');
                })->orWhere('logistics_provider_id', $provider->id)
                    ->orWhere(function ($query) use ($provider) {
                        $query->whereNull('logistics_provider_id')
                            ->whereHas('pickupRider', fn ($query) => $query
                                ->where('logistics_provider_id', $provider->id));
                    });
            })
            ->whereHas('order', fn ($query) => $query->where('status', '!=', 'CANCELLED'))
            ->with(['order.buyer', 'items', 'shipment.pickupRider.user', 'shipment.rider.user', 'shipment.events'])
            ->latest()
            ->get()
            ->map(fn (SellerOrder $order) => $this->presentOrder($order));
    }

    private function presentOrder(SellerOrder $sellerOrder): array
    {
        $order = $sellerOrder->order;
        $shipment = $sellerOrder->shipment;

        return [
            'id' => $sellerOrder->id,
            'source' => 'database',
            'status' => strtolower($sellerOrder->workflow_status->value),
            'seller_name' => $sellerOrder->seller?->name,
            'order_number' => $order->order_number,
            'customer_name' => $order->recipient_name,
            'buyer_name' => $order->buyer?->full_name ?? $order->recipient_name,
            'shipping_address' => [
                'name' => $order->recipient_name,
                'phone' => $order->recipient_phone,
                'house_number' => $order->house_number,
                'street' => $order->street_address,
                'barangay' => $order->barangay,
                'municipality' => $order->municipality,
                'province' => $order->province,
                'postal_code' => $order->postal_code,
                'address' => implode(', ', array_filter([
                    $order->house_number, $order->street_address, $order->barangay,
                    $order->municipality, $order->province, $order->postal_code,
                ])),
            ],
            'items' => $sellerOrder->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->all(),
            'total' => ($sellerOrder->subtotal_minor + $sellerOrder->shipping_fee_minor) / 100,
            'tracking_code' => $shipment->tracking_code,
            'logistics_provider_id' => $shipment->logistics_provider_id,
            'pickup_rider_name' => $shipment->pickupRider?->user?->full_name,
            'pickup_rider' => [
                'first_name' => $shipment->pickupRider?->user?->first_name,
                'last_name' => $shipment->pickupRider?->user?->last_name,
            ],
            'rider_index' => $shipment->rider_id,
            'rider_name' => $shipment->rider?->user?->full_name,
            'delivery_area' => $shipment->sorting_area,
            'area' => $shipment->sorting_area,
            'failure_reason' => $shipment->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->last()?->note,
            'updated_at' => $sellerOrder->status_changed_at?->toDateTimeString(),
        ];
    }

    private function activeRiders(LogisticsProvider $provider): array
    {
        return $provider->riders()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query
                ->where('status', 'active')
                ->where('is_suspended', false))
            ->with('user')
            ->get()
            ->map(fn (Rider $rider) => [
                'id' => $rider->id,
                'name' => $rider->user->full_name,
                'area' => 'Provider pool',
            ])
            ->all();
    }
}
