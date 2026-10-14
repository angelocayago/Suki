<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Rider;
use App\Models\Shipment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentTrackingService
{
    public function update(Shipment $shipment, Rider $rider, float $latitude, float $longitude): Shipment
    {
        return DB::transaction(function () use ($shipment, $rider, $latitude, $longitude) {
            $locked = Shipment::query()->with('sellerOrder.order')->lockForUpdate()->findOrFail($shipment->id);
            $status = $locked->workflow_status;

            if (! in_array($status, [OrderStatus::AssignedToRider, OrderStatus::OutForDelivery], true)) {
                throw ValidationException::withMessages(['shipment' => 'Live location is available only for an active final-mile delivery.']);
            }
            if ($locked->sellerOrder?->order?->status === 'CANCELLED') {
                throw ValidationException::withMessages(['shipment' => 'A cancelled order cannot publish rider location.']);
            }
            if ((int) $locked->rider_id !== (int) $rider->id) {
                throw new AuthorizationException('Only the assigned delivery rider may update this shipment location.');
            }

            $recent = $locked->location_updated_at && $locked->location_updated_at->gt(now()->subSeconds(10));
            $nearby = $locked->current_latitude !== null && $locked->current_longitude !== null
                && $this->distanceMeters((float) $locked->current_latitude, (float) $locked->current_longitude, $latitude, $longitude) < 25;

            if (! ($recent && $nearby)) {
                $locked->update([
                    'current_latitude' => $latitude,
                    'current_longitude' => $longitude,
                    'location_rider_id' => $rider->id,
                    'location_updated_at' => now(),
                ]);
            }

            return $locked->fresh();
        }, 3);
    }

    private function distanceMeters(float $aLat, float $aLng, float $bLat, float $bLng): float
    {
        $earth = 6371000;
        $lat = deg2rad($bLat - $aLat);
        $lng = deg2rad($bLng - $aLng);
        $h = sin($lat / 2) ** 2 + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($lng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($h)));
    }
}
