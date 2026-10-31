<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_categories_seed_an_empty_database_with_fifteen_roots_and_children(): void
    {
        $this->assertDatabaseCount('categories', 0);

        $this->seed(CategorySeeder::class);

        $this->assertSame(15, Category::query()->whereNull('parent_id')->count());
        $this->assertDatabaseHas('categories', ['name' => 'Electronics & Gadgets', 'parent_id' => null, 'slug' => 'electronics-gadgets']);
        $root = Category::query()->where('slug', 'electronics-gadgets')->sole();
        $this->assertDatabaseHas('categories', ['name' => 'Mobile Phones & Accessories', 'parent_id' => $root->id, 'slug' => 'electronics-gadgets--mobile-phones-accessories']);
    }

    public function test_seeder_is_idempotent_and_registration_only_lists_roots(): void
    {
        $this->seed(CategorySeeder::class);
        $firstCount = Category::count();
        $this->seed(CategorySeeder::class);

        $this->assertSame($firstCount, Category::count());
        $this->get(route('seller.register'))
            ->assertOk()
            ->assertSee('Electronics & Gadgets')
            ->assertDontSee('Mobile Phones & Accessories');
    }
}
