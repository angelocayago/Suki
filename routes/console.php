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

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
