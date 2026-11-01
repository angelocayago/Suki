<?php

namespace App\Services;

use App\Models\Shipment;

class ShipmentQrService
{
    private const PREFIX = 'SUKI-SHIPMENT-V1';

    /**
     * The QR contains only a tracking reference plus an application-key HMAC.
     * Shipment state, actors, money, and addresses are always loaded server-side.
     */
    public function payload(Shipment $shipment): string
    {
        $trackingCode = strtoupper(trim($shipment->tracking_code));

        return implode('|', [self::PREFIX, $trackingCode, $this->signature($trackingCode)]);
    }

    public function trackingCodeFromPayload(string $payload): ?string
    {
        $parts = explode('|', trim($payload));

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            return null;
        }

        $trackingCode = $this->normalizeTrackingCode($parts[1]);

        if (! $trackingCode || ! hash_equals($this->signature($trackingCode), $parts[2])) {
            return null;
        }

        return $trackingCode;
    }

    public function isQrPayload(string $value): bool
    {
        return str_starts_with(trim($value), self::PREFIX.'|');
    }

    public function normalizeTrackingCode(string $trackingCode): ?string
    {
        $trackingCode = strtoupper(trim($trackingCode));

        return $trackingCode !== '' && preg_match('/^[A-Z0-9-]{3,100}$/', $trackingCode)
            ? $trackingCode
            : null;
    }

    private function signature(string $trackingCode): string
    {
        return hash_hmac('sha256', self::PREFIX.'|'.$trackingCode, (string) config('app.key'));
    }
}
