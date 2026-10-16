<?php

namespace App\Services;

use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PickupOfferService
{
    public static function offerNext(Shipment $shipment): ?PickupRequest
    {
        return DB::transaction(function () use ($shipment) {
            $shipment = Shipment::query()
                ->whereKey($shipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Expire unanswered offers even if the shipment is no longer eligible.
            PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->where('status', 'offered')
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => 'expired',
                    'responded_at' => now(),
                ]);

            if (
                $shipment->status !== 'pending' ||
                !$shipment->seller_order_id ||
                !$shipment->logistic_id ||
                !$shipment->hub_id ||
                $shipment->pickup_rider_id !== null ||
                $shipment->rider_id !== null ||
                $shipment->received_by_sorting_center ||
                $shipment->picked_up_at !== null
            ) {
                return null;
            }

            $sellerOrder = $shipment->sellerOrder;

            if (!$sellerOrder || $sellerOrder->status !== 'ready_for_pickup') {
                return null;
            }

            // Seller location, never buyer delivery zone.
            $pickupZone = SellerPickupZoneService::resolve($sellerOrder);

            if (!$pickupZone) {
                return null;
            }

            $active = PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->where('status', 'offered')
                ->where('expires_at', '>', now())
                ->first();

            if ($active) {
                return $active;
            }

            $previousRiderIds = PickupRequest::query()
                ->where('shipment_id', $shipment->id)
                ->pluck('rider_id');

            $rider = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'rider');
                })
                ->where('status', User::STATUS_ACTIVE)
                ->where('logistic_status', 'approved')
                ->where('logistic_id', $shipment->logistic_id)
                ->where('hub_id', $shipment->hub_id)
                ->where('availability_status', 'available')
                ->where('max_capacity', '>', 0)
                ->whereColumn('current_load', '<', 'max_capacity')
                ->whereNotIn('id', $previousRiderIds)
                ->whereRaw(
                    'LOWER(TRIM(assigned_zone)) = ?',
                    [$pickupZone]
                )
                ->orderBy('current_load')
                ->orderBy('id')
                ->first();

            if (!$rider) {
                // No eligible rider: logistics must review dispatch.
                return null;
            }


            $offer = PickupRequest::create([
                'shipment_id' => $shipment->id,
                'rider_id' => $rider->id,
                'status' => 'offered',
                'offered_at' => now(),
                'expires_at' => now()->addMinutes(10),
            ]);

            \App\Models\Notification::create([
                'user_id' => $rider->id,
                'title' => 'New Seller Pickup Request',
                'message' => 'You have a new pickup request in '
                    . $pickupZone
                    . '. Please accept or decline within 10 minutes.',
                'type' => 'order',
                'link' => route('rider.pickups'),
            ]);

            return $offer;
        });
    }
}
