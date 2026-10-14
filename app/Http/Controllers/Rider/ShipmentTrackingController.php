<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\ShipmentTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentTrackingController extends Controller
{
    public function store(Request $request, Shipment $shipment, ShipmentTrackingService $tracking): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $rider = $request->user()->rider()->where('is_active', true)->firstOrFail();
        $shipment = $tracking->update($shipment, $rider, (float) $data['latitude'], (float) $data['longitude']);

        return response()->json([
            'latitude' => (float) $shipment->current_latitude,
            'longitude' => (float) $shipment->current_longitude,
            'updated_at' => $shipment->location_updated_at?->toIso8601String(),
        ]);
    }
}
