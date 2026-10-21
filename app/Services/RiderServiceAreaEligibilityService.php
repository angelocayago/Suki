<?php

namespace App\Services;

use App\Models\Address;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\RiderServiceArea;
use App\Models\SellerOrder;
use Illuminate\Validation\ValidationException;

class RiderServiceAreaEligibilityService
{
    public function pickup(SellerOrder $sellerOrder, LogisticsProvider $provider, Rider $rider): void
    {
        $address = $sellerOrder->seller?->pickupAddress;
        $this->assertEligible($provider, $rider, $address, 'pickup');
    }

    public function delivery(SellerOrder $sellerOrder, LogisticsProvider $provider, Rider $rider): void
    {
        $order = $sellerOrder->order;
        $target = $order ? (object) [
            'region_code' => $order->region_code,
            'province_code' => $order->province_code,
            'city_municipality_code' => $order->city_municipality_code,
            'barangay_code' => $order->barangay_code,
        ] : null;
        $this->assertEligible($provider, $rider, $target, 'delivery');
    }

    private function assertEligible(LogisticsProvider $provider, Rider $rider, ?object $target, string $kind): void
    {
        if (! $target?->region_code || ! $target?->city_municipality_code || ! $target?->barangay_code) {
            throw ValidationException::withMessages(['rider_id' => "Verified {$kind} service-area matching is unavailable for this legacy address."]);
        }
        $eligible = RiderServiceArea::query()
            ->where('rider_id', $rider->id)
            ->where('logistics_provider_id', $provider->id)
            ->where('is_active', true)
            ->where('region_code', $target->region_code)
            ->where('city_municipality_code', $target->city_municipality_code)
            ->where(function ($query) use ($target) {
                $query->whereNull('barangay_code')->orWhere('barangay_code', $target->barangay_code);
            })->exists();
        if (! $eligible) {
            throw ValidationException::withMessages(['rider_id' => "The selected rider is not authorized for this {$kind} service area."]);
        }
    }
}
