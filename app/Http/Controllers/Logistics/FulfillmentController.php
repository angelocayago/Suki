<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\RiderServiceArea;
use App\Models\SellerOrder;
use App\Models\Shipment;
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
                ->filter(fn (array $order) => in_array($order['status'], ['ready_for_pickup', 'sorted', 'assigned_to_rider', 'delivery_failed', 'rts_initiated', 'rts_returning', 'rts_completed'], true)),
            'riders' => $this->activeRiders($provider),
        ]);
    }

    public function lookup(Request $request): View
    {
        $provider = $this->providerFor($request);
        $data = $request->validate(['tracking_code' => ['nullable', 'string', 'max:100']]);
        $shipment = null;
        if (filled($data['tracking_code'] ?? null)) {
            $shipment = Shipment::query()
                ->with(['sellerOrder.order', 'sellerOrder.seller'])
                ->whereRaw('UPPER(tracking_code) = ?', [strtoupper(trim($data['tracking_code']))])
                ->where('logistics_provider_id', $provider->id)
                ->first();
        }
        return view('logistics.parcel-lookup', compact('shipment'));
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

    public function assignPickup(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate(['pickup_rider_id' => ['required', 'integer']]);
        $provider = $this->providerFor($request);
        $rider = $provider->riders()->with(['user', 'logisticsProvider'])->findOrFail($validated['pickup_rider_id']);

        $workflow->assignPickupRider($sellerOrder, $provider, $request->user(), $rider);

        return back()->with('success', 'Pickup rider assignment saved. The rider must still accept the pickup.');
    }

    public function reassignDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['rider_id' => ['required', 'integer']]);
        $provider = $this->providerFor($request);
        $rider = $provider->riders()->with(['user', 'logisticsProvider'])->findOrFail($data['rider_id']);
        $workflow->reassignDeliveryRider($sellerOrder, $provider, $request->user(), $rider);

        return back()->with('success', 'Delivery rider reassigned.');
    }

    public function reschedule(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['rider_id' => ['required', 'integer'], 'scheduled_at' => ['required', 'date', 'after:now']]);
        $provider = $this->providerFor($request);
        $rider = $provider->riders()->with(['user', 'logisticsProvider'])->findOrFail($data['rider_id']);
        $workflow->rescheduleDelivery($sellerOrder, $provider, $request->user(), $rider, $data['scheduled_at']);
        return back()->with('success', 'Delivery rescheduled.');
    }

    public function advanceReturn(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['RTS_RETURNING', 'RTS_COMPLETED'])]]);
        $workflow->advanceReturn($sellerOrder, $this->providerFor($request), $request->user(), OrderStatus::from($data['status']));
        return back()->with('success', 'Return status updated.');
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
            ->with(['order.buyer', 'items', 'seller.pickupAddress', 'shipment.pickupRider.user', 'shipment.rider.user', 'shipment.events.user'])
            ->latest()
            ->get()
            ->map(fn (SellerOrder $order) => $this->presentOrder($order, $provider));
    }

    private function presentOrder(SellerOrder $sellerOrder, LogisticsProvider $provider): array
    {
        $order = $sellerOrder->order;
        $shipment = $sellerOrder->shipment;

        return [
            'id' => $sellerOrder->id,
            'shipment_id' => $shipment->id,
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
            'pickup_rider_id' => $shipment->pickup_rider_id,
            'pickup_assignment_open' => $sellerOrder->workflow_status === OrderStatus::ReadyForPickup && ! $shipment->pickup_accepted_at,
            'assignment_history' => $shipment->events->filter(fn ($event) => in_array($event->status, [
                'PICKUP_RIDER_ASSIGNED', 'PICKUP_RIDER_REASSIGNED', 'PICKUP_ACCEPTED', 'PICKUP_DECLINED',
                'ASSIGNED_TO_RIDER', 'DELIVERY_ACCEPTED', 'DELIVERY_DECLINED', 'DELIVERY_RIDER_REASSIGNED',
            ], true))->values(),
            'pickup_rider' => [
                'first_name' => $shipment->pickupRider?->user?->first_name,
                'last_name' => $shipment->pickupRider?->user?->last_name,
            ],
            'rider_index' => $shipment->rider_id,
            'rider_name' => $shipment->rider?->user?->full_name,
            'delivery_area' => $shipment->sorting_area,
            'area' => $shipment->sorting_area,
            'failure_reason' => $shipment->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->last()?->note,
            'failure_history' => $shipment->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->values(),
            'updated_at' => $sellerOrder->status_changed_at?->toDateTimeString(),
            'pickup_eligible_riders' => $this->eligibleRiders($provider, $sellerOrder->seller?->pickupAddress?->region_code, $sellerOrder->seller?->pickupAddress?->city_municipality_code, $sellerOrder->seller?->pickupAddress?->barangay_code),
            'delivery_eligible_riders' => $this->eligibleRiders($provider, $order->region_code, $order->city_municipality_code, $order->barangay_code),
        ];
    }

    private function eligibleRiders(LogisticsProvider $provider, ?string $region, ?string $city, ?string $barangay): array
    {
        if (! $region || ! $city || ! $barangay) return [];
        return $provider->riders()->where('is_active', true)->where(fn ($q) => $q->where('availability', 'ONLINE')->orWhereNull('availability'))->whereNotNull('approved_at')->whereHas('user', fn ($q) => $q->where('status', 'active')->where('is_suspended', false))->whereHas('serviceAreas', function ($query) use ($provider, $region, $city, $barangay) {
            $query->where('logistics_provider_id', $provider->id)->where('is_active', true)->where('region_code', $region)->where('city_municipality_code', $city)->where(fn ($q) => $q->whereNull('barangay_code')->orWhere('barangay_code', $barangay));
        })->with(['user', 'serviceAreas'])->get()->map(fn (Rider $rider) => ['id' => $rider->id, 'name' => $rider->user->full_name, 'area' => $rider->serviceAreas->where('is_active', true)->pluck('city_municipality_code')->implode(', ')])->all();
    }

    private function activeRiders(LogisticsProvider $provider): array
    {
        return $provider->riders()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('availability', 'ONLINE')->orWhereNull('availability'))
            ->whereNotNull('approved_at')
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
