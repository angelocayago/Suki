<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:complete-delivered', function () {
    $count = 0;
    $issues = 0;
    \App\Models\SellerOrder::where('workflow_status', 'DELIVERED')
        ->where('status', '!=', 'cancelled')
        ->whereHas('order', fn ($q) => $q->where('status', '!=', 'CANCELLED'))
        ->whereNull('delivered_at')->chunkById(100, function ($orders) use (&$issues) {
            foreach ($orders as $order) {
                $this->warn("Seller order {$order->id}: missing delivered_at; automatic completion skipped.");
                $issues++;
            }
        });
    \App\Models\SellerOrder::where('workflow_status', 'DELIVERED')
        ->where('status', '!=', 'cancelled')
        ->where('delivered_at', '<=', now()->subDays(3))
        ->whereHas('order', fn ($q) => $q->where('status', '!=', 'CANCELLED'))
        ->chunkById(100, function ($orders) use (&$count, &$issues) {
            foreach ($orders as $order) {
                try {
                    app(\App\Services\OrderWorkflowService::class)->transition($order, null, \App\Enums\OrderStatus::Completed);
                    $count++;
                } catch (\Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $e) {
                    if ($order->fresh()?->workflow_status === \App\Enums\OrderStatus::Delivered) {
                        $this->warn("Seller order {$order->id}: ".$e->getMessage());
                        $issues++;
                    }
                }
            }
        });
    $this->info("Completed {$count} delivered seller orders.");
    $legacyCount = 0;
    \App\Models\Order::where('status', 'DELIVERED')->whereDoesntHave('sellerOrders')
        ->chunkById(100, function ($orders) use (&$issues, &$legacyCount) {
            foreach ($orders as $order) {
                if (! $order->delivered_at) {
                    $this->warn("Order {$order->order_number}: missing delivered_at; automatic completion skipped.");
                    $issues++;
                } elseif ($order->delivered_at->lte(now()->subDays(3))) {
                    try {
                        app(\App\Services\OrderWorkflowService::class)->confirmReceipt($order, null);
                        $legacyCount++;
                    } catch (\Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $e) {
                        $this->warn("Order {$order->order_number}: ".$e->getMessage());
                        $issues++;
                    }
                }
            }
        });
    $this->info("Completed {$legacyCount} historical parent-only orders.");
    return $issues ? 1 : 0;
})->purpose('Complete seller orders still delivered three days after delivery');

Schedule::command('orders:complete-delivered')->hourly()->withoutOverlapping();

Artisan::command('notifications:retry', function () {
    $count = app(\App\Services\DomainNotificationService::class)->retry();
    $this->info("Delivered {$count} pending notifications.");
})->purpose('Retry due notification outbox entries');

Schedule::command('notifications:retry')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('vouchers:notify-followers', function () {
    app(\App\Services\ShopVoucherService::class)->notifyDue();
    $this->info('Checked active follower vouchers.');
})->purpose('Notify eligible followers once when a shop voucher becomes active');
Schedule::command('vouchers:notify-followers')->everyMinute()->withoutOverlapping();
