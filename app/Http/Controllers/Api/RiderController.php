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
use App\Services\CodRemittanceService;
use App\Services\DeliveryCompletionService;
use App\Services\MoneyReportService;
use App\Services\OrderWorkflowService;
use App\Services\ReportService;
use App\Services\ShipmentQrService;
use App\Support\RiderApiScanVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Rider mobile API (/api/rider/*), matching the suki-mobile Flutter contract.
 *
 * Every operation delegates to the same services the web Rider portal uses
 * (OrderWorkflowService, DeliveryCompletionService, CodRemittanceService), so
 * assignment ownership, provider ownership, eligibility, row locks and
 * idempotency are enforced in one place. Financial amounts are never read from
 * the request: COD comes from the persisted shipment allocation.
 */
class RiderController extends Controller
{
    private const SCAN_MODES = ['pickup', 'sorting_center_arrival', 'hub_transfer_dispatch', 'last_mile_dispatch'];
    private const SCAN_ACTIONS = ['accept_pickup', 'confirm_pickup', 'arrive_sorting', 'accept_delivery', 'begin_delivery', 'dispatch_hub', 'accept_hub_transfer', 'decline_hub_transfer'];

    public function dashboard(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        return $this->ok([
            'rider_id' => $rider->id,
            'availability' => $rider->availability ?? 'OFFLINE',
            'application' => $this->profileData($rider),
            'orders' => $this->orders($rider)->values(),
        ]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $rider = $this->rider($request);

        return $this->ok([
            'availability' => $rider->availability ?? 'OFFLINE',
            'orders' => $this->orders($rider)->values(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $orders = $this->orders($this->rider($request))
            ->filter(fn (array $order) => in_array($order['status'], ['delivered', 'completed'], true) || $order['has_failed_attempt'])
            ->values();

        return $this->ok(['orders' => $orders]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        // delivered_by stores the delivering rider's *user* id.
        $delivered = Shipment::query()
            ->where('delivered_by', $rider->user_id)
            ->whereIn('workflow_status', [OrderStatus::Delivered->value, OrderStatus::Completed->value]);

        return $this->ok(['performance' => [
            'assigned' => Shipment::query()->where('rider_id', $rider->id)->count(),
            'pending' => Shipment::query()->where('rider_id', $rider->id)->whereIn('workflow_status', [OrderStatus::AssignedToRider->value, OrderStatus::OutForDelivery->value])->count(),
            'completedDeliveries' => (clone $delivered)->count(),
            'failedAttempts' => DeliveryEvent::query()->where('user_id', $rider->user_id)->where('status', 'DELIVERY_ATTEMPT_FAILED')->count(),
            'codCollectedMinor' => (int) (clone $delivered)->where('cod_collected', true)->sum('cod_collected_minor'),
            'shippingFeeEarningsMinor' => (int) RiderShippingFeeEarning::query()->where('rider_id', $rider->id)->sum('amount_minor'),
        ]]);
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
        $data['shipments'] = collect($data['shipments'])->map(fn (Shipment $shipment) => [
            'id' => $shipment->id,
            'tracking_code' => $shipment->tracking_code,
            'status' => $this->statusValue($shipment->workflow_status),
            'cod_collected_minor' => $shipment->cod_collected_minor,
            'updated_at' => $shipment->updated_at?->toIso8601String(),
        ])->values();

        return $this->ok($data);
    }

    public function money(Request $request, MoneyReportService $reports): JsonResponse
    {
        $data = $reports->rider($this->rider($request));
        $data['cod'] = collect($data['cod'])->map(fn (RiderCodRemittance $row) => $this->remittanceData($row))->values();
        $data['earnings'] = collect($data['earnings'])->map(fn (RiderShippingFeeEarning $row) => [
            'id' => $row->id,
            'amount_minor' => (int) $row->amount_minor,
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
            ->with(['shipment', 'sellerOrder.order'])->latest('id')->get();

        return $this->ok([
            'remittances' => $rows->map(fn (RiderCodRemittance $row) => $this->remittanceData($row))->values(),
            'summary' => [
                'cash_on_hand_minor' => (int) $rows->whereIn('status', ['PENDING', 'OUTSTANDING'])->sum('expected_minor'),
                'pending_verification_minor' => (int) $rows->where('status', 'SUBMITTED')->sum('submitted_minor'),
                'verified_remitted_minor' => (int) $rows->whereIn('status', ['VERIFIED', 'ACCEPTED'])->sum('accepted_minor'),
            ],
        ]);
    }

    public function submitRemittance(Request $request, int $remittance, CodRemittanceService $service): JsonResponse
    {
        $rider = $this->rider($request);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            // Optional rider declaration; a mismatch is recorded as a
            // discrepancy for Logistics, never as the accepted amount.
            'amount_minor' => ['nullable', 'integer', 'min:1'],
        ]);
        $row = RiderCodRemittance::query()->where('rider_id', $rider->id)->findOrFail($remittance);
        $service->submitRider($row, $request->user(), isset($data['amount_minor']) ? (int) $data['amount_minor'] : null, trim($data['reference']));

        return $this->ok([
            'message' => 'COD remittance recorded for Logistics verification.',
            'remittance' => $this->remittanceData($row->fresh(['shipment', 'sellerOrder.order'])),
        ]);
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

        return $this->ok(['message' => 'Pickup accepted. Proceed to the seller to collect the parcel.']);
    }

    public function declinePickup(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $workflow->declinePickup($sellerOrder, $this->rider($request));

        return $this->ok(['message' => 'Pickup declined. Logistics can assign another available rider.']);
    }

    public function acceptDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $workflow->acceptDelivery($sellerOrder, $this->rider($request));

        return $this->ok(['message' => 'Delivery assignment accepted.']);
    }

    public function declineDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $workflow->declineDelivery($sellerOrder, $this->rider($request));

        return $this->ok(['message' => 'Delivery assignment declined and returned to logistics for reassignment.']);
    }

    /**
     * Mirrors the web rule: pickup, sorting-center arrival and dispatch are
     * never accepted as free-form status updates; they require the scanner.
     * DELIVERED is accepted only with delivery proof (DeliveryCompletionService).
     */
    public function updateStatus(Request $request, SellerOrder $sellerOrder, DeliveryCompletionService $completion): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string', Rule::in([
            'PICKED_UP', 'AT_SORTING_CENTER', 'OUT_FOR_DELIVERY', 'DELIVERED',
            'picked_up', 'at_sorting_center', 'out_for_delivery', 'delivered',
        ])]]);
        $this->rider($request);
        if (strtoupper($data['status']) !== 'DELIVERED') {
            throw ValidationException::withMessages([
                'status' => 'Pickup, sorting-center arrival, and delivery dispatch must be confirmed through the Shipment Scanner.',
            ]);
        }

        return $this->completeDelivery($request, $sellerOrder, $completion);
    }

    public function completeDelivery(Request $request, SellerOrder $sellerOrder, DeliveryCompletionService $completion): JsonResponse
    {
        $this->rider($request);
        // The service locks the parcel and requires the actor to be its
        // accepted, active last-mile rider with an approved provider.
        $completion->deliver($sellerOrder, $request->user(), $request->only(['proof_photo', 'received_by', 'delivery_note', 'cod_collected']));
        $fresh = SellerOrder::query()->with(['order.buyer', 'items', 'seller.pickupAddress', 'shipment.events'])->findOrFail($sellerOrder->id);

        return $this->ok([
            'message' => $fresh->order->payment_method === 'cod'
                ? 'Delivery proof saved and COD collection recorded. Awaiting buyer receipt.'
                : 'Delivery proof saved. Awaiting buyer receipt.',
            'order' => $this->presentOrder($fresh),
        ]);
    }

    public function failedDelivery(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $this->rider($request);
        $data = $request->validate([
            'failure_reason' => ['required', Rule::in(['buyer_unavailable', 'buyer_unreachable', 'incorrect_address', 'access_issue', 'buyer_requested_reschedule', 'buyer_refused', 'other'])],
            'failure_notes' => ['nullable', 'required_if:failure_reason,other', 'string', 'max:1000'],
        ]);
        $workflow->recordFailedDelivery($sellerOrder, $request->user(), $data['failure_reason'], $data['failure_notes'] ?? null);

        return $this->ok(['message' => 'Failed delivery attempt recorded.']);
    }

    public function deliveryIssue(Request $request, SellerOrder $sellerOrder, OrderWorkflowService $workflow): JsonResponse
    {
        $this->rider($request);
        $data = $request->validate([
            'issue_category' => ['required', Rule::in(['buyer_unreachable', 'wrong_or_incomplete_address', 'damaged_parcel', 'other'])],
            'issue_details' => ['nullable', 'string', 'max:1000'],
        ]);
        $workflow->recordDeliveryIssue($sellerOrder, $request->user(), $data['issue_category'], $data['issue_details'] ?? null);

        return $this->ok(['message' => 'Delivery issue recorded for logistics follow-up.']);
    }

    /**
     * Verifies one exact shipment (signed QR payload or exact tracking code)
     * and records a 15-minute, token-bound, single-use verification.
     */
    public function resolveShipment(Request $request, ShipmentQrService $qr): JsonResponse
    {
        $rider = $this->rider($request);
        RiderApiScanVerification::forget($request, $rider);

        $data = $request->validate([
            'payload' => ['nullable', 'string', 'max:256', 'required_without:tracking_code'],
            'tracking_code' => ['nullable', 'string', 'max:100', 'required_without:payload'],
            'mode' => ['nullable', Rule::in(self::SCAN_MODES)],
        ]);
        $entered = (string) ($data['payload'] ?? $data['tracking_code'] ?? '');
        $trackingCode = $qr->isQrPayload($entered) ? $qr->trackingCodeFromPayload($entered) : $qr->normalizeTrackingCode($entered);
        if (! $trackingCode) {
            throw ValidationException::withMessages(['payload' => 'The shipment QR code is invalid or has been altered.']);
        }

        $shipment = Shipment::query()->with(['sellerOrder.order', 'sellerOrder.seller.pickupAddress', 'sellerOrder.items'])
            ->where('tracking_code', $trackingCode)->first();
        // Unknown and foreign shipments are indistinguishable to the caller.
        if (! $shipment || ! $this->isAssigned($shipment, $rider)) {
            abort(404, 'No shipment assigned to you matches this code.');
        }

        $mode = $data['mode'] ?? $this->inferMode($shipment, $rider);
        RiderApiScanVerification::put($request, $rider, (int) $shipment->id, $mode);

        return $this->ok([
            'verified' => true,
            'verification_expires_in' => RiderApiScanVerification::TTL_SECONDS,
            'mode' => $mode,
            'shipment' => $this->shipmentData($shipment),
            'allowed_action' => $this->allowedAction($shipment, $rider, $mode),
        ]);
    }

    public function scannerAction(Request $request, int $shipment, OrderWorkflowService $workflow): JsonResponse
    {
        $rider = $this->rider($request);
        $data = $request->validate([
            'action' => ['required', Rule::in(self::SCAN_ACTIONS)],
            'transfer_decline_reason' => ['required_if:action,decline_hub_transfer', 'nullable', 'string', 'min:3', 'max:250'],
        ]);

        $model = Shipment::query()->with(['sellerOrder.order'])->find($shipment);
        if (! $model || ! $this->isAssigned($model, $rider)) {
            abort(404, 'No shipment assigned to you matches this reference.');
        }

        $verification = RiderApiScanVerification::get($request, $rider, (int) $model->id);
        if (! $verification) {
            throw ValidationException::withMessages(['action' => 'Scan and verify this exact shipment before recording this rider workflow action.']);
        }
        if (! RiderApiScanVerification::claim($request, $rider)) {
            throw ValidationException::withMessages(['action' => 'This scan verification is already being used. Scan the shipment again.']);
        }

        try {
            $action = $data['action'];
            $allowed = $this->allowedAction($model->fresh(['sellerOrder']), $rider, $verification['mode'] ?? null);
            if ($action === 'decline_hub_transfer' ? $allowed !== 'accept_hub_transfer' : $allowed !== $action) {
                throw ValidationException::withMessages(['action' => 'This scan action is no longer available for the current shipment state.']);
            }

            $sellerOrder = $model->sellerOrder;
            match ($action) {
                'accept_pickup' => $workflow->acceptPickup($sellerOrder, $rider),
                'confirm_pickup' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::PickedUp),
                'arrive_sorting' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::AtSortingCenter),
                'accept_delivery' => $workflow->acceptDelivery($sellerOrder, $rider),
                'begin_delivery' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::OutForDelivery),
                'dispatch_hub' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::InTransitToHub, ['workflow_event_note' => 'Hub transfer dispatch verified by Rider mobile Shipment Scanner mode hub_transfer_dispatch.']),
                'accept_hub_transfer' => $workflow->acceptHubTransfer($sellerOrder, $rider),
                'decline_hub_transfer' => $workflow->declineHubTransfer($sellerOrder, $rider, $data['transfer_decline_reason']),
            };
        } catch (\Throwable $error) {
            // Nothing changed; keep the verification so the rider may retry.
            RiderApiScanVerification::release($request, $rider);
            throw $error;
        }
        RiderApiScanVerification::consume($request, $rider);

        $fresh = Shipment::query()->with(['sellerOrder.order', 'sellerOrder.seller.pickupAddress'])->findOrFail($model->id);

        return $this->ok([
            'message' => 'Shipment scan action recorded.',
            'shipment' => $this->shipmentData($fresh),
            // A new action always requires a new scan.
            'allowed_action' => null,
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->ok(['profile' => $this->profileData($this->rider($request))]);
    }

    /**
     * Name changes only. Phone, vehicle and plate were verified/approved during
     * rider onboarding and are changed through the logistics provider, so a
     * mobile request cannot bypass contact verification or provider review.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $rider = $this->rider($request);
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'plate_number' => ['nullable', 'string', 'max:50'],
        ]);
        $locked = [
            'phone' => [$data['phone'] ?? null, $user->phone],
            'vehicle_type' => [$data['vehicle_type'] ?? null, $rider->vehicle_type],
            'plate_number' => [$data['plate_number'] ?? null, $rider->plate_no],
        ];
        foreach ($locked as $field => [$submitted, $current]) {
            if (filled($submitted) && trim((string) $submitted) !== (string) $current) {
                throw ValidationException::withMessages([$field => 'Contact your logistics provider to change verified rider details.']);
            }
        }
        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        $user->update(['first_name' => $first, 'last_name' => $last, 'name' => trim($first.' '.$last)]);

        return $this->ok(['profile' => $this->profileData($rider->fresh(['user', 'logisticsProvider', 'logisticsHub']))]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $this->rider($request);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'The new password must be different from the current password.']);
        }
        $user->update(['password' => Hash::make($data['password'])]);
        // Sign out every other device; keep only the token making this request.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current instanceof PersonalAccessToken, fn ($q) => $q->whereKeyNot($current->getKey()))->delete();

        return $this->ok(['message' => 'Password updated. Other devices were signed out.']);
    }

    private function rider(Request $request): Rider
    {
        $rider = $request->attributes->get('apiRider');
        if (! $rider instanceof Rider || (int) $rider->user_id !== (int) $request->user()?->id) {
            $rider = $request->user()?->rider()->with(['user', 'logisticsProvider', 'logisticsHub'])->first();
        }
        abort_unless($rider && $rider->isOperationallyEligible(), 403, 'An active, approved rider account is required.');

        return $rider;
    }

    private function orders(Rider $rider): Collection
    {
        return SellerOrder::query()
            ->whereHas('shipment', fn ($query) => $query->where(fn ($q) => $q->where('pickup_rider_id', $rider->id)->orWhere('rider_id', $rider->id)))
            ->whereHas('order', fn ($query) => $query->where('status', '!=', 'CANCELLED'))
            ->with(['order.buyer', 'items', 'seller.pickupAddress', 'shipment.events'])
            ->latest()->get()
            ->map(fn (SellerOrder $order) => $this->presentOrder($order));
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
            'status' => strtolower((string) $this->statusValue($sellerOrder->workflow_status)),
            'order_number' => $order?->order_number,
            'customer_name' => $order?->recipient_name,
            'buyer_name' => $order?->buyer?->full_name ?? $order?->recipient_name,
            'contact' => $order?->recipient_phone,
            'address' => $address,
            'navigation_address' => $address,
            'pickup_address' => $pickup ? implode(', ', array_filter([$pickup->line1, $pickup->barangay, $pickup->city, $pickup->province, $pickup->postal_code])) : null,
            'items' => $sellerOrder->items->map(fn ($item) => ['name' => $item->product_name, 'quantity' => (int) $item->quantity])->values()->all(),
            // COD is the server-side per-shipment allocation (vouchers and shipping included).
            'total' => $order?->payment_method === 'cod'
                ? ((int) ($shipment?->cod_amount_minor ?? 0)) / 100
                : ((int) $sellerOrder->subtotal_minor + (int) $sellerOrder->shipping_fee_minor) / 100,
            'cod_amount_minor' => $order?->payment_method === 'cod' ? (int) ($shipment?->cod_amount_minor ?? 0) : 0,
            'payment_method' => $order?->payment_method,
            'tracking_code' => $shipment?->tracking_code,
            'pickup_accepted_at' => $shipment?->pickup_accepted_at?->toIso8601String(),
            'delivery_accepted_at' => $shipment?->delivery_accepted_at?->toIso8601String(),
            'has_failed_attempt' => (bool) ($shipment?->events->contains('status', 'DELIVERY_ATTEMPT_FAILED') ?? false),
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
            'availability' => $rider->availability ?? 'OFFLINE',
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_no,
            'license_status' => $rider->approved_at ? 'Approved' : 'Pending review',
            'logistics_provider' => $rider->logisticsProvider?->name,
            'logistics_hub' => $rider->logisticsHub?->name,
        ];
    }

    private function isAssigned(Shipment $shipment, Rider $rider): bool
    {
        if (in_array((int) $rider->id, [(int) $shipment->pickup_rider_id, (int) $shipment->rider_id], true)) return true;

        return $shipment->assignments()->where('assignment_type', 'HUB_TRANSFER')->active()->where('rider_id', $rider->id)->exists();
    }

    /** Mobile clients may omit the mode; derive it from this rider's role on the parcel. */
    private function inferMode(Shipment $shipment, Rider $rider): ?string
    {
        $status = $this->status($shipment);
        if ($status === OrderStatus::Sorted && $this->hasActiveTransfer($shipment, $rider)) return 'hub_transfer_dispatch';
        if ((int) $shipment->pickup_rider_id === (int) $rider->id) {
            if ($status === OrderStatus::ReadyForPickup) return 'pickup';
            if ($status === OrderStatus::PickedUp) return 'sorting_center_arrival';
        }
        if ((int) $shipment->rider_id === (int) $rider->id && $status === OrderStatus::AssignedToRider) return 'last_mile_dispatch';

        return null;
    }

    /** Same state machine as ShipmentScannerController::allowedAction for riders. */
    private function allowedAction(Shipment $shipment, Rider $rider, ?string $mode): ?string
    {
        $status = $this->status($shipment);
        if ($status === OrderStatus::Sorted && $this->hasActiveTransfer($shipment, $rider)) {
            if ($mode !== 'hub_transfer_dispatch') return null;

            return $shipment->assignments()->where('assignment_type', 'HUB_TRANSFER')->where('status', 'ACCEPTED')->where('rider_id', $rider->id)->exists()
                ? 'dispatch_hub' : 'accept_hub_transfer';
        }
        if ((int) $shipment->pickup_rider_id === (int) $rider->id) {
            return match ($status) {
                OrderStatus::ReadyForPickup => $mode === 'pickup' ? ($shipment->pickup_accepted_at ? 'confirm_pickup' : 'accept_pickup') : null,
                OrderStatus::PickedUp => $mode === 'sorting_center_arrival' ? 'arrive_sorting' : null,
                default => null,
            };
        }
        if ((int) $shipment->rider_id === (int) $rider->id) {
            return $status === OrderStatus::AssignedToRider && $mode === 'last_mile_dispatch'
                ? ($shipment->delivery_accepted_at ? 'begin_delivery' : 'accept_delivery')
                : null;
        }

        return null;
    }

    private function hasActiveTransfer(Shipment $shipment, Rider $rider): bool
    {
        return $shipment->assignments()->where('assignment_type', 'HUB_TRANSFER')->where('rider_id', $rider->id)->active()->exists();
    }

    private function status(Shipment $shipment): ?OrderStatus
    {
        $value = $shipment->sellerOrder?->workflow_status;

        return $value instanceof OrderStatus ? $value : OrderStatus::tryFrom(strtoupper((string) $value));
    }

    private function statusValue(mixed $status): ?string
    {
        return $status instanceof OrderStatus ? $status->value : ($status === null ? null : (string) $status);
    }

    private function shipmentData(Shipment $shipment): array
    {
        $order = $shipment->sellerOrder?->order;
        $pickup = $shipment->sellerOrder?->seller?->pickupAddress;

        return [
            'id' => $shipment->id,
            'seller_order_id' => $shipment->seller_order_id,
            'tracking_code' => $shipment->tracking_code,
            'order_number' => $order?->order_number,
            'status' => $this->statusValue($shipment->sellerOrder?->workflow_status),
            'pickup_address' => $pickup ? implode(', ', array_filter([$pickup->line1, $pickup->barangay, $pickup->city, $pickup->province])) : null,
            'address' => $order ? implode(', ', array_filter([$order->house_number, $order->street_address, $order->barangay, $order->municipality, $order->province])) : null,
            'customer_name' => $order?->recipient_name,
            'payment_method' => $order?->payment_method,
            'cod_amount_minor' => $order?->payment_method === 'cod' ? (int) $shipment->cod_amount_minor : 0,
        ];
    }

    private function remittanceData(RiderCodRemittance $row): array
    {
        return [
            'id' => $row->id,
            'status' => $row->status,
            'expected_minor' => (int) $row->expected_minor,
            'submitted_minor' => $row->submitted_minor === null ? null : (int) $row->submitted_minor,
            'accepted_minor' => $row->accepted_minor === null ? null : (int) $row->accepted_minor,
            'reference' => $row->reference,
            'reason' => $row->reason,
            'order_number' => $row->sellerOrder?->order?->order_number,
            'tracking_code' => $row->shipment?->tracking_code,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
