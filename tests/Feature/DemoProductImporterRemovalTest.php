<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DemoProductImporterRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_dedicated_importer_command_and_admin_bulk_routes_are_absent(): void
    {
        $this->assertArrayNotHasKey('demo:products:import', Artisan::all());
        $this->assertNull(Route::getRoutes()->getByName('admin.products.demo.preview'));
        $this->assertNull(Route::getRoutes()->getByName('admin.products.demo.approve'));
        $this->assertNull(Route::getRoutes()->getByName('superadmin.products.demo.preview'));
        $this->assertNull(Route::getRoutes()->getByName('superadmin.products.demo.approve'));

        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());
        $this->assertFalse($uris->contains('admin/products/demo/lahat-meron'));
        $this->assertFalse($uris->contains('admin/products/demo/lahat-meron/approve'));
        $this->assertFalse($uris->contains('superadmin/products/demo/lahat-meron'));
        $this->assertFalse($uris->contains('superadmin/products/demo/lahat-meron/approve'));
    }

    public function test_retained_cleanup_and_presentation_commands_remain_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('catalog:cleanup-demo-content', $commands);
        $this->assertArrayHasKey('presentation:marketplace-data', $commands);
        $this->artisan('catalog:cleanup-demo-content')->assertSuccessful();
    }
}
