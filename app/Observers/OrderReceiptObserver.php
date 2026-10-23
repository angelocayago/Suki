<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderReceiptFinalizationService;
use Illuminate\Support\Facades\Schema;

class OrderReceiptObserver
{
    public function saved(Order $order): void
    {
        if (
            !Schema::hasColumn('orders', 'confirmed_received_at')
            || !$order->confirmed_received_at
        ) {
            return;
        }

        // This observer lets the existing manual "Confirm Received" action
        // trigger rider wallet credit and seller settlement retry without
        // requiring the old controller flow to know about the new wallet.
        app(OrderReceiptFinalizationService::class)->creditRiders($order);
        app(OrderReceiptFinalizationService::class)->retrySellerSettlements($order);
    }
}
