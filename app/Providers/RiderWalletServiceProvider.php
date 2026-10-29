<?php
namespace App\Providers;
use App\Models\Shipment;
use App\Observers\ShipmentRiderWalletObserver;
use Illuminate\Support\ServiceProvider;
class RiderWalletServiceProvider extends ServiceProvider {
    public function register(): void {}
    public function boot(): void { Shipment::observe(ShipmentRiderWalletObserver::class); }
}
