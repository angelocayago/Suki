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
