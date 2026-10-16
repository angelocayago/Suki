<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\PickupRequest;
use App\Services\PickupAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class PickupOfferController extends Controller
{
    public function accept(PickupRequest $pickupRequest): RedirectResponse
    {
        $rider = Auth::user();

        abort_unless($rider && $rider->isRider(), 403);

        abort_unless(
            (int) $pickupRequest->rider_id === (int) $rider->id,
            403
        );

        try {
            PickupAcceptanceService::accept($pickupRequest, $rider);

            return redirect()
                ->route('rider.pickups')
                ->with('success', 'Pickup accepted. You can now collect the parcel using its QR code.');
        } catch (RuntimeException $e) {
            return redirect()
                ->route('rider.pickups')
                ->with('error', $e->getMessage());
        }
    }

    public function decline(PickupRequest $pickupRequest): RedirectResponse
    {
        $rider = Auth::user();

        abort_unless($rider && $rider->isRider(), 403);

        abort_unless(
            (int) $pickupRequest->rider_id === (int) $rider->id,
            403
        );

        try {
            PickupAcceptanceService::decline($pickupRequest, $rider);

            return redirect()
                ->route('rider.pickups')
                ->with('success', 'Pickup declined. The system will offer it to another eligible rider.');
        } catch (RuntimeException $e) {
            return redirect()
                ->route('rider.pickups')
                ->with('error', $e->getMessage());
        }
    }
}
