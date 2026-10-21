<?php

namespace App\Http\Controllers\Rider;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\DeliveryEvent;
use App\Models\RiderShippingFeeEarning;
use App\Services\OrderWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FulfillmentController extends Controller
{
    public function performance(Request $request): View
    {
        $rider = $this->riderFor($request);
        $orders = $this->databaseOrders($rider);
        $delivered = Shipment::query()->where('delivered_by', $rider->id)->whereIn('workflow_status', [OrderStatus::Delivered->value, OrderStatus::Completed->value]);
        $completedDeliveries = (clone $delivered)->count();
        $failedAttempts = DeliveryEvent::query()->where('user_id', $rider->user_id)->where('status', 'DELIVERY_ATTEMPT_FAILED')->count();
        $assigned = Shipment::query()->where('rider_id', $rider->id)->count();
        $pending = Shipment::query()->where('rider_id', $rider->id)->whereIn('workflow_status', [OrderStatus::AssignedToRider->value, OrderStatus::OutForDelivery->value])->count();
        $codCollectedMinor = (clone $delivered)->where('cod_collected', true)->sum('cod_collected_minor');
        $shippingFeeEarningsMinor = RiderShippingFeeEarning::query()->where('rider_id', $rider->id)->sum('amount_minor');

        return view('rider.earnings', [
            'orders' => $orders->all(), 'totalDeliveries' => $completedDeliveries,
            'totalEarnings' => null, 'deliveryFee' => null,
            'performance' => compact('assigned', 'pending', 'completedDeliveries', 'failedAttempts', 'codCollectedMinor', 'shippingFeeEarningsMinor'),
        ]);
    }

    public function dashboard(Request $request): View
    {
        $rider = $this->riderFor($request);
        $orders = $this->databaseOrders($rider);

        return view('rider.dashboard', [
            'application' => $this->application($rider),
            'orders' => $orders->isNotEmpty() ? $orders->all() : $request->session()->get('orders', []),
            'riderId' => $rider->id,
        ]);
    }

    public function deliveries(Request $request): View
    {
        $rider = $this->riderFor($request);
        $orders = $this->databaseOrders($rider);

        return view('rider.deliveries', [
            'orders' => $orders->isNotEmpty() ? $orders->all() : $request->session()->get('orders', []),
            'riderId' => $rider->id,
            'riderName' => $rider->user->full_name,
            'availability' => $rider->availability,
        ]);
    }

    public function history(Request $request): View
    {
        $rider = $this->riderFor($request);
        $orders = $this->databaseOrders($rider)
            ->filter(fn (array $order) => in_array($order['status'], ['delivered', 'completed'], true)
                || $order['has_failed_attempt']);

        return view('rider.history', [
            'orders' => $orders->isNotEmpty() ? $orders : collect($request->session()->get('orders', [])),
        ]);
    }

    public function accept(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $workflow->acceptPickup($sellerOrder, $this->riderFor($request));

        return back()->with('success', 'Pickup accepted. Proceed to the seller to collect the parcel.');
    }

    public function decline(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $workflow->declinePickup($sellerOrder, $this->riderFor($request));

        return back()->with('success', 'Pickup declined. Logistics can assign another available rider.');
    }

    public function acceptDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): RedirectResponse
    {
        $workflow->acceptDelivery($sellerOrder, $this->riderFor($request));

        return back()->with('success', 'Delivery assignment accepted.');
    }

    public function declineDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): RedirectResponse
    {
        $workflow->declineDelivery($sellerOrder, $this->riderFor($request));

        return back()->with('success', 'Delivery assignment declined and returned to logistics for reassignment.');
    }

    public function update(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                'PICKED_UP', 'AT_SORTING_CENTER', 'OUT_FOR_DELIVERY', 'DELIVERED',
                'picked_up', 'at_sorting_center', 'out_for_delivery', 'delivered',
            ])],
        ]);

        if (strtoupper($validated['status']) === 'DELIVERED') {
            app(\App\Services\DeliveryCompletionService::class)->deliver($sellerOrder, $request->user(), $request->all());
            return back()->with('success', 'Delivery proof saved and COD collection recorded. Awaiting buyer receipt.');
        }

        $workflow->transition(
            $sellerOrder,
            $request->user(),
            OrderStatus::from(strtoupper($validated['status']))
        );

        return back()->with('success', 'Shipment status updated successfully.');
    }

    public function failed(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'failure_reason' => ['required', Rule::in(['buyer_unavailable', 'buyer_unreachable', 'incorrect_address', 'access_issue', 'buyer_requested_reschedule', 'buyer_refused', 'other'])],
            'failure_notes' => ['nullable', 'required_if:failure_reason,other', 'string', 'max:1000'],
        ]);

        $workflow->recordFailedDelivery($sellerOrder, $request->user(), $validated['failure_reason'], $validated['failure_notes'] ?? null);

        return back()->with('success', 'Failed delivery attempt recorded.');
    }

    public function issue(
        Request $request,
        SellerOrder $sellerOrder,
        OrderWorkflowService $workflow
    ): RedirectResponse {
        $validated = $request->validate([
            'issue_category' => ['required', Rule::in(['buyer_unreachable', 'wrong_or_incomplete_address', 'damaged_parcel', 'other'])],
            'issue_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflow->recordDeliveryIssue($sellerOrder, $request->user(), $validated['issue_category'], $validated['issue_details'] ?? null);

        return back()->with('success', 'Delivery issue recorded for logistics follow-up.');
    }

    private function riderFor(Request $request): Rider
    {
        $rider = $request->user()->rider()->with(['user', 'logisticsProvider'])->first();
        abort_unless($rider && $rider->is_active && $rider->logisticsProvider?->status === 'approved', 403);

        return $rider;
    }

    private function databaseOrders(Rider $rider): Collection
    {
        return SellerOrder::query()
            ->whereHas('shipment', function ($query) use ($rider) {
                $query->where('pickup_rider_id', $rider->id)
                    ->orWhere('rider_id', $rider->id);
            })
            ->whereHas('order', fn ($query) => $query->where('status', '!=', 'CANCELLED'))
            ->with(['order.buyer', 'items', 'seller.pickupAddress', 'shipment.pickupRider.user', 'shipment.rider.user', 'shipment.events'])
            ->latest()
            ->get()
            ->map(fn (SellerOrder $order) => $this->presentOrder($order));
    }

    private function presentOrder(SellerOrder $sellerOrder): array
    {
        $order = $sellerOrder->order;
        $shipment = $sellerOrder->shipment;
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];
        $pickupAddress = $sellerOrder->seller?->pickupAddress;

        return [
            'id' => $sellerOrder->id,
            'shipment_id' => $shipment?->id,
            'source' => 'database',
            'status' => strtolower($sellerOrder->workflow_status->value),
            'order_number' => $order->order_number,
            'customer_name' => $order->recipient_name,
            'buyer_name' => $order->buyer?->full_name ?? $order->recipient_name,
            'contact' => $order->recipient_phone,
            'address' => implode(', ', array_filter([
                $order->house_number, $order->street_address, $order->barangay,
                $order->municipality, $order->province, $order->postal_code,
            ])),
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
                'name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->all(),
            'total' => ($sellerOrder->subtotal_minor + $sellerOrder->shipping_fee_minor) / 100,
            'tracking_code' => $shipment?->tracking_code,
            'pickup_rider_index' => $shipment?->pickup_rider_id,
            'pickup_rider_name' => $shipment?->pickupRider?->user?->full_name,
            'pickup_accepted_at' => $shipment?->pickup_accepted_at?->toDateTimeString(),
            'rider_index' => $shipment?->rider_id,
            'rider_name' => $shipment?->rider?->user?->full_name,
            'delivery_accepted_at' => $shipment?->delivery_accepted_at?->toDateTimeString(),
            'has_failed_attempt' => $shipment?->events->contains('status', 'DELIVERY_ATTEMPT_FAILED') ?? false,
            'failure_reason' => $shipment?->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->last()?->note,
            'created_at' => $sellerOrder->created_at?->toDateTimeString(),
            'updated_at' => $sellerOrder->status_changed_at?->toDateTimeString(),
            'navigation_address' => implode(', ', array_filter([
                $order->house_number, $order->street_address, $order->barangay,
                $order->municipality, $order->province, $order->postal_code,
            ])),
            'pickup_address' => $pickupAddress ? implode(', ', array_filter([
                $pickupAddress->line1, $pickupAddress->barangay, $pickupAddress->city,
                $pickupAddress->province, $pickupAddress->postal_code,
            ])) : null,
        ];
    }

    private function application(Rider $rider): array
    {
        return [
            'first_name' => $rider->user->first_name,
            'last_name' => $rider->user->last_name,
            'phone' => $rider->user->phone,
            'email' => $rider->user->email,
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_no,
            'status' => 'approved',
        ];
    }
}
