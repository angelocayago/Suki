<?php

namespace App\Http\Controllers\Rider;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
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
            'failure_reason' => ['required', 'string', 'max:1000'],
        ]);

        $workflow->recordFailedDelivery($sellerOrder, $request->user(), $validated['failure_reason']);

        return back()->with('success', 'Failed delivery attempt recorded.');
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
                $query->where(function ($query) use ($rider) {
                    $query->where('workflow_status', OrderStatus::ReadyForPickup->value)
                        ->whereNull('pickup_rider_id');
                })->orWhere('pickup_rider_id', $rider->id)
                    ->orWhere('rider_id', $rider->id);
            })
            ->whereHas('order', fn ($query) => $query->where('status', '!=', 'CANCELLED'))
            ->with(['order.buyer', 'items', 'seller', 'shipment.pickupRider.user', 'shipment.rider.user', 'shipment.events'])
            ->latest()
            ->get()
            ->map(fn (SellerOrder $order) => $this->presentOrder($order));
    }

    private function presentOrder(SellerOrder $sellerOrder): array
    {
        $order = $sellerOrder->order;
        $shipment = $sellerOrder->shipment;
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];

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
            'rider_index' => $shipment?->rider_id,
            'rider_name' => $shipment?->rider?->user?->full_name,
            'has_failed_attempt' => $shipment?->events->contains('status', 'DELIVERY_ATTEMPT_FAILED') ?? false,
            'failure_reason' => $shipment?->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->last()?->note,
            'created_at' => $sellerOrder->created_at?->toDateTimeString(),
            'updated_at' => $sellerOrder->status_changed_at?->toDateTimeString(),
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
