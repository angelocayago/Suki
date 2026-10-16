<?php

namespace App\Services;

use App\Models\SellerOrder;

class SellerPickupZoneService
{
    public static function resolve(SellerOrder $sellerOrder): ?string
    {
        $sellerOrder->loadMissing([
            'seller.owner',
            'seller.pickupAddress',
        ]);

        $address = $sellerOrder->seller?->pickupAddress;

        // Prefer the seller's configured pickup address.
        if ($address && filled($address->city)) {
            return self::normalize($address->city);
        }

        // Fall back to the seller owner's municipality.
        $owner = $sellerOrder->seller?->owner;

        $municipality = $owner?->municipality_name;

        return filled($municipality)
            ? self::normalize($municipality)
            : null;
    }

    public static function normalize(?string $zone): ?string
    {
        if (blank($zone)) {
            return null;
        }

        return mb_strtolower(trim($zone));
    }
}
