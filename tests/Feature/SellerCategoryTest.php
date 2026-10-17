<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        SellerCategories::populate();
    }

    protected function shop(string $category = 'Pet Supplies', string $status = 'approved'): Seller
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        return Seller::create(['user_id' => $owner->id, 'name' => 'Fixture store',
            'slug' => 'fixture-'.$owner->id, 'business_category' => $category, 'status' => $status])->load('owner');
    }

    protected function child(Seller $seller, int $offset = 0): Category
    {
        return SellerCategories::children($seller, SellerCategories::main($seller))[$offset];
    }

    protected function payload(Seller $seller, array $extra = []): array
    {
        return array_replace(['name' => 'Fixture listing', 'description' => 'Product details',
            'subcategory' => $this->child($seller)->id, 'price' => '25.00', 'stock' => 3, 'status' => 'active',
            'images' => [UploadedFile::fake()->createWithContent('product.png', file_get_contents(public_path('images/logo.png')))]], $extra);
    }

    protected function product(Seller $seller): Product
    {
        $this->actingAs($seller->owner)->post(route('seller.products.store'), $this->payload($seller))
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));

        return $seller->products()->latest('id')->firstOrFail();
    }

    protected function updatePayload(Product $product, array $extra = []): array
    {
        return array_replace(['name' => $product->name, 'description' => $product->description,
            'subcategory' => $product->category_id, 'price' => 25, 'stock' => 3,
            'sku' => $product->variants->first()->sku, 'status' => 'active'], $extra);
    }

    public function test_exact_specification_is_available_with_parent_scoped_similar_names(): void
    {
        $expected = ['Pet Supplies', 'Electronics and Gadgets', "Women's Apparel", "Men's Apparel", 'Kids and Baby',
            'Home and Garden', 'Sports and Outdoors', 'Health and Beauty', 'Books and Media', 'Food and Gourmet',
            'Automotive & Motorcycle Group', 'Furniture and Office Equipment', 'Jewelry and Watches', 'Office and School Supplies'];
        $this->assertSame($expected, array_keys(config('seller_categories')));
        $this->assertSame(14, Category::whereNull('parent_id')->count());
        $this->assertSame(98, Category::count());
        foreach (config('seller_categories') as $main => $children) {
            $record = Category::whereNull('parent_id')->where('name', $main)->firstOrFail();
            $this->assertSame($children, $record->children()->orderBy('position')->pluck('name')->all());
        }
        $sameName = Category::where('name', 'Shoes & Accessories')->get();
        $this->assertCount(2, $sameName);
        $this->assertNotSame($sameName[0]->parent_id, $sameName[1]->parent_id);
        $this->assertNotSame($sameName[0]->slug, $sameName[1]->slug);
    }

    public function test_reference_setup_is_idempotent_and_preserves_existing_records_and_relationships(): void
    {
        $seller = $this->shop();
        $product = $this->product($seller);
        $category = $product->category;
        $category->update(['name' => '  DOG Food & Treats  ', 'slug' => 'existing-custom-slug']);
        $legacy = Category::create(['name' => 'Legacy marketplace', 'slug' => 'legacy-marketplace']);
        $before = Category::orderBy('id')->get()->toArray();
        SellerCategories::populate();
        SellerCategories::populate();
        $this->assertSame($before, Category::orderBy('id')->get()->toArray());
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertTrue($legacy->fresh()->exists);
        $this->assertTrue(SellerCategories::children($seller, SellerCategories::main($seller))->contains('id', $category->id));
    }

    public function test_registration_has_fourteen_options_rejects_invalid_and_preserves_old_input(): void
    {
        $response = $this->get(route('seller.register'))->assertOk();
        foreach (array_keys(config('seller_categories')) as $name) {
            $response->assertSee('value="'.e($name).'"', false);
            $this->post(route('seller.register.submit'), ['business_category' => $name])
                ->assertSessionDoesntHaveErrors('business_category');
        }
        $this->from(route('seller.register'))->post(route('seller.register.submit'), ['business_category' => 'Unlisted'])
            ->assertSessionHasErrors('business_category')->assertSessionHasInput('business_category', 'Unlisted');
    }

    public function test_create_form_uses_real_assignment_and_only_permitted_children(): void
    {
        $seller = $this->shop('Home and Garden');
        $response = $this->actingAs($seller->owner)->withSession(['seller_profile' => ['business_category' => 'Pet Supplies']])
            ->get(route('seller.products.create'))->assertOk();
        $response->assertSee('readonly value="Home and Garden"', false)->assertDontSee('name="category"', false);
        foreach (SellerCategories::children($seller, SellerCategories::main($seller)) as $child) {
            $response->assertSee('value="'.$child->id.'"', false);
        }
        $response->assertDontSeeText('Dog Food & Treats');
    }

    public function test_valid_child_is_persisted_with_owner_and_forged_main_and_owner_are_ignored(): void
    {
        $seller = $this->shop();
        $other = $this->shop('Home and Garden');
        $this->actingAs($seller->owner)->post(route('seller.products.store'), $this->payload($seller, [
            'category' => 'Home and Garden', 'category_id' => SellerCategories::main($other)->id,
            'seller_id' => $other->id, 'user_id' => $other->user_id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::sole();
        $this->assertSame($seller->id, $product->seller_id);
        $this->assertSame($this->child($seller)->id, $product->category_id);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'price_minor' => 2500, 'stock' => 3]);
        $this->assertDatabaseCount('product_images', 1);
        $this->flushSession();
        $this->get(route('seller.products'))->assertOk()->assertSeeText('Fixture listing');
        $this->get(route('seller.products.edit', $product->id))->assertOk()->assertSeeText('Dog Food & Treats');
    }

    public function test_create_rejects_missing_unknown_inactive_and_cross_parent_children_before_upload(): void
    {
        $seller = $this->shop();
        $other = $this->shop('Home and Garden');
        $inactive = $this->child($seller, 1);
        $inactive->update(['is_active' => false]);
        foreach (['', 999999, $this->child($other)->id, $inactive->id, SellerCategories::main($seller)->id] as $id) {
            $this->actingAs($seller->owner)->post(route('seller.products.store'), $this->payload($seller, ['subcategory' => $id]))
                ->assertSessionHasErrors('subcategory');
            $this->assertDatabaseCount('products', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_edit_loads_existing_child_and_saves_another_permitted_child_preserving_other_data(): void
    {
        $seller = $this->shop();
        $product = $this->product($seller);
        $variant = $product->variants->first()->toArray();
        $images = $product->images->toArray();
        $this->get(route('seller.products.edit', $product->id))->assertOk()
            ->assertSee('value="'.$product->category_id.'" selected', false);
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, [
            'subcategory' => $this->child($seller, 1)->id, 'seller_id' => 9999, 'category' => 'Home and Garden',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->child($seller, 1)->id, $product->fresh()->category_id);
        $this->assertSame($seller->id, $product->fresh()->seller_id);
        $this->assertSame($variant, $product->fresh()->variants->first()->toArray());
        $this->assertSame($images, $product->fresh()->images->toArray());
    }

    public function test_invalid_edit_preserves_every_product_field_image_variant_and_file(): void
    {
        $seller = $this->shop();
        $product = $this->product($seller);
        $other = $this->shop('Home and Garden');
        $before = $product->fresh(['variants', 'images'])->toArray();
        $files = Storage::disk('public')->allFiles();
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, [
            'subcategory' => $this->child($other)->id, 'name' => 'Forged update', 'stock' => 100, 'price' => 1,
        ]))->assertSessionHasErrors('subcategory');
        $this->assertSame($before, $product->fresh(['variants', 'images'])->toArray());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_foreign_products_cannot_be_edited_updated_or_have_stock_changed(): void
    {
        $seller = $this->shop();
        $product = $this->product($seller);
        $other = $this->shop();
        $this->actingAs($other->owner)->get(route('seller.products.edit', $product->id))->assertNotFound();
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product))->assertNotFound();
        foreach (['increase', 'decrease'] as $action) {
            $this->post(route('seller.inventory.'.$action, $product->id))->assertNotFound();
        }
        $this->assertSame(3, $product->variants->first()->fresh()->stock);
        $this->get(route('seller.products'))->assertDontSeeText('Fixture listing');
    }

    public function test_unsupported_legacy_assignment_is_preserved_and_blocks_product_writes(): void
    {
        $seller = $this->shop('Unknown legacy category');
        $this->actingAs($seller->owner)->get(route('seller.products.create'))->assertOk()
            ->assertSeeText('No supported subcategories');
        $this->post(route('seller.products.store'), ['submit_action' => 'draft'])->assertSessionHasErrors('subcategory');
        $this->assertSame('Unknown legacy category', $seller->fresh()->business_category);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_legacy_product_requires_explicit_valid_reclassification_and_is_never_changed_on_read(): void
    {
        $seller = $this->shop();
        $product = $this->product($seller);
        $legacy = Category::create(['name' => 'Legacy classification', 'slug' => 'legacy-classification']);
        $product->update(['category_id' => $legacy->id]);
        $this->get(route('seller.products.edit', $product->id))->assertOk()->assertSeeText('Existing classification: Legacy classification');
        $this->assertSame($legacy->id, $product->fresh()->category_id);
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, ['status' => 'draft', 'subcategory' => null]))
            ->assertSessionHasErrors('subcategory');
        $this->assertSame($legacy->id, $product->fresh()->category_id);
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, ['subcategory' => $this->child($seller)->id]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->child($seller)->id, $product->fresh()->category_id);
    }

    public function test_session_flags_and_non_seller_accounts_cannot_use_persisted_product_pages(): void
    {
        foreach (['seller.products', 'seller.products.create', 'seller.inventory'] as $name) {
            $this->withSession(['seller_logged_in' => true])->get(route($name))->assertForbidden();
        }
        $this->post(route('seller.products.store'), ['submit_action' => 'draft'])->assertForbidden();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('seller.products.create'))->assertForbidden();
    }

    public function test_pending_access_is_local_only_and_never_approves_or_bypasses_suspension(): void
    {
        $seller = $this->shop('Pet Supplies', 'pending');
        $this->actingAs($seller->owner)->get(route('seller.products.create'))->assertForbidden();
        $this->app->instance('env', 'local');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->get(route('seller.products.create'))->assertOk();
        $this->post(route('seller.products.store'), $this->payload($seller))->assertRedirect();
        $this->assertSame('pending', $seller->fresh()->status);
        $seller->owner->update(['is_suspended' => true]);
        $this->get(route('seller.products.create'))->assertForbidden();
    }

    public function test_inventory_updates_persistent_stock_and_session_projection(): void
    {
        $seller = $this->shop();
        $product = $this->product($seller);
        $this->post(route('seller.inventory.increase', $product->id))->assertRedirect();
        $this->assertSame(4, $product->variants->first()->fresh()->stock);
        $this->flushSession();
        $this->get(route('seller.inventory'))->assertOk();
        $this->assertSame(4, session('seller_products.P0001.stock'));
        $this->post(route('seller.inventory.decrease', $product->id))->assertRedirect();
        $this->assertSame(3, $product->variants->first()->fresh()->stock);
    }

    public function test_unverified_session_rows_are_kept_for_review_and_not_imported_or_exposed(): void
    {
        $seller = $this->shop();
        $legacy = ['P0042' => ['name' => 'Unverified listing', 'category' => 'Pet Supplies']];
        $this->actingAs($seller->owner)->withSession(['seller_products' => $legacy])->get(route('seller.products'))
            ->assertOk()->assertDontSeeText('Unverified listing')->assertSeeText('Earlier listings need an ownership review')
            ->assertSessionHas('seller_products_legacy_review', $legacy);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_failed_database_save_rolls_back_product_and_removes_new_uploads(): void
    {
        $seller = $this->shop();
        DB::statement("CREATE TRIGGER reject_product_image BEFORE INSERT ON product_images BEGIN SELECT RAISE(ABORT, 'fixture failure'); END;");
        try {
            $this->actingAs($seller->owner)->withoutExceptionHandling()->post(route('seller.products.store'), $this->payload($seller));
            $this->fail('Expected database failure');
        } catch (QueryException) {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('product_variants', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_existing_multiple_variants_are_preserved_during_category_change(): void
    {
        $seller = $this->shop();
        $product = $this->product($seller);
        $product->variants()->create(['sku' => 'SECOND', 'name' => 'Second option', 'options' => ['Color' => 'Blue'], 'price_minor' => 4000, 'stock' => 5]);
        $variants = $product->fresh()->variants->toArray();
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, [
            'subcategory' => $this->child($seller, 1)->id, 'stock' => 8,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($variants, $product->fresh()->variants->toArray());
        $this->post(route('seller.inventory.increase', $product->id))->assertStatus(409);
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, ['price' => 1, 'stock' => 8]))
            ->assertSessionHasErrors('stock');
        $this->assertSame($variants, $product->fresh()->variants->toArray());
    }

    public function test_new_main_category_persists_through_registration_login_and_store_profile(): void
    {
        Storage::fake('seller_documents');
        Http::preventStrayRequests();
        Http::fake([
            '*/regions' => Http::response(['data' => [['code' => '0100000000', 'name' => 'Region I']]]),
            '*/regions/0100000000/provinces' => Http::response(['data' => [['code' => '0101000000', 'name' => 'Province']]]),
            '*/provinces/0101000000/cities-municipalities' => Http::response(['data' => [['code' => '0101010000', 'name' => 'City']]]),
            '*/cities-municipalities/0101010000/barangays' => Http::response(['data' => [['code' => '0101010001', 'name' => 'Barangay']]]),
        ]);
        $this->post(route('seller.register.submit'), [
            'first_name' => 'Test', 'last_name' => 'Seller', 'sex' => 'Female', 'birthday' => '1990-01-01',
            'email' => 'category@example.test', 'phone' => '09123456789', 'region' => '0100000000',
            'province' => '0101000000', 'municipality' => '0101010000', 'barangay' => '0101010001',
            'address' => 'Fixture pickup', 'postal_code' => '0123', 'business_name' => 'Category fixture',
            'business_category' => 'Food and Gourmet', 'seller_type' => 'Individual', 'tin' => '123-456-789-012',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => '1',
            'government_id' => UploadedFile::fake()->createWithContent('identity.png', file_get_contents(public_path('images/logo.png'))),
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $seller = Seller::sole();
        $this->assertSame('Food and Gourmet', $seller->business_category);
        $this->assertSame('pending', $seller->status);
        $this->assertSame('Individual', $seller->seller_type);
        $this->assertSame('123456789012', $seller->tin);
        $this->assertDatabaseCount('addresses', 1);
        $this->assertDatabaseCount('seller_documents', 1);
        $this->assertGuest();
        $this->app->instance('env', 'local');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->post(route('login.submit'), ['login' => 'category@example.test', 'password' => 'password123'])
            ->assertRedirect(route('seller.dashboard'));
        $this->get(route('seller.store.profile'))->assertOk()->assertSeeText('Food and Gourmet');
        $this->post(route('seller.store.profile.update'), ['shop_name' => 'Renamed fixture', 'phone' => '09123456789', 'business_category' => 'Pet Supplies'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Food and Gourmet', $seller->fresh()->business_category);
        $this->get(route('seller.products.create'))->assertOk()->assertSeeText('Baking Supplies & Ingredients')->assertDontSeeText('Dog Food & Treats');
    }

    public function test_category_setup_stops_on_ambiguous_matches_without_changing_shared_data(): void
    {
        Category::create(['name' => ' PET SUPPLIES ', 'slug' => 'duplicate-legacy-root']);
        $before = Category::orderBy('id')->get()->toArray();
        try {
            SellerCategories::populate();
            $this->fail('Expected ambiguous reference to stop setup');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Ambiguous category', $exception->getMessage());
            $this->assertSame($before, Category::orderBy('id')->get()->toArray());
        }
    }

    public function test_product_posts_retain_csrf_protection_and_production_pending_restrictions(): void
    {
        $seller = $this->shop();
        $this->app->instance('env', 'production');
        $this->actingAs($seller->owner)->post(route('seller.products.store'), $this->payload($seller))->assertStatus(419);
        $this->withSession(['_token' => 'fixture-csrf-token'])->post(route('seller.products.store'),
            $this->payload($seller, ['_token' => 'fixture-csrf-token']))->assertSessionHasNoErrors()->assertRedirect();
        $seller->update(['status' => 'pending']);
        $this->get(route('seller.products.create'))->assertForbidden();
        $this->assertSame('pending', $seller->fresh()->status);
    }

    public static function compatibleNames(): array
    {
        $cases = [];
        foreach (['Kids and Baby' => 'Kids and Baby Apparel', 'Office and School Supplies' => 'Office and School Supplies Group'] as $preferred => $old) {
            foreach ([$preferred, $old] as $recordName) {
                foreach ([$preferred, $old] as $assignment) {
                    $cases[$recordName.' / '.$assignment] = [$preferred, $recordName, $assignment];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('compatibleNames')]
    public function test_preferred_and_existing_names_share_records_children_and_restricted_product_workflow(string $preferred, string $recordName, string $assignment): void
    {
        $main = Category::whereNull('parent_id')->where('name', $preferred)->firstOrFail();
        $main->update(['name' => $recordName]);
        $seller = $this->shop($assignment);
        $this->assertSame($main->id, SellerCategories::main($seller)->id);
        $this->assertSame(config('seller_categories')[$preferred], SellerCategories::children($seller, $main)->pluck('name')->all());
        $product = $this->product($seller);
        $childId = $product->category_id;
        $categories = Category::orderBy('id')->get()->toArray();
        SellerCategories::populate();
        SellerCategories::populate();
        $this->assertSame($categories, Category::orderBy('id')->get()->toArray());
        $this->assertSame($childId, $product->fresh()->category_id);
        $this->assertSame($main->id, $product->fresh()->category->parent_id);
        $this->assertSame(14, Category::whereNull('parent_id')->count());
        $this->assertSame(98, Category::count());

        $this->get(route('seller.products.create'))->assertOk()->assertSee('readonly value="'.e($preferred).'"', false);
        $this->get(route('seller.products.edit', $product->id))->assertOk()
            ->assertSee('readonly value="'.e($preferred).'"', false)->assertSee('value="'.$childId.'" selected', false)
            ->assertDontSeeText('Existing classification:');
        $this->get(route('seller.products'))->assertOk()->assertSeeText($preferred);
        $this->get(route('seller.store.profile'))->assertOk()->assertSeeText($preferred)
            ->assertDontSee('name="business_category"', false);
        $foreignChild = Category::where('name', 'Dog Food & Treats')->firstOrFail();
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, ['subcategory' => $foreignChild->id]))
            ->assertSessionHasErrors('subcategory');
        $this->assertSame($childId, $product->fresh()->category_id);
        $this->post(route('seller.products.store'), $this->payload($seller, ['subcategory' => $foreignChild->id]))
            ->assertSessionHasErrors('subcategory');
        $this->assertDatabaseCount('products', 1);
        $this->put(route('seller.products.update', $product->id), $this->updatePayload($product, ['subcategory' => $this->child($seller, 1)->id]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($this->child($seller, 1)->id, $product->fresh()->category_id);
        $this->assertSame($assignment, $seller->fresh()->business_category);
        $this->assertSame($recordName, $main->fresh()->name);
    }

    public function test_alias_collisions_are_blocked_without_choosing_or_modifying_either_record(): void
    {
        Category::create(['name' => 'Kids and Baby Apparel', 'slug' => 'existing-kids-alias']);
        $seller = $this->shop('Kids and Baby');
        $before = Category::orderBy('id')->get()->toArray();
        $this->assertNull(SellerCategories::main($seller));
        $this->actingAs($seller->owner)->post(route('seller.products.store'), ['submit_action' => 'draft'])
            ->assertSessionHasErrors('subcategory');
        try {
            SellerCategories::populate();
            $this->fail('Expected alias ambiguity to stop setup');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Ambiguous category', $exception->getMessage());
            $this->assertSame($before, Category::orderBy('id')->get()->toArray());
        }
    }
}
