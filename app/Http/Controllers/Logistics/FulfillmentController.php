<?php

namespace App\Http\Controllers\Logistics;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ShipmentScannerController;
use App\Models\LogisticsProvider;
use App\Models\LogisticsSortingZone;
use App\Models\Application;
use App\Models\Rider;
use App\Models\RiderServiceArea;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\ShipmentAssignment;
use App\Services\OrderWorkflowService;
use App\Services\LogisticsShipmentCounters;
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
        $applications = $this->providerRiderApplications($provider);
        $approvedRiders = $provider->riders()
            ->whereNotNull('approved_at')
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('status', 'active')->where('is_suspended', false))
            ->count();
        $busyRiderIds = Shipment::query()
            ->where('logistics_provider_id', $provider->id)
            ->whereNotNull('rider_id')
            ->whereIn('workflow_status', [OrderStatus::AssignedToRider->value, OrderStatus::OutForDelivery->value])
            ->distinct()->pluck('rider_id');
        $operationalRiders = $provider->riders()->with(['user', 'logisticsProvider', 'logisticsHub'])
            ->whereNotNull('approved_at')->where('is_active', true)
            ->where(fn ($query) => $query->where('availability', 'ONLINE')->orWhereNull('availability'))
            ->whereHas('user', fn ($query) => $query->where('status', 'active')->where('is_suspended', false))
            ->get()->filter(fn (Rider $rider) => $rider->isOperationallyAvailable()
                && $rider->logisticsHub?->is_active
                && (int) $rider->logisticsHub->logistics_provider_id === (int) $provider->id)->values();
        $busyRiders = $operationalRiders->whereIn('id', $busyRiderIds)->count();

        return view('logistics.dashboard', [
            'application' => ['partner' => $provider->name, 'status' => $provider->status],
            'partner' => $provider->name,
            'orders' => $orders->all(),
            'riders' => $applications->map(fn (Application $application) => array_merge($application->application_data ?? [], [
                'application_id' => $application->id,
                'first_name' => $application->user?->first_name,
                'last_name' => $application->user?->last_name,
                'status' => $application->status === 'rejected' ? 'disapproved' : $application->status,
            ]))->all(),
            'approvedRiders' => $approvedRiders,
            'disapprovedRiders' => $applications->where('status', 'rejected')->count(),
            'busyRiders' => $busyRiders,
            'availableRiders' => max(0, $operationalRiders->count() - $busyRiders),
            'workflowCounters' => app(LogisticsShipmentCounters::class)->forProvider($provider),
        ]);
    }

    public function parcels(Request $request): View
    {
        $orders = $this->databaseOrders($this->providerFor($request));

        return view('logistics.parcels', [
            'orders' => $orders->all(),
        ]);
    }

    public function sorting(Request $request): View
    {
        $provider = $this->providerFor($request);
        $orders = $this->databaseOrders($provider)
            ->filter(fn (array $order) => in_array($order['status'], [
                'at_sorting_center', 'sorted', 'in_transit_to_hub', 'at_destination_hub', 'assigned_to_rider',
            ], true));
        $routingExceptions = SellerOrder::query()->where('workflow_status', OrderStatus::AtSortingCenter->value)
            ->whereHas('shipment', fn ($query) => $query->where('logistics_provider_id', $provider->id))
            ->with(['order', 'shipment'])->get()->map(function (SellerOrder $sellerOrder) use ($provider) {
                $resolution = $sellerOrder->order
                    ? app(\App\Services\SortingZoneResolver::class)->resolve($provider, $sellerOrder->order)
                    : ['zone' => null, 'ambiguous' => false];
                return $resolution['zone'] ? null : [
                    'order_number' => $sellerOrder->order?->order_number,
                    'tracking_code' => $sellerOrder->shipment?->tracking_code,
                    'destination' => collect([$sellerOrder->order?->barangay, $sellerOrder->order?->municipality, $sellerOrder->order?->province])->filter()->implode(', '),
                    'reason' => ($resolution['ambiguous'] ?? false) ? 'Multiple active sorting zones have the same specificity and priority.' : 'No active destination zone linked to an active provider hub covers this address.',
                ];
            })->filter()->values();

        return view('logistics.sorting', [
            'orders' => $orders->all(),
            'riders' => $this->activeRiders($provider),
            'routingExceptions' => $routingExceptions,
        ]);
    }

    public function assignments(Request $request): View
    {
        $provider = $this->providerFor($request);

        return view('logistics.assignments', [
            'orders' => $this->databaseOrders($provider)
                ->filter(fn (array $order) => in_array($order['status'], ['ready_for_pickup', 'at_destination_hub', 'assigned_to_rider', 'delivery_failed', 'rts_initiated', 'rts_returning', 'rts_completed'], true)),
            'riders' => $this->activeRiders($provider),
            'transferRiders' => $this->transferRiders($provider),
            'hubTransfers' => ShipmentAssignment::query()->with(['shipment.sellerOrder.order', 'destinationHub', 'rider.user'])->where('logistics_provider_id', $provider->id)->where('assignment_type', 'HUB_TRANSFER')->whereIn('status', ['ASSIGNED', 'ACCEPTED', 'DECLINED'])->latest()->get()->unique('shipment_id')->values(),
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
        $provider = $this->providerFor($request);
        $shipment = $sellerOrder->shipment;
        abort_unless($shipment && (int) $shipment->logistics_provider_id === (int) $provider->id, 403);
        $verified = $request->session()->get(ShipmentScannerController::verifiedResultKey('logistics', $provider->id));
        if (! $shipment || $shipment->workflow_status !== OrderStatus::PickedUp || ! is_array($verified) || (int) ($verified['shipment_id'] ?? 0) !== (int) $shipment->id || now()->timestamp - (int) ($verified['verified_at'] ?? 0) > 900) {
            throw \Illuminate\Validation\ValidationException::withMessages(['shipment' => 'Only a recently scanned PICKED_UP parcel can be received at the sorting center.']);
        }
        $workflow->transition($sellerOrder, $request->user(), OrderStatus::AtSortingCenter);
        $request->session()->forget(ShipmentScannerController::verifiedResultKey('logistics', $provider->id));

        return back()->with('success', 'Parcel arrival recorded at the main sorting center.');
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
            'rider_id' => ['nullable', 'required_if:status,ASSIGNED_TO_RIDER,assigned_to_rider', 'integer'],
        ]);

        $status = OrderStatus::from(strtoupper($validated['status']));
        if ($status === OrderStatus::Sorted) {
            if ((int) $request->session()->get(ShipmentScannerController::sortingVerificationKey($provider->id)) !== (int) $sellerOrder->shipment->id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'Scan and verify this exact parcel in Shipment Scanner before confirming sorting.',
                ]);
            }
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Confirm sorting from the verified Shipment Scanner so an active provider zone can be selected.',
            ]);
        }
        $context = [];
        if ($status === OrderStatus::AssignedToRider) {
            $context['rider'] = Rider::query()->findOrFail($validated['rider_id']);
        }

        $workflow->transition($sellerOrder, $request->user(), $status, $context);

        if ($status === OrderStatus::Sorted) {
            $request->session()->forget(ShipmentScannerController::sortingVerificationKey($provider->id));
        }

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

    public function assignHubTransfer(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'rider_id' => ['nullable', 'integer', 'required_without_all:vehicle_reference,batch_reference'],
            'vehicle_reference' => ['nullable', 'string', 'max:100', 'required_without_all:rider_id,batch_reference'],
            'batch_reference' => ['nullable', 'string', 'max:100', 'required_without_all:rider_id,vehicle_reference'],
        ]);
        $provider = $this->providerFor($request);
        $rider = filled($data['rider_id'] ?? null) ? $provider->riders()->with(['user', 'logisticsProvider', 'logisticsHub'])->findOrFail($data['rider_id']) : null;
        $workflow->assignHubTransferRider($sellerOrder, $provider, $request->user(), $rider, $data['vehicle_reference'] ?? null, $data['batch_reference'] ?? null);
        return back()->with('success', $rider ? 'Hub-transfer rider assigned. The rider must accept and scan before dispatch.' : 'Hub-transfer vehicle or batch assigned. Scan the shipment to dispatch.');
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
            ->with(['order.buyer', 'items', 'seller.pickupAddress', 'shipment.pickupRider.user', 'shipment.rider.user', 'shipment.events.user', 'shipment.sortingZone.hub'])
            ->latest()
            ->get()
            ->map(fn (SellerOrder $order) => $this->presentOrder($order, $provider));
    }

    private function presentOrder(SellerOrder $sellerOrder, LogisticsProvider $provider): array
    {
        $order = $sellerOrder->order;
        $shipment = $sellerOrder->shipment;
        $sortingZone = $shipment->sortingZone;
        $zoneIsUsable = ! $shipment->sorting_zone_id || ($sortingZone
            && $sortingZone->is_active
            && (int) $sortingZone->logistics_provider_id === (int) $provider->id
            && app(\App\Services\SortingZoneResolver::class)->covers($sortingZone, $order));

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
            'total' => $order->payment_method === 'cod'
                ? $shipment->cod_amount_minor / 100
                : ($sellerOrder->subtotal_minor + $sellerOrder->shipping_fee_minor) / 100,
            'payment_method' => $order->payment_method,
            'cod_amount_minor' => $shipment->cod_amount_minor,
            'pickup_address' => collect([
                $sellerOrder->seller?->pickupAddress?->line1,
                $sellerOrder->seller?->pickupAddress?->barangay,
                $sellerOrder->seller?->pickupAddress?->city,
                $sellerOrder->seller?->pickupAddress?->province,
            ])->filter()->implode(', '),
            'tracking_code' => $shipment->tracking_code,
            'logistics_provider_id' => $shipment->logistics_provider_id,
            'pickup_rider_name' => $shipment->pickupRider?->user?->full_name,
            'pickup_rider_id' => $shipment->pickup_rider_id,
            'pickup_accepted_at' => $shipment->pickup_accepted_at,
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
            'destination_sorting_area' => implode(', ', array_filter([$order->municipality, $order->province])),
            'delivery_area' => $shipment->sorting_area,
            'area' => $shipment->sorting_area,
            'failure_reason' => $shipment->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->last()?->note,
            'failure_history' => $shipment->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->values(),
            'updated_at' => $sellerOrder->status_changed_at?->toDateTimeString(),
            'pickup_eligible_riders' => $this->eligibleRiders($provider, $sellerOrder->seller?->pickupAddress?->region_code, $sellerOrder->seller?->pickupAddress?->city_municipality_code, $sellerOrder->seller?->pickupAddress?->barangay_code),
            'delivery_eligible_riders' => $zoneIsUsable ? $this->eligibleRiders($provider, $order->region_code, $order->city_municipality_code, $order->barangay_code, $sortingZone) : [],
            'sorting_zone_code' => $sortingZone?->zone_code,
            'sorting_zone_name' => $sortingZone?->name,
            'sorting_hub_name' => $sortingZone?->hub?->name,
        ];
    }

    private function eligibleRiders(LogisticsProvider $provider, ?string $region, ?string $city, ?string $barangay, ?LogisticsSortingZone $sortingZone = null): array
    {
        if (! $region || ! $city || ! $barangay) return [];
        return $provider->riders()->where('is_active', true)->where(fn ($q) => $q->where('availability', 'ONLINE')->orWhereNull('availability'))->whereNotNull('approved_at')->whereHas('user', fn ($q) => $q->where('status', 'active')->where('is_suspended', false))->whereHas('serviceAreas', function ($query) use ($provider, $region, $city, $barangay) {
            $query->where('logistics_provider_id', $provider->id)->where('is_active', true)->where('region_code', $region)->where('city_municipality_code', $city)->where(fn ($q) => $q->whereNull('barangay_code')->orWhere('barangay_code', $barangay));
        })->with(['user', 'serviceAreas', 'logisticsProvider', 'logisticsHub'])->get()
            ->filter(fn (Rider $rider) => $rider->isOperationallyAvailable()
                && $rider->assignments()->active()->count() < max(1, (int) ($rider->active_assignment_capacity ?: 5))
                && $rider->logisticsHub?->is_active
                && (int) $rider->logisticsHub->logistics_provider_id === (int) $provider->id
                && $rider->logisticsHub->barangay_code === $barangay
                && (! $sortingZone || ! $sortingZone->logistics_hub_id || (int) $sortingZone->logistics_hub_id === (int) $rider->logistics_hub_id))
            ->map(fn (Rider $rider) => ['id' => $rider->id, 'name' => $rider->user->full_name, 'area' => $rider->serviceAreas->where('is_active', true)->pluck('city_municipality_code')->implode(', ')])->values()->all();
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
            ->filter(fn (Rider $rider) => $rider->assignments()->active()->count() < max(1, (int) ($rider->active_assignment_capacity ?: 5)))
            ->map(fn (Rider $rider) => [
                'id' => $rider->id,
                'name' => $rider->user->full_name,
                'area' => 'Provider pool',
            ])
            ->all();
    }

    private function transferRiders(LogisticsProvider $provider): array
    {
        if (! $provider->main_sorting_hub_id) return [];
        return $provider->riders()->with(['user', 'logisticsHub'])
            ->where('logistics_hub_id', $provider->main_sorting_hub_id)
            ->where('is_active', true)->whereNotNull('approved_at')
            ->where(fn ($query) => $query->where('availability', 'ONLINE')->orWhereNull('availability'))
            ->whereHas('user', fn ($query) => $query->where('status', 'active')->where('is_suspended', false))
            ->get()->filter(fn (Rider $rider) => $rider->isOperationallyAvailable()
                && $rider->assignments()->active()->count() < max(1, (int) ($rider->active_assignment_capacity ?: 5)))
            ->map(fn (Rider $rider) => ['id' => $rider->id, 'name' => $rider->user->full_name])->values()->all();
    }

    private function providerRiderApplications(LogisticsProvider $provider): Collection
    {
        return Application::query()->with('user')->where('application_type', 'courier')
            ->where(function ($query) use ($provider) {
                $query->whereHas('user.rider', fn ($riders) => $riders->where('logistics_provider_id', $provider->id))
                    ->orWhere(function ($unassigned) use ($provider) {
                        $unassigned->whereDoesntHave('user.rider')
                            ->where(fn ($hint) => $hint->whereNull('application_data->logistics_provider_id')->orWhere('application_data->logistics_provider_id', $provider->id))
                            ->where(fn ($reviewer) => $reviewer->whereNull('reviewed_by')->orWhere('reviewed_by', $provider->user_id));
                    });
            })->latest()->get();
    }
}
