<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ShipmentQrController extends Controller
{
    private function authorizeQrAccess(Shipment $shipment): void
    {
        $user = Auth::user();
        abort_unless($user, 401);
        $shipment->loadMissing([
            'sellerOrder.order',
            'sellerOrder.seller',
            'logistic',
        ]);
        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder?->order;
        abort_unless(
            $sellerOrder && $order,
            404,
            'Seller order for this shipment was not found.'
        );
        $isBuyer = (int) $order->user_id === (int) $user->id;
        $isSeller = $sellerOrder->seller
            && (int) $sellerOrder->seller->user_id === (int) $user->id;
        $isPickupRider = $shipment->pickup_rider_id !== null
            && (int) $shipment->pickup_rider_id === (int) $user->id;
        $isDeliveryRider = $shipment->rider_id !== null
            && (int) $shipment->rider_id === (int) $user->id;
        $isLogistic = $shipment->logistic
            && (int) $shipment->logistic->owner_user_id === (int) $user->id;
        $isAdmin = $user->isAdmin();
        abort_unless(
            $isBuyer ||
                $isSeller ||
                $isPickupRider ||
                $isDeliveryRider ||
                $isLogistic ||
                $isAdmin,
            403,
            'You are not authorized to access this shipment QR code.'
        );
    }

    public function image(Shipment $shipment)
    {
        $this->authorizeQrAccess($shipment);

        // Temporary local diagnostic for PHP 8.5 deprecation warnings.
        $previousReporting = error_reporting(
            error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED
        );

        try {
            $svg = (string) QrCode::format('svg')
                ->size(300)
                ->margin(1)
                ->errorCorrection('H')
                ->generate($shipment->qr_payload);
        } finally {
            error_reporting($previousReporting);
        }

        // Remove a leading UTF-8 BOM or whitespace.
        $svg = preg_replace('/^\xEF\xBB\xBF/', '', $svg);
        $svg = ltrim($svg);

        // Remove the XML declaration so leading output cannot
        // cause "XML declaration allowed only at the start" errors.
        $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function png(Shipment $shipment)
    {
        $this->authorizeQrAccess($shipment);
        if (!extension_loaded('gd')) {
            return $this->image($shipment);
        }
        try {
            $png = QrCode::format('png')
                ->size(400)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($shipment->qr_payload);
            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Throwable $e) {
            return $this->image($shipment);
        }
    }
    public function label(Shipment $shipment)
    {
        $shipment->loadMissing([
            'sellerOrder.order.user',
            'sellerOrder.items.product',
            'sellerOrder.seller.owner',
            'rider',
            'logistic',
        ]);
        $this->authorizeQrAccess($shipment);
        return view('shipments.qr-label', compact('shipment'));
    }
    public function scan(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
        ]);
        $rider = Auth::user();
        abort_unless($rider && $rider->isRider(), 403);
        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($shipment, $rider, $validated) {
            $shipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $shipment->load('sellerOrder.order', 'logistic');
            $sellerOrder = $shipment->sellerOrder;
            $order = $sellerOrder?->order;
            if (!$sellerOrder || !$order) {
                return ['error' => 'Shipment is not linked to a valid order.'];
            }
            if (
                $shipment->pickup_rider_id === null ||
                (int) $shipment->pickup_rider_id !== (int) $rider->id
            ) {
                abort(403, 'This parcel is not assigned to you for seller pickup.');
            }
            // Require an accepted offer for assignments made through
            // the new pickup-request workflow.
            $hasPickupRequests = \App\Models\PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->exists();
            if ($hasPickupRequests) {
                $hasAcceptedOffer = \App\Models\PickupRequest::query()
                    ->where('shipment_id', $shipment->id)
                    ->where('rider_id', $rider->id)
                    ->where('status', 'accepted')
                    ->exists();
                abort_unless(
                    $hasAcceptedOffer,
                    403,
                    'You must accept the pickup request before scanning this parcel.'
                );
            }
            if ($shipment->received_by_sorting_center) {
                return ['error' => 'This parcel has already been received by the sorting center.'];
            }
            if ($shipment->status !== 'assigned') {
                return ['error' => 'This parcel is not awaiting seller pickup.'];
            }
            $token = trim($validated['qr_token']);
            if (
                !hash_equals(strtoupper((string) $shipment->qr_token), strtoupper($token))
                && !hash_equals((string) $shipment->tracking_number, $token)
            ) {
                return ['error' => 'Invalid QR token for this shipment.'];
            }
            $shipment->update([
                'status' => 'in_transit',
                'scanned_at_seller' => now(),
                'scanned_by_rider_id' => $rider->id,
                'seller_scan_confirmed_at' => now(),
                'picked_up_at' => now(),
            ]);
            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'buyer_id' => $order->user_id,
                'seller_order_id' => $sellerOrder->id,
                'tracking_number' => $shipment->tracking_number,
                'logistic_owner_id' => $shipment->logistic?->owner_user_id,
            ];
        });
        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }
        \App\Models\Notification::create([
            'user_id' => $result['buyer_id'],
            'title' => 'Rider Picked Up Your Parcel',
            'type' => 'delivery',
            'message' => 'Parcel #' . $result['seller_order_id']
                . ' from order ' . $result['order_number']
                . ' has been picked up from the seller.',
            'link' => route('orders.show', $result['order_id']),
        ]);
        if ($result['logistic_owner_id']) {
            \App\Models\Notification::create([
                'user_id' => $result['logistic_owner_id'],
                'title' => 'Parcel Picked Up by Rider',
                'type' => 'shipment',
                'message' => 'Rider ' . $rider->name
                    . ' picked up shipment ' . $result['tracking_number']
                    . ' from the seller.',
                'link' => route('logistic.shipments.show', $shipment),
            ]);
        }
        return redirect()
            ->route('rider.orders.show', $result['order_id'])
            ->with('success', 'QR verified. Parcel picked up successfully.');
    }
    /**
     * Verify physical handover of a staged parcel to its final-mile rider.
     * Seller pickup and logistics inbound scanning remain separate actions.
     */
    public function scanHubPickup(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string', 'max:2048'],
        ]);

        $rider = $request->user();
        abort_unless($rider && $rider->isRider(), 403);

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($shipment, $rider, $validated) {
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $locked->rider_id !== null && (int) $locked->rider_id === (int) $rider->id,
                403,
                'This parcel is not assigned to you for final-mile delivery.'
            );

            if ($locked->hub_picked_up_at !== null) {
                return ['error' => 'Hub pickup has already been confirmed.'];
            }
            if (
                !$locked->received_by_sorting_center
                || $locked->sorting_status !== 'staged'
                || $locked->staged_at === null
                || !in_array($locked->status, ['assigned', 'staged', 'at_sorting_center'], true)
            ) {
                return ['error' => 'Parcel must be received, staged, and assigned before hub pickup.'];
            }
            if (!$locked->hub_id || !$locked->logistic_id) {
                return ['error' => 'Parcel has no valid logistics hub assignment.'];
            }
            $hub = $locked->hub;
            if (!$hub || (int) $hub->logistic_id !== (int) $locked->logistic_id) {
                return ['error' => 'Parcel hub does not belong to its logistics provider.'];
            }
            // Rider's logistics company must match the shipment's company.
            if (!$rider->logistic_id || (int) $rider->logistic_id !== (int) $locked->logistic_id) {
                abort(403, 'Rider does not belong to this logistics company.');
            }
            // Verify rider hub when one is recorded on the rider account.
            if ($rider->hub_id !== null && (int) $rider->hub_id !== (int) $locked->hub_id) {
                abort(403, 'Rider is assigned to a different hub.');
            }
            // Zone is checked against the rider's configured delivery zone when available.
            if ($rider->delivery_zone !== null && trim((string) $rider->delivery_zone) !== '') {
                if (mb_strtolower(trim((string) $rider->delivery_zone)) !== mb_strtolower(trim((string) $locked->delivery_zone))) {
                    abort(403, 'Rider delivery zone does not match this parcel.');
                }
            }

            $sellerOrder = $locked->sellerOrder;
            $order = $sellerOrder?->order;
            if (!$sellerOrder || !$order) {
                return ['error' => 'Shipment is not linked to a valid order.'];
            }

            $raw = trim((string) $validated['qr_token']);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                if (
                    !isset($decoded['q'], $decoded['t'], $decoded['so'])
                    || !is_scalar($decoded['q'])
                    || !is_scalar($decoded['t'])
                    || !is_scalar($decoded['so'])
                    || !hash_equals((string) $locked->tracking_number, (string) $decoded['t'])
                    || !hash_equals((string) $locked->seller_order_id, (string) $decoded['so'])
                ) {
                    return ['error' => 'QR code does not belong to this parcel.'];
                }
                $raw = trim((string) $decoded['q']);
            }
            if (
                !$locked->qr_token
                || !hash_equals(strtoupper((string) $locked->qr_token), strtoupper($raw))
            ) {
                return ['error' => 'Invalid QR token for this parcel.'];
            }

            $locked->status = 'picked_up';
            $locked->hub_picked_up_at = now();
            $locked->hub_pickup_rider_id = $rider->id;
            $locked->save();

            return [
                'buyer_id' => $order->user_id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tracking_number' => $locked->tracking_number,
            ];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        if ($result['buyer_id']) {
            \App\Models\Notification::create([
                'user_id' => $result['buyer_id'],
                'title' => 'Parcel Picked Up for Delivery',
                'type' => 'delivery',
                'message' => 'Shipment ' . $result['tracking_number']
                    . ' from order ' . $result['order_number']
                    . ' has been picked up from the sorting center for delivery.',
                'link' => route('orders.show', $result['order_id']),
            ]);
        }

        return redirect()->route('rider.pickups', ['status' => 'sorting_center'])
            ->with('success', 'Hub QR verified. Parcel picked up for delivery.');
    }

    public function scanAtHub(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'qr_token' => ['required', 'string'],
            'delivery_zone' => ['nullable', 'string', 'max:255'],
            'delivery_type' => ['nullable', 'in:standard,same_day,cod'],
        ]);
        $user = Auth::user();
        $shipment->loadMissing('logistic');
        $isLogistic = $shipment->logistic
            && (int) $user->id === (int) $shipment->logistic->owner_user_id;
        $isAdmin = $user->isAdmin();
        if (!$isLogistic && !$isAdmin) {
            abort(403, 'Only logistic owner or admin can scan at hub.');
        }
        try {
            \Illuminate\Support\Facades\DB::transaction(
                function () use ($shipment, $validated) {
                    $lockedShipment = Shipment::query()
                        ->whereKey($shipment->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    // Revalidate authorization and token against the locked shipment.
                    $lockedShipment->loadMissing('logistic');
                    $currentUser = Auth::user();
                    abort_unless($currentUser && (
                        $currentUser->isAdmin() ||
                        ($lockedShipment->logistic && (int) $lockedShipment->logistic->owner_user_id === (int) $currentUser->id)
                    ), 403);
                    $providedToken = trim((string) $validated['qr_token']);
                    $validQr = $lockedShipment->qr_token
                        && hash_equals(strtoupper((string) $lockedShipment->qr_token), strtoupper($providedToken));
                    $validTracking = hash_equals((string) $lockedShipment->tracking_number, $providedToken);
                    if (!$validQr && !$validTracking) {
                        throw new \RuntimeException('Invalid QR code for this shipment.');
                    }
                    if (!$lockedShipment->seller_order_id) {
                        throw new \RuntimeException(
                            'Shipment is not linked to a seller order.'
                        );
                    }
                    if (!$lockedShipment->received_by_sorting_center) {
                        throw new \RuntimeException(
                            'Receive this shipment at the sorting center first.'
                        );
                    }
                    if ($lockedShipment->sorting_status !== 'received') {
                        throw new \RuntimeException(
                            'Only received shipments can be scanned.'
                        );
                    }
                    if ($lockedShipment->rider_id !== null) {
                        throw new \RuntimeException(
                            'Shipment still has an active rider assignment.'
                        );
                    }
                    $lockedShipment->update([
                        'sorting_status' => 'scanned',
                        'scanned_at' => now(),
                        'delivery_zone' => $validated['delivery_zone']
                            ?? $lockedShipment->delivery_zone,
                        'delivery_type' => $validated['delivery_type']
                            ?? $lockedShipment->delivery_type,
                    ]);
                }
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with(
            'success',
            'Shipment QR verified and scanned at hub. Ready for sorting.'
        );
    }
    /**
     * Compatibility route for old order-based QR actions.
     * Resolve the parcel through SellerOrder instead of Order::shipment.
     */
    public function scanByOrder(Request $request, \App\Models\Order $order)
    {
        $order->loadMissing('sellerOrders.shipment');
        $shipments = $order->sellerOrders
            ->map(fn($sellerOrder) => $sellerOrder->shipment)
            ->filter()
            ->values();
        if ($shipments->isEmpty()) {
            return back()->with('error', 'No shipment found for this order.');
        }
        if ($shipments->count() > 1) {
            return back()->with('error', 'This order contains multiple parcels. Please scan the QR code for the specific parcel.');
        }
        return $this->scan($request, $shipments->first());
    }
}
