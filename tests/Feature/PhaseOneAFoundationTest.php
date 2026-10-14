<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseOneAFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_example_environment_is_aligned_to_mysql_without_credentials(): void
    {
        $environment = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('DB_CONNECTION=mysql', $environment);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $environment);
        $this->assertStringContainsString('DB_DATABASE=suki_shop', $environment);
        $this->assertSame('mysql', config('database.connections.mysql.driver'));
    }

    public function test_role_seeder_is_idempotent_and_preserves_both_role_systems(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        foreach (['admin', 'buyer', 'logistics', 'rider', 'seller', 'superadmin'] as $name) {
            $this->assertSame(1, Role::query()->where('name', $name)->count());
        }

        $user = User::factory()->create(['role' => 'seller']);
        $sellerRole = Role::query()->where('name', 'seller')->firstOrFail();
        $user->roles()->attach($sellerRole);

        $this->assertSame('seller', $user->fresh()->role);
        $this->assertTrue($user->fresh()->roles->contains($sellerRole));
    }

    public function test_default_seeder_only_builds_reference_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('roles', ['name' => 'buyer']);
        $this->assertDatabaseHas('categories', ['slug' => 'electronics']);
    }

    public function test_seller_has_an_authoritative_category_and_keeps_legacy_pivot_compatibility(): void
    {
        $user = User::factory()->create([
            'role' => 'seller',
            'password' => Hash::make('password'),
        ]);
        $assignedCategory = Category::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel',
            'position' => 1,
            'is_active' => true,
        ]);
        $legacyCategory = Category::query()->create([
            'name' => 'Legacy Category',
            'slug' => 'legacy-category',
            'position' => 2,
            'is_active' => true,
        ]);
        $seller = Seller::query()->create([
            'user_id' => $user->id,
            'name' => 'Phase One Seller',
            'slug' => 'phase-one-seller',
            'category_id' => $assignedCategory->id,
            'seller_type' => 'business',
            'tin' => '123-456-789-000',
            'status' => 'pending',
        ]);
        $seller->allowedCategories()->attach($legacyCategory);

        $this->assertTrue($seller->category->is($assignedCategory));
        $this->assertTrue($assignedCategory->assignedSellers->contains($seller));
        $this->assertTrue($seller->allowedCategories->contains($legacyCategory));
    }

    public function test_seller_documents_use_private_storage_metadata_and_relationships(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $seller = Seller::query()->create([
            'user_id' => $user->id,
            'name' => 'Document Seller',
            'slug' => 'document-seller',
            'status' => 'pending',
        ]);
        $document = SellerDocument::query()->create([
            'seller_id' => $seller->id,
            'uploaded_by' => $user->id,
            'document_type' => 'business_permit',
            'disk' => config('seller_documents.disk'),
            'path' => 'seller/'.$seller->id.'/generated-permit.pdf',
            'original_name' => 'permit.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => str_repeat('a', 64),
        ]);

        $this->assertTrue(Schema::hasColumns('sellers', ['category_id', 'seller_type', 'tin']));
        $this->assertTrue(Schema::hasTable('seller_documents'));
        $this->assertSame('seller_documents', config('seller_documents.disk'));
        $this->assertSame('private', config('filesystems.disks.seller_documents.visibility'));
        $this->assertTrue($document->seller->is($seller));
        $this->assertTrue($seller->documents->contains($document));
        $this->assertTrue($document->uploader->is($user));
    }
}
