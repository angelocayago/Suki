<?php

namespace App\Services;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\SellerOrder;
use Illuminate\Validation\ValidationException;

class LogisticsHubEligibilityService
{
    public function pickup(SellerOrder $sellerOrder, LogisticsProvider $provider, Rider $rider): void
    {
        $this->assertCovered($provider, $rider, $sellerOrder->seller?->pickupAddress?->barangay_code, 'pickup');
    }

    public function delivery(SellerOrder $sellerOrder, LogisticsProvider $provider, Rider $rider): void
    {
        $this->assertCovered($provider, $rider, $sellerOrder->order?->barangay_code, 'delivery');
        $shipment = $sellerOrder->shipment;
        $destinationHub = $shipment?->destinationHub;
        if (! $destinationHub || ! $destinationHub->is_active || (int) $destinationHub->logistics_provider_id !== (int) $provider->id || (int) $rider->logistics_hub_id !== (int) $destinationHub->id) {
            throw ValidationException::withMessages(['rider_id' => 'The selected rider must be assigned to this shipment’s active destination barangay hub.']);
        }
        if ($shipment?->sorting_zone_id) {
            $zone = $provider->sortingZones()->with('hub')->where('is_active', true)->find($shipment->sorting_zone_id);
            if (! $zone || ! app(SortingZoneResolver::class)->covers($zone, $sellerOrder->order)) {
                throw ValidationException::withMessages(['rider_id' => 'The shipment sorting zone does not cover this delivery destination.']);
            }
            if ((int) $zone->logistics_hub_id !== (int) $destinationHub->id || (int) $zone->logistics_hub_id !== (int) $rider->logistics_hub_id) {
                throw ValidationException::withMessages(['rider_id' => 'The selected rider must be assigned to this sorting zone hub.']);
            }
        }
    }

    private function assertCovered(LogisticsProvider $provider, Rider $rider, ?string $barangayCode, string $kind): void
    {
        $rider->load('logisticsHub');
        $hub = $rider->logisticsHub;
        if (! $hub || ! $hub->is_active || $hub->logistics_provider_id !== $provider->id || ! $barangayCode || $hub->barangay_code !== $barangayCode) {
            throw ValidationException::withMessages(['rider_id' => "The selected rider's hub does not cover this {$kind} barangay."]);
        }
    }
}
