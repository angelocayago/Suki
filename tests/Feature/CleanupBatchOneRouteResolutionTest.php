<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\BuyerManagementController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Logistics\FulfillmentController as LogisticsFulfillmentController;
use App\Http\Controllers\Rider\FulfillmentController as RiderFulfillmentController;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class CleanupBatchOneRouteResolutionTest extends TestCase
{
    public function test_rider_and_logistics_urls_resolve_to_the_database_fulfillment_controllers(): void
    {
        $expectations = [
            ['GET', '/rider/dashboard', 'rider.dashboard', RiderFulfillmentController::class.'@dashboard', 'role:rider'],
            ['GET', '/rider/deliveries', 'rider.deliveries', RiderFulfillmentController::class.'@deliveries', 'role:rider'],
            ['GET', '/rider/history', 'rider.history', RiderFulfillmentController::class.'@history', 'role:rider'],
            ['GET', '/logistics', 'logistics.dashboard', LogisticsFulfillmentController::class.'@dashboard', 'role:logistics'],
            ['GET', '/logistics/parcels', 'logistics.parcels', LogisticsFulfillmentController::class.'@parcels', 'role:logistics'],
            ['GET', '/logistics/sorting', 'logistics.sorting', LogisticsFulfillmentController::class.'@sorting', 'role:logistics'],
            ['GET', '/logistics/assignments', 'logistics.assignments', LogisticsFulfillmentController::class.'@assignments', 'role:logistics'],
            ['GET', '/logistics/shipments', 'logistics.shipments', LogisticsFulfillmentController::class.'@parcels', 'role:logistics'],
        ];

        foreach ($expectations as [$method, $uri, $name, $action, $roleMiddleware]) {
            $route = $this->resolve($method, $uri);

            $this->assertSame($name, $route->getName());
            $this->assertSame($action, $route->getActionName());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('not_suspended', $route->gatherMiddleware());
            $this->assertContains('approved', $route->gatherMiddleware());
            $this->assertContains($roleMiddleware, $route->gatherMiddleware());
        }
    }

    public function test_admin_urls_are_canonical_controller_routes_and_legacy_urls_remain_compatible(): void
    {
        $expectations = [
            ['GET', '/admin', 'admin.dashboard', AdminDashboardController::class.'@index'],
            ['GET', '/admin/buyers', 'admin.buyers', BuyerManagementController::class.'@index'],
            ['GET', '/superadmin', 'superadmin.dashboard', AdminDashboardController::class.'@index'],
            ['GET', '/superadmin/buyers', 'superadmin.buyers', BuyerManagementController::class.'@index'],
        ];

        foreach ($expectations as [$method, $uri, $name, $action]) {
            $route = $this->resolve($method, $uri);

            $this->assertSame($name, $route->getName());
            $this->assertSame($action, $route->getActionName());
            $this->assertContains('role:admin,superadmin', $route->gatherMiddleware());
        }

        $this->assertSame(
            'admin.buyers.approve',
            $this->resolve('POST', '/admin/buyers/123/approve')->getName()
        );
    }

    private function resolve(string $method, string $uri): Route
    {
        return app('router')->getRoutes()->match(Request::create($uri, $method));
    }
}
