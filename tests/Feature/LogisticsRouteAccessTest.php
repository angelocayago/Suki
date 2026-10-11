<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LogisticsRouteAccessTest extends TestCase
{
    public function test_guests_are_redirected_from_logistics_routes(): void
    {
        $routes = [
            ['GET', '/logistics'],
            ['GET', '/logistics/sorting'],
            ['GET', '/logistics/assignments'],
            ['GET', '/logistics/monitoring'],
            ['GET', '/logistics/reports'],
            ['GET', '/logistics/parcels'],
            ['GET', '/logistics/shipments'],
            ['GET', '/logistics/riders'],
            ['GET', '/logistics/riders/0/review'],
            ['POST', '/logistics/orders/1/status'],
            ['POST', '/logistics/riders/0/approve'],
            ['POST', '/logistics/riders/0/disapprove'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->call($method, $uri)
                ->assertRedirect(route('login'));
        }
    }

    public function test_active_logistics_users_can_access_the_dashboard(): void
    {
        $user = new User([
            'role' => 'logistics',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('logistics.dashboard'))
            ->assertOk();
    }

    public function test_non_logistics_users_are_forbidden_from_the_dashboard(): void
    {
        $user = new User([
            'role' => 'buyer',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('logistics.dashboard'))
            ->assertForbidden();
    }

    public function test_guests_cannot_update_order_status(): void
    {
        $this->post('/orders/1/status', [
            'status' => 'completed',
        ])->assertRedirect(route('admin.login'));
    }
}