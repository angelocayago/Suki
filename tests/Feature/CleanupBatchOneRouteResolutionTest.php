<?php

namespace Tests\Feature;

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

    public function test_legacy_admin_urls_keep_their_compatibility_route_resolution(): void
    {
        $expectations = [
            ['GET', '/admin', 'admin.dashboard'],
            ['GET', '/admin/buyers', 'admin.buyers'],
            ['POST', '/admin/buyers/123/approve', 'admin.buyers.approve'],
            ['POST', '/admin/buyers/123/reject', 'admin.buyers.reject'],
        ];

        foreach ($expectations as [$method, $uri, $name]) {
            $route = $this->resolve($method, $uri);

            $this->assertSame($name, $route->getName());
            $this->assertSame('Closure', $route->getActionName());
        }
    }

    private function resolve(string $method, string $uri): Route
    {
        return app('router')->getRoutes()->match(Request::create($uri, $method));
    }
}
