<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Shipment;
use App\Services\OrderWorkflowService;
use App\Services\ShipmentQrService;
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

        return $this->scannerView('logistics', null, is_string($trackingCode) ? substr(trim($trackingCode), 0, 100) : '');
    }

    public function rider(Request $request): View
    {
        return $this->scannerView('rider');
    }

    public function resolve(Request $request, ShipmentQrService $qr, string $actorType): View
    {
        $data = $request->validate([
            'payload' => ['nullable', 'string', 'max:512', 'required_without:tracking_code'],
            'tracking_code' => ['nullable', 'string', 'max:100', 'required_without:payload'],
        ]);

        $enteredValue = (string) ($data['payload'] ?? $data['tracking_code'] ?? '');
        $trackingCode = $qr->isQrPayload($enteredValue)
            ? $qr->trackingCodeFromPayload($enteredValue)
            : $qr->normalizeTrackingCode($enteredValue);

        if (! $trackingCode) {
            throw ValidationException::withMessages(['payload' => 'The shipment QR code is invalid or has been altered.']);
        }

        $provider = $actorType === 'logistics' ? $this->providerFor($request) : null;
        if ($provider) {
            $request->session()->forget(self::sortingVerificationKey($provider->id));
        }

        $shipment = Shipment::query()
            ->with(['sellerOrder.order', 'sellerOrder.seller', 'provider', 'rider.user', 'pickupRider.user', 'events'])
            ->where('tracking_code', $trackingCode)
            ->firstOrFail();

        $this->authorizeShipment($request, $shipment, $actorType);

        if ($provider && $shipment->sellerOrder->workflow_status === OrderStatus::AtSortingCenter) {
            $request->session()->put(self::sortingVerificationKey($provider->id), $shipment->id);
        }

        return $this->scannerView($actorType, $shipment);
    }

    public function action(
        Request $request,
        Shipment $shipment,
        OrderWorkflowService $workflow,
        string $actorType
    ): RedirectResponse {
        $this->authorizeShipment($request, $shipment->loadMissing(['sellerOrder.order', 'events']), $actorType);
        $data = $request->validate([
            'action' => ['required', 'in:accept_pickup,confirm_pickup,arrive_sorting,receive_sorting,mark_sorted,accept_delivery,begin_delivery'],
            'sorting_area' => ['nullable', 'required_if:action,mark_sorted', 'string', 'max:100'],
        ]);

        $action = $data['action'];
        $sellerOrder = $shipment->sellerOrder;

        if ($action === 'mark_sorted') {
            $provider = $this->providerFor($request);
            if ((int) $request->session()->get(self::sortingVerificationKey($provider->id)) !== (int) $shipment->id) {
                throw ValidationException::withMessages(['action' => 'Scan and verify this exact parcel before confirming sorting.']);
            }
        }

        if ($action === 'receive_sorting' && $shipment->events()->where('status', 'RECEIVED_AT_SORTING_CENTER')->exists()) {
            return $this->redirectToScanner($actorType)->with('success', 'This parcel was already received at the sorting center.');
        }

        $allowed = $this->allowedAction($request, $shipment->fresh(['sellerOrder', 'events']));
        if ($allowed !== $action) {
            throw ValidationException::withMessages(['action' => 'This scan action is no longer available for the current shipment state.']);
        }

        if ($actorType === 'rider') {
            $rider = $this->riderFor($request);
            match ($action) {
                'accept_pickup' => $workflow->acceptPickup($sellerOrder, $rider),
                'confirm_pickup' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::PickedUp),
                'arrive_sorting' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::AtSortingCenter),
                'accept_delivery' => $workflow->acceptDelivery($sellerOrder, $rider),
                'begin_delivery' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::OutForDelivery),
                default => throw ValidationException::withMessages(['action' => 'This scan action is not available to riders.']),
            };
        } else {
            $provider = $this->providerFor($request);
            match ($action) {
                'receive_sorting' => $workflow->receiveAtSortingCenter($sellerOrder, $provider, $request->user()),
                'mark_sorted' => $workflow->transition($sellerOrder, $request->user(), OrderStatus::Sorted, ['sorting_area' => $data['sorting_area']]),
                default => throw ValidationException::withMessages(['action' => 'This scan action is not available to logistics.']),
            };

            if ($action === 'mark_sorted') {
                $request->session()->forget(self::sortingVerificationKey($provider->id));
            }
        }

        return $this->redirectToScanner($actorType)->with('success', 'Shipment scan action recorded.');
    }

    private function scannerView(string $actorType, ?Shipment $shipment = null, string $prefillTrackingCode = ''): View
    {
        return view('shipments.scanner', [
            'actorType' => $actorType,
            'shipment' => $shipment,
            'allowedAction' => $shipment ? $this->allowedAction(request(), $shipment) : null,
            'prefillTrackingCode' => $prefillTrackingCode,
        ]);
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
            if ($status !== OrderStatus::AtSortingCenter) {
                return null;
            }

            $provider = $this->providerFor($request);
            if ((int) $request->session()->get(self::sortingVerificationKey($provider->id)) !== (int) $shipment->id) {
                return null;
            }

            return $shipment->events->contains('status', 'RECEIVED_AT_SORTING_CENTER')
                ? 'mark_sorted'
                : 'receive_sorting';
        }

        $rider = $this->riderFor($request);
        if ($shipment->pickup_rider_id === $rider->id) {
            return match ($status) {
                OrderStatus::ReadyForPickup => $shipment->pickup_accepted_at ? 'confirm_pickup' : 'accept_pickup',
                OrderStatus::PickedUp => 'arrive_sorting',
                default => null,
            };
        }

        if ($shipment->rider_id === $rider->id) {
            return match ($status) {
                OrderStatus::AssignedToRider => $shipment->delivery_accepted_at ? 'begin_delivery' : 'accept_delivery',
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
        if (! in_array($rider->id, [$shipment->pickup_rider_id, $shipment->rider_id], true)) {
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
        return redirect()->route($actorType === 'logistics' ? 'logistics.shipment-scanner' : 'rider.shipment-scanner');
    }
}
