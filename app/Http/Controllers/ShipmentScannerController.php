<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\LogisticsProvider;
use App\Models\LogisticsSortingZone;
use App\Models\Rider;
use App\Models\Shipment;
use App\Services\OrderWorkflowService;
use App\Services\ShipmentQrService;
use App\Services\SortingZoneResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShipmentScannerController extends Controller
{
    public function logistics(Request $request): View
    {
        $trackingCode = $request->query('tracking_code');
        $mode = $this->verifiedMode($request, 'logistics') ?? (in_array($request->query('mode'), ['pickup', 'sorting_center_arrival', 'sort', 'hub_transfer_dispatch', 'hub_arrival', 'last_mile_dispatch', 'delivery'], true) ? $request->query('mode') : null);
        return $this->scannerView('logistics', $this->verifiedShipmentFromSession($request, 'logistics'), is_string($trackingCode) ? substr(trim($trackingCode), 0, 100) : '', $mode);
    }

    public function rider(Request $request): View
    {
        $trackingCode = $request->query('tracking_code');
        $mode = $this->verifiedMode($request, 'rider') ?? (in_array($request->query('mode'), ['pickup', 'sorting_center_arrival', 'sort', 'hub_transfer_dispatch', 'hub_arrival', 'last_mile_dispatch', 'delivery'], true) ? $request->query('mode') : null);

        return $this->scannerView(
            'rider',
            $this->verifiedShipmentFromSession($request, 'rider'),
            is_string($trackingCode) ? substr(trim($trackingCode), 0, 100) : '',
            $mode
        );
    }

    public function resolve(Request $request, ShipmentQrService $qr, string $actorType): RedirectResponse
    {
        $scopeId = $actorType === 'logistics'
            ? $this->providerFor($request)->id
            : $this->riderFor($request)->id;
        $request->session()->forget(self::verifiedResultKey($actorType, $scopeId));
        if ($actorType === 'logistics') {
            $request->session()->forget(self::sortingVerificationKey($scopeId));
        } else {
            $request->session()->forget(self::riderVerificationKey($scopeId));
        }

        $data = $request->validate([
            // Prefix + 100-char tracking code + separators + SHA-256 hex HMAC is at most 182 chars.
            'payload' => ['nullable', 'string', 'max:256', 'required_without:tracking_code'],
            'tracking_code' => ['nullable', 'string', 'max:100', 'required_without:payload'],
            'mode' => ['nullable', 'in:pickup,sorting_center_arrival,sort,hub_transfer_dispatch,hub_arrival,last_mile_dispatch,delivery'],
            'hub_id' => ['nullable', 'integer', 'required_if:mode,hub_arrival'],
        ]);

        $enteredValue = (string) ($data['payload'] ?? $data['tracking_code'] ?? '');
        $trackingCode = $qr->isQrPayload($enteredValue)
            ? $qr->trackingCodeFromPayload($enteredValue)
            : $qr->normalizeTrackingCode($enteredValue);

        if (! $trackingCode) {
            throw ValidationException::withMessages(['payload' => 'The shipment QR code is invalid or has been altered.']);
        }

        $provider = $actorType === 'logistics' ? $this->providerFor($request) : null;

        $shipment = Shipment::query()
            ->with(['sellerOrder.order', 'sellerOrder.seller', 'provider', 'rider.user', 'pickupRider.user', 'events'])
            ->where('tracking_code', $trackingCode)
            ->firstOrFail();

        $this->authorizeShipment($request, $shipment, $actorType);

        $verifiedHubId = null;
        if ($actorType === 'logistics' && ($data['mode'] ?? null) === 'hub_arrival') {
            $verifiedHub = $provider->hubs()->whereKey($data['hub_id'])->where('is_active', true)->first();
            if (! $verifiedHub || (int) $shipment->destination_hub_id !== (int) $verifiedHub->id) {
                throw ValidationException::withMessages(['hub_id' => 'Select the active destination hub assigned to this shipment.']);
            }
            $verifiedHubId = (int) $verifiedHub->id;
        }

        if ($provider && $shipment->sellerOrder->workflow_status === OrderStatus::AtSortingCenter) {
            $request->session()->put(self::sortingVerificationKey($provider->id), $shipment->id);
        }

        $request->session()->put(self::verifiedResultKey($actorType, $scopeId), [
            'shipment_id' => $shipment->id,
            'verified_at' => now()->timestamp,
            'mode' => $data['mode'] ?? null,
            'hub_id' => $verifiedHubId,
        ]);
        if ($actorType === 'rider') {
            $request->session()->put(self::riderVerificationKey($scopeId), [
                'shipment_id' => (int) $shipment->id,
                'verified_at' => now()->timestamp,
            ]);
        }

        return $this->redirectToScanner($actorType);
    }

    public function action(
        Request $request,
        Shipment $shipment,
        OrderWorkflowService $workflow,
        string $actorType
    ): RedirectResponse {
        $this->authorizeShipment($request, $shipment->loadMissing(['sellerOrder.order', 'events']), $actorType);
        $data = $request->validate([
            'action' => ['required', 'in:accept_pickup,confirm_pickup,arrive_sorting,receive_sorting,mark_sorted,accept_delivery,begin_delivery,dispatch_hub,arrive_hub,accept_hub_transfer,decline_hub_transfer'],
            'transfer_decline_reason' => ['required_if:action,decline_hub_transfer', 'nullable', 'string', 'min:3', 'max:250'],
            'sorting_zone_id' => ['nullable', 'required_if:action,mark_sorted', 'integer'],
            'override_reason' => ['nullable', 'in:temporary_route_change,hub_capacity,area_reassignment,operational_exception,other'],
            'override_note' => ['nullable', 'required_if:override_reason,other', 'string', 'max:250'],
        ]);

        $action = $data['action'];
        $sellerOrder = $shipment->sellerOrder;

        if ($actorType === 'rider'
            && in_array($action, ['confirm_pickup', 'arrive_sorting', 'begin_delivery', 'dispatch_hub', 'accept_hub_transfer', 'decline_hub_transfer'], true)) {
            $rider = $this->riderFor($request);
            $verification = $request->session()->get(self::riderVerificationKey($rider->id));
            if (! is_array($verification)
                || (int) ($verification['shipment_id'] ?? 0) !== (int) $shipment->id
                || ! is_numeric($verification['verified_at'] ?? null)
                || now()->timestamp - (int) $verification['verified_at'] > 900) {
                throw ValidationException::withMessages([
                    'action' => 'Scan and verify this exact shipment before recording this rider workflow action.',
                ]);
            }
        }

        if ($action === 'mark_sorted') {
            $provider = $this->providerFor($request);
            if ((int) $request->session()->get(self::sortingVerificationKey($provider->id)) !== (int) $shipment->id) {
                throw ValidationException::withMessages(['action' => 'Scan and verify this exact parcel before confirming sorting.']);
            }
        }

        if ($actorType === 'logistics' && in_array($action, ['dispatch_hub', 'arrive_hub'], true)) {
            $provider = $this->providerFor($request);
            $verified = $request->session()->get(self::verifiedResultKey('logistics', $provider->id));
            if (! is_array($verified) || (int) ($verified['shipment_id'] ?? 0) !== (int) $shipment->id || now()->timestamp - (int) ($verified['verified_at'] ?? 0) > 900) {
                throw ValidationException::withMessages(['action' => 'Scan and verify this exact shipment before hub transfer.']);
            }
            if ($action === 'arrive_hub' && (int) ($verified['hub_id'] ?? 0) !== (int) $shipment->destination_hub_id) {
                throw ValidationException::withMessages(['hub_id' => 'Verify this shipment at its assigned destination hub.']);
            }
        }
        if ($actorType === 'logistics' && $action === 'receive_sorting') {
            $provider = $this->providerFor($request);
            $verified = $request->session()->get(self::verifiedResultKey('logistics', $provider->id));
            if (! is_array($verified) || (int) ($verified['shipment_id'] ?? 0) !== (int) $shipment->id || now()->timestamp - (int) ($verified['verified_at'] ?? 0) > 900) {
                throw ValidationException::withMessages(['action' => 'Scan and verify this exact pickup shipment before sorting-center receipt.']);
            }
        }

        if ($action === 'receive_sorting' && $shipment->events()->where('status', 'RECEIVED_AT_SORTING_CENTER')->exists()) {
            return $this->redirectToScanner($actorType)->with('success', 'This parcel was already received at the sorting center.');
        }

        $allowed = $this->allowedAction($request, $shipment->fresh(['sellerOrder', 'events']));
        if ($action === 'decline_hub_transfer' ? $allowed !== 'accept_hub_transfer' : $allowed !== $action) {
            throw ValidationException::withMessages(['action' => 'This scan action is no longer available for the current shipment state.']);
        }

        if ($actorType === 'rider') {
            $rider = $this->riderFor($request);
            match ($action) {
                'accept_pickup' => $workflow->acceptPickup($sellerOrder, $rider),
                'confirm_pickup' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::PickedUp),
                'arrive_sorting' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::AtSortingCenter),
                'accept_delivery' => $workflow->acceptDelivery($sellerOrder, $rider),
                'begin_delivery', 'dispatch_hub' => $workflow->transition($sellerOrder, $request->user(), $action === 'dispatch_hub' ? OrderStatus::InTransitToHub : OrderStatus::OutForDelivery, $action === 'dispatch_hub' ? ['workflow_event_note' => 'Hub transfer dispatch verified by Rider Shipment Scanner mode hub_transfer_dispatch.'] : []),
                'accept_hub_transfer' => $workflow->acceptHubTransfer($sellerOrder, $rider),
                'decline_hub_transfer' => $workflow->declineHubTransfer($sellerOrder, $rider, $data['transfer_decline_reason']),
                default => throw ValidationException::withMessages(['action' => 'This scan action is not available to riders.']),
            };
            $request->session()->forget(self::riderVerificationKey($rider->id));
            $request->session()->forget(self::verifiedResultKey('rider', $rider->id));
        } else {
            $provider = $this->providerFor($request);
            if ($action === 'receive_sorting') {
                $workflow->transition($sellerOrder, $request->user(), OrderStatus::AtSortingCenter);
            } elseif ($action === 'mark_sorted') {
                $zone = $provider->sortingZones()->with('hub')->where('is_active', true)->find($data['sorting_zone_id']);
                if (! $zone || ! $zone->hub || ! $zone->hub->is_active || (int) $zone->hub->logistics_provider_id !== (int) $provider->id) {
                    throw ValidationException::withMessages(['sorting_zone_id' => 'Select an active provider sorting zone linked to an active destination hub.']);
                }
                $resolution = app(SortingZoneResolver::class)->resolve($provider, $sellerOrder->order);
                $recommended = $resolution['zone'];
                $overridden = ! $recommended || (int) $recommended->id !== (int) $zone->id;
                if ($overridden && empty($data['override_reason'])) {
                    throw ValidationException::withMessages(['override_reason' => 'Select a reason when changing the recommended sorting zone.']);
                }
                $label = $zone->zone_code.' — '.$zone->name;
                $eventNote = $overridden
                    ? 'Sorting zone overridden by '.$request->user()->full_name.': '.($recommended ? $recommended->zone_code.' — '.$recommended->name : 'no automatic match').' → '.$label.'. Reason: '.$data['override_reason'].(filled($data['override_note'] ?? null) ? ' — '.trim($data['override_note']) : '').'.'
                    : 'Automatically matched sorting zone '.$label.($zone->hub ? ' at '.$zone->hub->name : '').' from structured delivery destination codes.';
                $workflow->transition($sellerOrder, $request->user(), OrderStatus::Sorted, [
                    'sorting_area' => $label,
                    'sorting_zone_id' => $zone->id,
                    'logistics_hub_id' => $zone->logistics_hub_id,
                    'destination_hub_id' => $zone->logistics_hub_id,
                    'origin_hub_id' => $provider->main_sorting_hub_id,
                    'sorting_event_note' => $eventNote,
                ]);
            } elseif ($action === 'dispatch_hub') {
                $workflow->transition($sellerOrder, $request->user(), OrderStatus::InTransitToHub, ['workflow_event_note' => 'Hub transfer dispatch verified by Logistics Shipment Scanner.']);
            } elseif ($action === 'arrive_hub') {
                $workflow->transition($sellerOrder, $request->user(), OrderStatus::AtDestinationHub, ['workflow_event_note' => 'Destination hub arrival verified by Logistics Shipment Scanner at hub '.$shipment->destination_hub_id.'.']);
            } else {
                throw ValidationException::withMessages(['action' => 'This scan action is not available to logistics.']);
            }

            if (in_array($action, ['mark_sorted', 'receive_sorting'], true)) {
                $request->session()->forget(self::sortingVerificationKey($provider->id));
                $request->session()->forget(self::verifiedResultKey('logistics', $provider->id));
            }
        }

        return $this->redirectToScanner($actorType)->with('success', 'Shipment scan action recorded.');
    }

    private function scannerView(string $actorType, ?Shipment $shipment = null, string $prefillTrackingCode = '', ?string $mode = null): View
    {
        $allowedAction = $shipment ? $this->allowedAction(request(), $shipment) : null;
        $provider = $actorType === 'logistics' ? $this->providerFor(request()) : null;
        $sortingResolution = null;
        $sortingZones = collect();
        if ($provider && $shipment && $allowedAction === 'mark_sorted') {
            $sortingResolution = app(SortingZoneResolver::class)->resolve($provider, $shipment->sellerOrder->order);
            $sortingZones = $provider->sortingZones()->with('hub')->where('is_active', true)->orderBy('priority')->orderBy('zone_code')->get()
                ->filter(fn (LogisticsSortingZone $zone) => $zone->hub && $zone->hub->is_active && (int) $zone->hub->logistics_provider_id === (int) $provider->id)
                ->values();
        }
        return view('shipments.scanner', [
            'actorType' => $actorType,
            'shipment' => $shipment,
            'shipmentVerified' => $shipment !== null,
            'allowedAction' => $allowedAction,
            'prefillTrackingCode' => $prefillTrackingCode,
            'sortingResolution' => $sortingResolution,
            'sortingZones' => $sortingZones,
            'scannerMode' => $mode,
        ]);
    }

    public static function verifiedResultKey(string $actorType, int $scopeId): string
    {
        return 'shipment_scanner.'.$actorType.'_verified_result.'.$scopeId;
    }

    public static function riderVerificationKey(int $riderId): string
    {
        return 'shipment_scanner.rider_verified_shipment.'.$riderId;
    }

    private function verifiedShipmentFromSession(Request $request, string $actorType): ?Shipment
    {
        $scopeId = $actorType === 'logistics'
            ? $this->providerFor($request)->id
            : $this->riderFor($request)->id;
        $key = self::verifiedResultKey($actorType, $scopeId);
        $result = $request->session()->get($key);

        if (! is_array($result)
            || ! isset($result['shipment_id'], $result['verified_at'])
            || ! is_numeric($result['verified_at'])
            || now()->timestamp - (int) $result['verified_at'] > 900) {
            $request->session()->forget($key);

            return null;
        }

        $shipment = Shipment::query()
            ->with(['sellerOrder.order', 'sellerOrder.seller', 'provider', 'rider.user', 'pickupRider.user', 'events'])
            ->find($result['shipment_id']);

        if (! $shipment) {
            $request->session()->forget($key);

            return null;
        }

        try {
            $this->authorizeShipment($request, $shipment, $actorType);
        } catch (AuthorizationException) {
            $request->session()->forget($key);

            return null;
        }

        return $shipment;
    }

    private function verifiedMode(Request $request, string $actorType): ?string
    {
        $scopeId = $actorType === 'logistics' ? $this->providerFor($request)->id : $this->riderFor($request)->id;
        $result = $request->session()->get(self::verifiedResultKey($actorType, $scopeId));
        return is_array($result) && is_numeric($result['verified_at'] ?? null)
            && now()->timestamp - (int) $result['verified_at'] <= 900
                ? ($result['mode'] ?? null)
                : null;
    }

    public static function sortingVerificationKey(int $providerId): string
    {
        return 'shipment_scanner.logistics_sort_verified.'.$providerId;
    }

    private function allowedAction(Request $request, Shipment $shipment): ?string
    {
        $status = $shipment->sellerOrder->workflow_status;
        $status = $status instanceof OrderStatus ? $status : OrderStatus::tryFrom(strtoupper((string) $status));

        if ($request->user()->hasRole('logistics')) {
            $provider = $this->providerFor($request);
            $mode = $this->verifiedMode($request, 'logistics');
            $verified = $request->session()->get(self::verifiedResultKey('logistics', $provider->id));
            if (! is_array($verified) || (int) ($verified['shipment_id'] ?? 0) !== (int) $shipment->id || now()->timestamp - (int) ($verified['verified_at'] ?? 0) > 900) return null;
            return match ([$status, $mode]) {
                [OrderStatus::PickedUp, 'sorting_center_arrival'] => 'receive_sorting',
                [OrderStatus::AtSortingCenter, 'sort'] => (int) $request->session()->get(self::sortingVerificationKey($provider->id)) === (int) $shipment->id ? 'mark_sorted' : null,
                [OrderStatus::Sorted, 'hub_transfer_dispatch'] => $this->logisticsTransferReady($shipment) ? 'dispatch_hub' : null,
                [OrderStatus::InTransitToHub, 'hub_arrival'] => ((int) ($verified['hub_id'] ?? 0) === (int) $shipment->destination_hub_id ? 'arrive_hub' : null),
                default => null,
            };
        }

        $rider = $this->riderFor($request);
        $mode = $this->verifiedMode($request, 'rider');
        if ($status === OrderStatus::Sorted && $shipment->assignments()->where('assignment_type', 'HUB_TRANSFER')->where('rider_id', $rider->id)->active()->exists()) {
            return $mode === 'hub_transfer_dispatch' ? ($this->riderHasAcceptedTransfer($shipment, $rider) ? 'dispatch_hub' : 'accept_hub_transfer') : null;
        }

        if ($shipment->pickup_rider_id === $rider->id) {
            return match ($status) {
                OrderStatus::ReadyForPickup => $mode === 'pickup' ? ($shipment->pickup_accepted_at ? 'confirm_pickup' : 'accept_pickup') : null,
                OrderStatus::PickedUp => $mode === 'sorting_center_arrival' ? 'arrive_sorting' : null,
                default => null,
            };
        }

        if ($shipment->rider_id === $rider->id) {
            return match ($status) {
                OrderStatus::AssignedToRider => $mode === 'last_mile_dispatch' ? ($shipment->delivery_accepted_at ? 'begin_delivery' : 'accept_delivery') : null,
                default => null,
            };
        }

        return null;
    }

    private function authorizeShipment(Request $request, Shipment $shipment, string $actorType): void
    {
        if ($actorType === 'logistics') {
            $provider = $this->providerFor($request);
            if ($shipment->logistics_provider_id !== $provider->id) {
                throw new AuthorizationException('This shipment belongs to another logistics provider.');
            }

            return;
        }

        $rider = $this->riderFor($request);
        $transferAssigned = $shipment->assignments()->where('assignment_type', 'HUB_TRANSFER')->active()->where('rider_id', $rider->id)->exists();
        if (! in_array($rider->id, [$shipment->pickup_rider_id, $shipment->rider_id], true) && ! $transferAssigned) {
            throw new AuthorizationException('This shipment is not assigned to you.');
        }
    }

    private function providerFor(Request $request): LogisticsProvider
    {
        $provider = $request->user()->logisticsProvider()->where('status', 'approved')->first();
        abort_unless($provider, 403, 'No approved logistics provider is available.');

        return $provider;
    }

    private function riderFor(Request $request): Rider
    {
        $rider = $request->user()->rider()->with(['user', 'logisticsProvider', 'logisticsHub'])->first();
        abort_unless($rider && $rider->isOperationallyEligible(), 403, 'No active rider profile is available.');

        return $rider;
    }

    private function redirectToScanner(string $actorType): RedirectResponse
    {
        return match ($actorType) {
            'logistics' => redirect()->route('logistics.shipment-scanner'),
            'rider' => redirect()->route('rider.shipment-scanner'),
            default => abort(404),
        };
    }

    private function riderHasAcceptedTransfer(Shipment $shipment, Rider $rider): bool
    {
        return $shipment->assignments()->where('assignment_type', 'HUB_TRANSFER')->where('status', 'ACCEPTED')->where('rider_id', $rider->id)->exists();
    }

    private function logisticsTransferReady(Shipment $shipment): bool
    {
        $transfer = $shipment->assignments()->where('assignment_type', 'HUB_TRANSFER')->active()->first();
        if (! $transfer) return false;
        return $transfer->rider_id
            ? $transfer->status === 'ACCEPTED'
            : (filled($transfer->vehicle_reference) || filled($transfer->batch_reference));
    }

}
