<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:complete-delivered', function () {
    $count = 0;
    \App\Models\SellerOrder::where('workflow_status', 'DELIVERED')
        ->where('delivered_at', '<=', now()->subDays(3))
        ->whereHas('order', fn ($q) => $q->where('status', '!=', 'CANCELLED'))
        ->chunkById(100, function ($orders) use (&$count) {
            foreach ($orders as $order) {
                try {
                    app(\App\Services\OrderWorkflowService::class)->transition($order, null, \App\Enums\OrderStatus::Completed);
                    $count++;
                } catch (\Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $e) {
                    // A concurrent receipt/transition already changed eligibility.
                }
            }
        });
    $this->info("Completed {$count} delivered seller orders.");
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
