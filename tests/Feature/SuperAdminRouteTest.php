<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SuperAdminRouteTest extends TestCase
{
    public function test_superadmin_dashboard_route_is_registered(): void
    {
        $this->assertTrue(Route::has('superadmin.dashboard'));
    }

    public function test_guests_are_redirected_from_superadmin_dashboard(): void
    {
        $this->get(route('superadmin.dashboard'))
            ->assertRedirect(route('login'));
    }
}