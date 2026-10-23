<?php

namespace App\Observers;

use App\Models\Shipment;

class ShipmentRiderWalletObserver
{
    public function saved(Shipment $shipment): void
    {
        // Intentionally no wallet credit here.
        //
        // BiliHub's final rule is:
        // Delivered -> Buyer Confirm Received (manual or 3-day automatic)
        // -> Rider earning credited.
        //
        // Rider credit is handled by OrderReceiptObserver / auto-confirm service.
    }
}
