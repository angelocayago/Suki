<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryEvent;
use App\Models\Rider;
use App\Models\RiderCodRemittance;
use App\Models\RiderShippingFeeEarning;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CodRemittanceService;
use App\Services\DeliveryCompletionService;
use App\Services\MoneyReportService;
use App\Services\OrderWorkflowService;
use App\Services\ReportService;
use App\Services\ShipmentQrService;
use App\Services\ShipmentTrackingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RiderController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        return $this->ok([
            'rider_id' => $rider->id,
            'availability' => $rider->availability ?? 'ONLINE',
            'application' => $this->profileData($rider),
            'orders' => $this->orders($rider)->values(),
        ]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        return $this->ok([
            'availability' => $rider->availability ?? 'ONLINE',
            'orders' => $this->orders($rider)->values(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $orders = $this->orders($this->rider($request))->filter(fn (array $order) =>
            in_array($order['status'], ['delivered', 'completed', 'delivery_failed'], true)
                || $order['has_failed_attempt']
        )->values();
        return $this->ok(['orders' => $orders]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $delivered = Shipment::query()
            ->where('delivered_by', $rider->id)
            ->whereIn('workflow_status', [OrderStatus::Delivered->value, OrderStatus::Completed->value]);
        $performance = [
            'assigned' => Shipment::query()->where(fn ($q) => $q->where('rider_id', $rider->id)->orWhere('pickup_rider_id', $rider->id))->count(),
            'pending' => Shipment::query()->where('rider_id', $rider->id)->whereIn('workflow_status', [OrderStatus::AssignedToRider->value, OrderStatus::OutForDelivery->value])->count(),
            'completedDeliveries' => (clone $delivered)->count(),
            'failedAttempts' => DeliveryEvent::query()->where('user_id', $rider->user_id)->where('status', 'DELIVERY_ATTEMPT_FAILED')->count(),
            'codCollectedMinor' => (int) (clone $delivered)->where('cod_collected', true)->sum('cod_collected_minor'),
            'shippingFeeEarningsMinor' => (int) RiderShippingFeeEarning::query()->where('rider_id', $rider->id)->sum('amount_minor'),
        ];
        return $this->ok(['performance' => $performance]);
    }

    public function reports(Request $request, ReportService $reports): JsonResponse
    {
        $rider = $this->rider($request);
        $dates = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        [$from, $to] = \App\Support\ManilaTime::utcDateBounds($dates['from'] ?? null, $dates['to'] ?? null);
        $data = $reports->rider($rider, $from, $to);
        $data['shipments'] = collect($data['shipments'])->map(fn ($shipment) => [
            'id' => $shipment->id,
            'tracking_code' => $shipment->tracking_code,
            'status' => $shipment->workflow_status?->value ?? $shipment->workflow_status,
            'cod_collected_minor' => $shipment->cod_collected_minor,
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ])->values();
        return $this->ok($data);
    }

    public function money(Request $request, MoneyReportService $reports): JsonResponse
    {
        $data = $reports->rider($this->rider($request));
        $data['cod'] = collect($data['cod'])->map(fn ($row) => $this->remittanceData($row))->values();
        $data['earnings'] = collect($data['earnings'])->map(fn ($row) => [
            'id' => $row->id,
            'amount_minor' => $row->amount_minor,
            'earned_at' => $row->earned_at?->toIso8601String(),
            'order_number' => $row->sellerOrder?->order?->order_number,
            'tracking_code' => $row->shipment?->tracking_code,
        ])->values();
        return $this->ok($data);
    }

    public function codRemittances(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $rows = RiderCodRemittance::query()->where('rider_id', $rider->id)
            ->with(['shipment.sellerOrder.order', 'sellerOrder.order'])->latest()->get();
        return $this->ok([
            'remittances' => $rows->map(fn (RiderCodRemittance $row) => $this->remittanceData($row))->values(),
            'summary' => [
                'outstanding_minor' => (int) $rows->where('status', 'OUTSTANDING')->sum('expected_minor'),
                'submitted_minor' => (int) $rows->where('status', 'SUBMITTED')->sum('submitted_minor'),
                'accepted_minor' => (int) $rows->where('status', 'ACCEPTED')->sum('accepted_minor'),
            ],
        ]);
    }

    public function submitRemittance(Request $request, RiderCodRemittance $remittance, CodRemittanceService $service): JsonResponse
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);
        abort_unless((int) $remittance->rider_id === (int) $this->rider($request)->id, 404);
        $service->submitRider($remittance, $request->user(), $data['reference'] ?? null);
        return $this->ok(['message' => 'COD remittance submitted for Logistics review.']);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate(['availability' => ['required', Rule::in(['ONLINE', 'OFFLINE'])]]);
        $rider = $this->rider($request);
        $rider->update(['availability' => $data['availability']]);
        return $this->ok(['availability' => $rider->availability]);
    }

    public function acceptPickup(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $workflow->acceptPickup($sellerOrder, $this->rider($request));
        return $this->ok(['message' => 'Pickup accepted.']);
    }

    public function declinePickup(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $workflow->declinePickup($sellerOrder, $this->rider($request));
        return $this->ok(['message' => 'Pickup declined.']);
    }

    public function acceptDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $workflow->acceptDelivery($sellerOrder, $this->rider($request));
        return $this->ok(['message' => 'Delivery accepted.']);
    }

    public function declineDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $workflow->declineDelivery($sellerOrder, $this->rider($request));
        return $this->ok(['message' => 'Delivery declined.']);
    }

    public function updateStatus(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['PICKED_UP', 'AT_SORTING_CENTER', 'OUT_FOR_DELIVERY'])]]);
        $workflow->transition($sellerOrder, $request->user(), OrderStatus::from($data['status']));
        return $this->ok(['message' => 'Shipment status updated.']);
    }

    public function completeDelivery(Request $request, SellerOrder $sellerOrder, DeliveryCompletionService $completion): JsonResponse
    {
        $completion->deliver($sellerOrder, $request->user(), $request->all());
        return $this->ok(['message' => 'Delivery proof saved and COD collection recorded.', 'order' => $this->presentOrder($sellerOrder->fresh(['order', 'items', 'shipment']))]);
    }

    public function failedDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'failure_reason' => ['required', Rule::in(['buyer_unavailable', 'buyer_unreachable', 'incorrect_address', 'access_issue', 'buyer_requested_reschedule', 'buyer_refused', 'other'])],
            'failure_notes' => ['nullable', 'required_if:failure_reason,other', 'string', 'max:1000'],
        ]);
        $workflow->recordFailedDelivery($sellerOrder, $request->user(), $data['failure_reason'], $data['failure_notes'] ?? null);
        return $this->ok(['message' => 'Failed delivery attempt recorded.']);
    }

    public function deliveryIssue(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $data = $request->validate([
            'issue_category' => ['required', Rule::in(['buyer_unreachable', 'wrong_or_incomplete_address', 'damaged_parcel', 'other'])],
            'issue_details' => ['nullable', 'string', 'max:1000'],
        ]);
        $workflow->recordDeliveryIssue($sellerOrder, $request->user(), $data['issue_category'], $data['issue_details'] ?? null);
        return $this->ok(['message' => 'Delivery issue recorded.']);
    }

    public function resolveShipment(Request $request, ShipmentQrService $qr): JsonResponse
    {
        $data = $request->validate([
            'payload' => ['nullable', 'string', 'max:512', 'required_without:tracking_code'],
            'tracking_code' => ['nullable', 'string', 'max:100', 'required_without:payload'],
        ]);
        $entered = (string) ($data['payload'] ?? $data['tracking_code'] ?? '');
        $trackingCode = $qr->isQrPayload($entered) ? $qr->trackingCodeFromPayload($entered) : $qr->normalizeTrackingCode($entered);
        if (! $trackingCode) throw ValidationException::withMessages(['tracking_code' => 'The shipment QR code is invalid.']);
        $shipment = Shipment::query()->with(['sellerOrder.order', 'sellerOrder.seller.pickupAddress', 'sellerOrder.items', 'rider', 'pickupRider', 'events'])
            ->where('tracking_code', $trackingCode)->firstOrFail();
        $rider = $this->rider($request);
        $this->authorizeShipment($shipment, $rider);
        return $this->ok([
            'shipment' => $this->shipmentData($shipment),
            'allowed_action' => $this->allowedScanAction($shipment, $rider),
        ]);
    }

    public function scannerAction(Request $request, Shipment $shipment, OrderWorkflowService $workflow): JsonResponse
    {
        $rider = $this->rider($request);
        $this->authorizeShipment($shipment, $rider);
        $data = $request->validate(['action' => ['required', Rule::in(['accept_pickup', 'confirm_pickup', 'arrive_sorting', 'accept_delivery', 'begin_delivery'])]]);
        $shipment->loadMissing('sellerOrder.order');
        if ($this->allowedScanAction($shipment, $rider) !== $data['action']) {
            throw ValidationException::withMessages(['action' => 'This scan action is no longer available for the current shipment state.']);
        }
        $sellerOrder = $shipment->sellerOrder;
        match ($data['action']) {
            'accept_pickup' => $workflow->acceptPickup($sellerOrder, $rider),
            'confirm_pickup' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::PickedUp),
            'arrive_sorting' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::AtSortingCenter),
            'accept_delivery' => $workflow->acceptDelivery($sellerOrder, $rider),
            'begin_delivery' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::OutForDelivery),
        };
        $shipment->refresh()->load(['sellerOrder.order', 'sellerOrder.seller.pickupAddress', 'rider', 'pickupRider']);
        return $this->ok([
            'shipment' => $this->shipmentData($shipment),
            'allowed_action' => $this->allowedScanAction($shipment, $rider),
        ]);
    }

    public function location(Request $request, Shipment $shipment, ShipmentTrackingService $tracking): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $updated = $tracking->update($shipment, $this->rider($request), (float) $data['latitude'], (float) $data['longitude']);
        return $this->ok([
            'latitude' => (float) $updated->current_latitude,
            'longitude' => (float) $updated->current_longitude,
            'updated_at' => $updated->location_updated_at?->toIso8601String(),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->ok(['profile' => $this->profileData($this->rider($request))]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $rider = $this->rider($request);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user->id)],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'plate_number' => ['nullable', 'string', 'max:50'],
        ]);
        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        $user->update([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'name' => trim($first.' '.$last),
        ]);
        $rider->update([
            'vehicle_type' => $data['vehicle_type'] ?? $rider->vehicle_type,
            'plate_no' => $data['plate_number'] ?? $rider->plate_no,
        ]);
        return $this->profile($request);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $this->rider($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        if (Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The new password must be different from the current password.']);
        }
        $request->user()->update(['password' => $data['password']]);
        return $this->ok(['message' => 'Password updated.']);
    }

    private function rider(Request $request): Rider
    {
        $rider = $request->user()?->rider()->with(['user', 'logisticsProvider', 'logisticsHub'])->first();
        abort_unless($rider && $rider->isOperationallyEligible(), 403, 'An active rider profile is required.');
        return $rider;
    }

    private function orders(Rider $rider)
    {
        return SellerOrder::query()
            ->whereHas('shipment', fn ($query) => $query->where('pickup_rider_id', $rider->id)->orWhere('rider_id', $rider->id))
            ->whereHas('order', fn ($query) => $query->where('status', '!=', 'CANCELLED'))
            ->with(['order.buyer', 'items', 'seller.pickupAddress', 'shipment.pickupRider.user', 'shipment.rider.user', 'shipment.events'])
            ->latest()->get()->map(fn (SellerOrder $order) => $this->presentOrder($order));
    }

    private function presentOrder(SellerOrder $sellerOrder): array
    {
        $order = $sellerOrder->order;
        $shipment = $sellerOrder->shipment;
        $pickup = $sellerOrder->seller?->pickupAddress;
        $address = implode(', ', array_filter([
            $order?->house_number, $order?->street_address, $order?->barangay,
            $order?->municipality, $order?->province, $order?->postal_code,
        ]));
        return [
            'id' => $sellerOrder->id,
            'shipment_id' => $shipment?->id,
            'status' => $sellerOrder->workflow_status?->value ? strtolower($sellerOrder->workflow_status->value) : strtolower((string) $sellerOrder->workflow_status),
            'order_number' => $order?->order_number,
            'customer_name' => $order?->recipient_name,
            'buyer_name' => $order?->buyer?->full_name ?? $order?->recipient_name,
            'contact' => $order?->recipient_phone,
            'address' => $address,
            'navigation_address' => $address,
            'pickup_address' => $pickup ? implode(', ', array_filter([$pickup->line1, $pickup->barangay, $pickup->city, $pickup->province, $pickup->postal_code])) : null,
            'items' => $sellerOrder->items->map(fn ($item) => ['name' => $item->product_name, 'quantity' => $item->quantity])->all(),
            'total' => $order?->payment_method === 'cod' ? (($shipment?->cod_amount_minor ?? 0) / 100) : (($sellerOrder->subtotal_minor + $sellerOrder->shipping_fee_minor) / 100),
            'payment_method' => $order?->payment_method,
            'tracking_code' => $shipment?->tracking_code,
            'pickup_accepted_at' => $shipment?->pickup_accepted_at?->toIso8601String(),
            'delivery_accepted_at' => $shipment?->delivery_accepted_at?->toIso8601String(),
            'has_failed_attempt' => $shipment?->events->contains('status', 'DELIVERY_ATTEMPT_FAILED') ?? false,
            'failure_reason' => $shipment?->events->where('status', 'DELIVERY_ATTEMPT_FAILED')->last()?->note,
            'created_at' => $sellerOrder->created_at?->toIso8601String(),
            'updated_at' => $sellerOrder->status_changed_at?->toIso8601String(),
        ];
    }

    private function profileData(Rider $rider): array
    {
        return [
            'rider_id' => $rider->id,
            'first_name' => $rider->user->first_name,
            'last_name' => $rider->user->last_name,
            'email' => $rider->user->email,
            'phone' => $rider->user->phone,
            'status' => $rider->is_active ? 'active' : 'inactive',
            'availability' => $rider->availability ?? 'ONLINE',
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_no,
            'license_status' => $rider->approved_at ? 'Approved' : 'Pending review',
            'logistics_provider' => $rider->logisticsProvider?->name,
        ];
    }

    private function authorizeShipment(Shipment $shipment, Rider $rider): void
    {
        if (! in_array((int) $rider->id, [(int) $shipment->pickup_rider_id, (int) $shipment->rider_id], true)) {
            throw new AuthorizationException('This shipment is not assigned to you.');
        }
    }

    private function allowedScanAction(Shipment $shipment, Rider $rider): ?string
    {
        $status = $shipment->workflow_status instanceof OrderStatus
            ? $shipment->workflow_status
            : OrderStatus::tryFrom(strtoupper((string) $shipment->workflow_status));
        if ((int) $shipment->pickup_rider_id === (int) $rider->id) {
            return match ($status) {
                OrderStatus::ReadyForPickup => $shipment->pickup_accepted_at ? 'confirm_pickup' : 'accept_pickup',
                OrderStatus::PickedUp => 'arrive_sorting',
                default => null,
            };
        }
        if ((int) $shipment->rider_id === (int) $rider->id) {
            return match ($status) {
                OrderStatus::AssignedToRider => $shipment->delivery_accepted_at ? 'begin_delivery' : 'accept_delivery',
                default => null,
            };
        }
        return null;
    }

    private function shipmentData(Shipment $shipment): array
    {
        $order = $shipment->sellerOrder?->order;
        return [
            'id' => $shipment->id,
            'seller_order_id' => $shipment->seller_order_id,
            'tracking_code' => $shipment->tracking_code,
            'order_number' => $order?->order_number,
            'status' => $shipment->workflow_status?->value ?? $shipment->workflow_status,
            'pickup_address' => $shipment->sellerOrder?->seller?->pickupAddress?->line1,
            'address' => $order?->street_address,
            'customer_name' => $order?->recipient_name,
        ];
    }

    private function remittanceData(RiderCodRemittance $row): array
    {
        return [
            'id' => $row->id,
            'status' => $row->status,
            'expected_minor' => $row->expected_minor,
            'submitted_minor' => $row->submitted_minor,
            'accepted_minor' => $row->accepted_minor,
            'reference' => $row->reference,
            'reason' => $row->reason,
            'order_number' => $row->sellerOrder?->order?->order_number,
            'tracking_code' => $row->shipment?->tracking_code,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
