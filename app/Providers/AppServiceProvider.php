<?php

namespace App\Providers;

use App\Models\SellerVoucher;
use App\Observers\SellerVoucherNotificationObserver;
use App\Support\ChatImageWrites;
use App\Support\SellerMessaging;
use App\Support\SellerNotifications;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(ChatImageWrites::class);
        Event::listen(TransactionCommitted::class,
            fn ($event) => app(ChatImageWrites::class)->committed($event->connection));
        Event::listen(TransactionRolledBack::class,
            fn ($event) => app(ChatImageWrites::class)->rolledBack($event->connection));
        SellerVoucher::observe(SellerVoucherNotificationObserver::class);
        View::composer('layouts.seller', function ($view) {
            $view->with('sellerNotificationSummary', SellerNotifications::shell());
            $view->with('sellerMessageSummary', SellerMessaging::shell());
        });
    }
}
