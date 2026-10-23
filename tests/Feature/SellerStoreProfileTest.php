<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerStoreProfileTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $status = 'approved'): Seller
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'phone' => '0'.(9171234567 + User::count()), 'password' => 'Old-pass-123']);
        $address = Address::create([
            'user_id' => $owner->id, 'label' => 'Pickup', 'recipient' => $owner->name,
            'phone' => '09171234567', 'region' => 'Region IV-A (CALABARZON)', 'province' => 'Laguna',
            'city' => 'City of Biñan', 'barangay' => 'Santo Domingo', 'line1' => 'Original pickup',
            'postal_code' => '4024', 'is_default' => false,
        ]);

        return Seller::create([
            'user_id' => $owner->id, 'name' => 'Fixture Store '.$owner->id, 'slug' => 'fixture-store-'.$owner->id,
            'business_category' => 'Pet Supplies', 'seller_type' => 'Individual',
            'status' => $status, 'pickup_address_id' => $address->id, 'description' => 'Original description',
        ])->fresh(['owner', 'pickupAddress']);
    }

    private function profile(array $extra = []): array
    {
        return array_replace(['shop_name' => 'Updated Store', 'phone' => '+639181234567', 'description' => 'Saved description'], $extra);
    }

    private function image(string $name = 'logo.png')
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('images/logo.png')));
    }

    private function address(array $extra = []): array
    {
        return array_replace([
            'region' => '0400000000', 'province' => '0403400000', 'municipality' => '0403403000',
            'barangay' => '0403403024', 'address' => 'Updated pickup', 'postal_code' => '4024', 'pickup_phone' => '09181234567',
        ], $extra);
    }

    private function locations(bool $withoutProvince = false): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/regions' => Http::response(['data' => [['code' => '0400000000', 'name' => 'Region IV-A (CALABARZON)']]]),
            '*/regions/0400000000/provinces' => Http::response(['data' => $withoutProvince ? [] : [['code' => '0403400000', 'name' => 'Laguna']]]),
            '*/provinces/0403400000/cities-municipalities' => Http::response(['data' => [['code' => '0403403000', 'name' => 'City of Biñan']]]),
            '*/regions/0400000000/cities-municipalities' => Http::response(['data' => [['code' => '0403403000', 'name' => 'City of Biñan']]]),
            '*/cities-municipalities/0403403000/barangays' => Http::response(['data' => [['code' => '0403403024', 'name' => 'Santo Domingo']]]),
        ]);
    }

    public function test_guest_and_legacy_session_cannot_edit_persisted_stores(): void
    {
        foreach (['seller.store.profile', 'seller.store.profile.update', 'seller.store.profile.photo.remove', 'seller.store.address.update', 'seller.store.password.update'] as $route) {
            $this->flushSession();
            ($route === 'seller.store.profile' ? $this->get(route($route)) : $this->post(route($route)))
                ->assertRedirect(route('seller.register'));
            $this->withSession(['seller_logged_in' => true]);
            ($route === 'seller.store.profile' ? $this->get(route($route)) : $this->post(route($route)))
                ->assertForbidden();
        }
    }

    public function test_non_seller_cannot_use_profile_even_with_legacy_flag(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($user)->withSession(['seller_logged_in' => true])
            ->post(route('seller.store.profile.update'), $this->profile())->assertForbidden();
    }

    public function test_pending_rejected_suspended_and_missing_stores_are_denied(): void
    {
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $shop = $this->shop($status);
            $this->actingAs($shop->owner)->get(route('seller.store.profile'))->assertForbidden();
            $this->post(route('seller.store.profile.update'), $this->profile())->assertForbidden();
        }
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($owner)->get(route('seller.store.profile'))->assertForbidden()
            ->assertSee('No persisted store is linked to your account.');
    }

    public function test_inactive_or_suspended_owner_cannot_edit_an_approved_shop(): void
    {
        $shop = $this->shop();
        foreach ([['status' => 'pending'], ['status' => 'active', 'is_suspended' => true]] as $attributes) {
            $shop->owner->update($attributes);
            $this->actingAs($shop->owner->fresh())->post(route('seller.store.profile.update'), $this->profile())->assertForbidden();
        }
    }

    public function test_page_displays_owned_database_shop_id_and_category_not_stale_session_data(): void
    {
        User::factory()->create(['role' => 'buyer']);
        $shop = $this->shop();
        $other = $this->shop();
        $response = $this->actingAs($shop->owner)->withSession(['seller_profile' => ['shop_name' => 'Stale session name']])
            ->get(route('seller.store.profile', ['seller_id' => $other->id]));
        $response->assertOk()->assertViewHas('store', fn ($store) => $store->id === $shop->id)
            ->assertSee('Shop ID')->assertSee('Assigned Main Category')->assertSee('Pet Supplies')
            ->assertSee('id="shopId" readonly value="'.$shop->shop_code.'"', false)
            ->assertSee('id="assignedCategory" readonly', false)
            ->assertDontSee('Stale session name')->assertDontSee($other->name);
        $this->assertNotSame($shop->user_id, $shop->id);
    }

    public function test_updates_persist_only_whitelisted_business_fields_and_keep_slug_and_owner_contact(): void
    {
        $shop = $this->shop();
        $other = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $this->profile([
            'shop_name' => '  Updated Store  ', 'seller_id' => $other->id, 'shop_id' => $other->id,
            'id' => $other->id, 'user_id' => $other->user_id, 'status' => 'suspended',
            'commission_bps' => 0, 'business_category' => 'Electronics and Gadgets', 'tin' => '123456789012',
            'slug' => 'hijacked', 'email' => 'changed@example.test',
        ]))->assertRedirect(route('seller.store.profile').'#shop-profile');
        $saved = $shop->fresh();
        $this->assertSame('Updated Store', $saved->name);
        $this->assertSame('Saved description', $saved->description);
        foreach (['id', 'user_id', 'business_category', 'status', 'commission_bps', 'slug', 'tin'] as $field) {
            $this->assertSame($shop->$field, $saved->$field);
        }
        $this->assertSame('09181234567', $saved->pickupAddress->phone);
        $this->assertSame('09171234567', $shop->owner->fresh()->phone);
        $this->assertSame($shop->owner->email, $shop->owner->fresh()->email);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->assertSame('Original pickup', $saved->pickupAddress->line1);
    }

    public function test_invalid_profile_preserves_database_values_and_old_input(): void
    {
        $shop = $this->shop();
        $this->actingAs($shop->owner)->from(route('seller.store.profile'))->post(route('seller.store.profile.update'), $this->profile([
            'shop_name' => '   ', 'description' => str_repeat('a', 501), 'phone' => 'invalid',
        ]))->assertSessionHasErrors(['shop_name', 'description', 'phone'])->assertSessionHasInput('phone', 'invalid');
        $this->assertSame($shop->name, $shop->fresh()->name);
        $this->assertSame($shop->description, $shop->fresh()->description);
    }

    public function test_logo_and_banner_uploads_persist_and_replacements_remove_only_unreferenced_managed_images(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $this->profile([
            'profile_photo' => $this->image(), 'banner' => $this->image('banner.png'),
        ]))->assertSessionHasNoErrors();
        $saved = $shop->fresh();
        Storage::disk('public')->assertExists([$saved->logo_path, $saved->banner_path]);
        $this->assertNotSame($saved->logo_path, $saved->banner_path);
        $this->post(route('seller.store.profile.update'), $this->profile(['profile_photo' => $this->image()]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($saved->logo_path);
        Storage::disk('public')->assertExists($saved->banner_path);
        $this->assertSame($saved->banner_path, $shop->fresh()->banner_path);
    }

    public function test_invalid_and_oversized_images_preserve_existing_files(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $path = 'seller-profiles/'.$shop->id.'/logo_path/existing.png';
        Storage::disk('public')->put($path, 'existing file');
        $shop->update(['logo_path' => $path]);
        foreach ([UploadedFile::fake()->create('payload.php', 1, 'text/plain'), $this->image()->size(2049)] as $file) {
            $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $this->profile(['profile_photo' => $file]))
                ->assertSessionHasErrors('profile_photo');
            Storage::disk('public')->assertExists($path);
            $this->assertSame($path, $shop->fresh()->logo_path);
        }
        $this->post(route('seller.store.profile.update'), $this->profile(['banner' => $this->image()->size(5121)]))->assertSessionHasErrors('banner');
        $this->post(route('seller.store.profile.update'), $this->profile(['banner' => UploadedFile::fake()->create('fake.png', 1, 'text/plain')]))->assertSessionHasErrors('banner');
    }

    public function test_image_removal_cannot_select_another_sellers_image_or_delete_shared_assets(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $other = $this->shop();
        $path = 'seller-profiles/'.$shop->id.'/logo_path/shared.png';
        Storage::disk('public')->put($path, 'shared');
        $shop->update(['logo_path' => $path]);
        $other->update(['logo_path' => $path]);
        $this->actingAs($shop->owner)->post(route('seller.store.profile.photo.remove'), ['seller_id' => $other->id, 'path' => $path])->assertRedirect();
        $this->assertNull($shop->fresh()->logo_path);
        $this->assertSame($path, $other->fresh()->logo_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_database_failure_rolls_back_profile_and_removes_new_uploads(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        DB::statement("CREATE TRIGGER reject_profile BEFORE UPDATE ON sellers BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $this->profile(['profile_photo' => $this->image(), 'banner' => $this->image()]))->assertStatus(500);
        $this->assertSame($shop->name, $shop->fresh()->name);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_valid_pickup_changes_persist_without_touching_personal_or_other_addresses(): void
    {
        $this->locations();
        $shop = $this->shop();
        $other = $this->shop();
        $original = $shop->pickupAddress;
        $original->update(['is_default' => true]);
        $this->actingAs($shop->owner)->post(route('seller.store.address.update'), $this->address(['seller_id' => $other->id]))->assertSessionHasNoErrors();
        $saved = $shop->fresh()->pickupAddress;
        $this->assertNotSame($original->id, $saved->id);
        $this->assertSame('Updated pickup', $saved->line1);
        $this->assertSame('Laguna', $saved->province);
        $this->assertSame('City of Biñan', $saved->city);
        $this->assertSame('Santo Domingo', $saved->barangay);
        $this->assertSame('Original pickup', $original->fresh()->line1);
        $this->assertTrue($original->fresh()->is_default);
        $this->assertFalse($saved->is_default);
        $this->assertSame('Original pickup', $other->fresh()->pickupAddress->line1);
        $this->assertSame('09171234567', $shop->owner->fresh()->phone);
    }

    public function test_mismatched_or_invalid_locations_are_rejected_without_updating_address(): void
    {
        $this->locations();
        $shop = $this->shop();
        foreach (['region', 'province', 'municipality', 'barangay'] as $field) {
            $this->actingAs($shop->owner)->post(route('seller.store.address.update'), $this->address([$field => '9999999999']))->assertSessionHasErrors($field);
            $this->assertSame('Original pickup', $shop->fresh()->pickupAddress->line1);
        }
        $this->post(route('seller.store.address.update'), $this->address(['postal_code' => '12ab']))->assertSessionHasErrors('postal_code');
    }

    public function test_psgc_outage_preserves_existing_address(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.store.address.update'), $this->address())->assertSessionHasErrors('region');
        $this->assertSame('Original pickup', $shop->fresh()->pickupAddress->line1);
    }

    public function test_province_free_region_uses_existing_psgc_region_city_endpoint(): void
    {
        $this->locations(true);
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.store.address.update'), $this->address(['province' => '']))->assertSessionHasNoErrors();
        $this->assertSame('', $shop->fresh()->pickupAddress->province);
    }

    public function test_missing_pickup_can_be_saved_using_real_validated_location_data(): void
    {
        $this->locations();
        $shop = $this->shop();
        $shop->update(['pickup_address_id' => null]);
        $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $this->profile())->assertSessionHasErrors('phone');
        $this->post(route('seller.store.address.update'), $this->address())->assertSessionHasNoErrors();
        $this->assertSame('Updated pickup', $shop->fresh()->pickupAddress->line1);
    }

    public function test_foreign_pickup_relationship_and_ambiguous_owned_shops_fail_closed(): void
    {
        $shop = $this->shop();
        $other = $this->shop();
        $shop->update(['pickup_address_id' => $other->pickup_address_id]);
        $this->actingAs($shop->owner)->get(route('seller.store.profile'))->assertForbidden();
        $other->update(['user_id' => $shop->user_id]);
        $this->get(route('seller.store.profile'))->assertStatus(409);
    }

    public function test_login_reloads_saved_store_after_logout_and_new_session(): void
    {
        $shop = $this->shop();
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'Old-pass-123'])->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($shop->owner);
        $this->post(route('seller.store.profile.update'), $this->profile())->assertSessionHasNoErrors();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->flushSession();
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'Old-pass-123'])->assertRedirect(route('seller.dashboard'));
        $this->get(route('seller.store.profile'))->assertOk()->assertSee('Updated Store')->assertSee('Saved description');
        $this->assertSame('Updated Store', session('seller_profile.shop_name'));
        $this->assertNull(session('seller_profile.password_hash'));
    }

    public function test_pending_store_login_does_not_authenticate_or_set_seller_flag(): void
    {
        $shop = $this->shop('pending');
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'Old-pass-123'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('login')->assertSessionMissing('seller_logged_in');
        $this->assertGuest();
    }

    public function test_existing_password_endpoint_checks_and_updates_real_user_password(): void
    {
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.store.password.update'), ['current_password' => 'wrong', 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123'])->assertSessionHasErrors('current_password');
        $this->post(route('seller.store.password.update'), ['current_password' => 'Old-pass-123', 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('New-pass-123', $shop->owner->fresh()->password));
        Auth::logout();
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'New-pass-123'])->assertRedirect(route('seller.dashboard'));
    }

    public function test_omitting_optional_description_preserves_existing_value(): void
    {
        $shop = $this->shop();
        $data = $this->profile();
        unset($data['description']);
        $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $data)->assertSessionHasNoErrors();
        $this->assertSame('Original description', $shop->fresh()->description);
    }

    public function test_approval_revocation_is_checked_again_on_subsequent_requests(): void
    {
        $shop = $this->shop();
        $this->actingAs($shop->owner)->get(route('seller.store.profile'))->assertOk();
        $shop->update(['status' => 'suspended']);
        foreach (['seller.store.profile.update', 'seller.store.address.update', 'seller.store.profile.photo.remove', 'seller.store.password.update'] as $route) {
            $this->post(route($route), $this->profile())->assertForbidden();
        }
    }

    private function environment(string $environment): void
    {
        $this->app->instance('env', $environment);
        // Laravel's automatic test CSRF exemption depends on APP_ENV=testing.
        // Keep that exemption in these isolated environment-simulation tests only.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_local_pending_seller_can_log_in_and_visit_existing_seller_pages(): void
    {
        $this->environment('local');
        $shop = $this->shop('pending');
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'Old-pass-123'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($shop->owner);
        foreach (['seller.dashboard', 'seller.store.profile', 'seller.products', 'seller.products.create', 'seller.inventory', 'seller.orders', 'seller.reports', 'seller.sales', 'seller.vouchers.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->assertSame($shop->id, session('seller_profile.shop_id'));
        $this->assertSame('pending', session('seller_profile.status'));
        $this->assertSame('pending', $shop->fresh()->status);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.register'));
    }

    public function test_local_pending_seller_can_persist_only_their_own_store_changes(): void
    {
        $this->environment('local');
        $shop = $this->shop('pending');
        $other = $this->shop('pending');
        $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $this->profile([
            'seller_id' => $other->id, 'user_id' => $other->user_id, 'status' => 'approved',
            'business_category' => 'Electronics and Gadgets',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('Updated Store', $shop->fresh()->name);
        $this->assertSame('pending', $shop->fresh()->status);
        $this->assertSame('Pet Supplies', $shop->fresh()->business_category);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->assertSame('pending', $other->fresh()->status);
        $this->flushSession();
        $this->get(route('seller.store.profile'))->assertOk()->assertSee('Updated Store')->assertSee('Pet Supplies');
    }

    public function test_nonlocal_environments_reject_pending_sellers_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $shop = $this->shop('pending');
        foreach (['production', 'staging', 'testing'] as $environment) {
            $this->environment($environment);
            $this->actingAs($shop->owner)->get(route('seller.dashboard'))->assertForbidden();
            $this->get(route('seller.store.profile'))->assertForbidden();
            $this->post(route('seller.store.profile.update'), $this->profile())->assertForbidden();
        }
        $this->assertSame('pending', $shop->fresh()->status);
    }

    public function test_production_login_rejects_pending_seller_and_clears_authentication(): void
    {
        $this->environment('production');
        $shop = $this->shop('pending');
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'Old-pass-123'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('login')->assertSessionMissing('seller_logged_in');
        $this->assertGuest();
    }

    public function test_local_access_does_not_admit_guests_other_roles_or_ineligible_sellers(): void
    {
        $this->environment('local');
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.register'));
        $this->get(route('seller.store.profile'))->assertRedirect(route('seller.register'));
        foreach (['buyer', 'admin', 'superadmin', 'rider', 'logistics'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get(route('seller.dashboard'))->assertForbidden();
            $this->post(route('seller.store.profile.update'), $this->profile())->assertForbidden();
        }
        foreach (['rejected', 'suspended'] as $status) {
            $shop = $this->shop($status);
            $this->actingAs($shop->owner)->get(route('seller.dashboard'))->assertForbidden();
        }
        $shop = $this->shop('pending');
        $shop->owner->update(['is_suspended' => true]);
        $this->actingAs($shop->owner->fresh())->get(route('seller.dashboard'))->assertForbidden();
        $shop->owner->update(['is_suspended' => false, 'status' => 'pending']);
        $this->actingAs($shop->owner->fresh())->get(route('seller.dashboard'))->assertForbidden();
    }

    public function test_local_pending_resolution_does_not_guess_between_multiple_shops(): void
    {
        $this->environment('local');
        $shop = $this->shop('pending');
        $other = $this->shop('pending');
        $other->update(['user_id' => $shop->user_id, 'pickup_address_id' => $shop->pickup_address_id]);
        $this->actingAs($shop->owner)->get(route('seller.store.profile'))->assertStatus(409);
        $shop->update(['status' => 'approved']);
        $this->get(route('seller.store.profile'))->assertOk()->assertViewHas('store', fn ($store) => $store->id === $shop->id);
    }

    public function test_saved_image_urls_follow_the_active_site_origin_not_the_configured_app_url(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $logo = 'seller-profiles/'.$shop->id.'/logo_path/saved.png';
        $banner = 'seller-profiles/'.$shop->id.'/banner_path/saved.png';
        Storage::disk('public')->put($logo, 'saved logo');
        Storage::disk('public')->put($banner, 'saved banner');
        $shop->update(['logo_path' => $logo, 'banner_path' => $banner]);
        config(['filesystems.disks.public.url' => 'http://localhost/storage']);
        Storage::forgetDisk('public');
        $response = $this->actingAs($shop->owner)->get('http://127.0.0.1:8123/seller/store-profile');
        $response->assertOk()
            ->assertSee('src="http://127.0.0.1:8123/storage/'.$logo.'"', false)
            ->assertSee('src="http://127.0.0.1:8123/storage/'.$banner.'"', false);
    }

    public function test_shop_display_code_is_stable_read_only_and_based_on_the_store_primary_key(): void
    {
        $shop = $this->shop();
        $expected = 'SHOP-'.str_pad((string) $shop->id, 5, '0', STR_PAD_LEFT);
        $this->assertSame($expected, $shop->shop_code);
        $this->assertSame($expected, $shop->fresh()->shop_code);
        $this->assertNull((new Seller)->shop_code);
        $response = $this->actingAs($shop->owner)->get(route('seller.store.profile'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $field = (new \DOMXPath($dom))->query('//input[@id="shopId"]')->item(0);
        $this->assertSame($expected, $field->getAttribute('value'));
        $this->assertTrue($field->hasAttribute('readonly'));
        $this->assertFalse($field->hasAttribute('name'));
        $this->post(route('seller.store.profile.update'), $this->profile(['shop_code' => 'FORGED-CODE']))->assertSessionHasNoErrors();
        $this->assertSame($expected, $shop->fresh()->shop_code);
    }

    public function test_clean_image_controls_use_hidden_inputs_and_do_not_display_storage_filenames(): void
    {
        $shop = $this->shop();
        $shop->update(['logo_path' => 'seller-profiles/'.$shop->id.'/logo_path/generated-logo.png', 'banner_path' => 'seller-profiles/'.$shop->id.'/banner_path/generated-banner.png']);
        $response = $this->actingAs($shop->owner)->get(route('seller.store.profile'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['profile_photo', 'storeBanner'] as $id) {
            $this->assertCount(1, $xpath->query('//input[@id="'.$id.'"][@type="file"][contains(@class,"sr-only")]'));
        }
        foreach (['logoUploadButton', 'bannerUploadButton'] as $id) {
            $button = $xpath->query('//button[@id="'.$id.'"][@type="button"]')->item(0);
            $this->assertSame('Replace image', trim($button->textContent));
        }
        $this->assertCount(2, $xpath->query('//button[@formnovalidate][@name="image"][@value="logo" or @value="banner"]'));
        $logo = $xpath->query('//img[@id="shopPhotoPreview"]')->item(0);
        $this->assertStringContainsString('rounded-[28px]', $logo->getAttribute('class'));
        $this->assertStringContainsString('sm:h-40', $logo->getAttribute('class'));
        $this->assertStringNotContainsString('rounded-full', $logo->getAttribute('class'));
        $visibleText = $xpath->query('//section[@id="shop-profile"]')->item(0)->textContent;
        $this->assertStringNotContainsString('generated-logo.png', $visibleText);
        $this->assertStringNotContainsString('generated-banner.png', $visibleText);
        $this->assertStringNotContainsString('Choose File', $visibleText);
    }

    public function test_saved_logo_and_banner_are_rendered_after_redirect_refresh_and_new_login(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.store.profile.update'), $this->profile([
            'profile_photo' => $this->image(), 'banner' => $this->image('banner.png'),
        ]))->assertRedirect(route('seller.store.profile').'#shop-profile');
        $saved = $shop->fresh();
        Storage::disk('public')->assertExists([$saved->logo_path, $saved->banner_path]);
        for ($refresh = 0; $refresh < 2; $refresh++) {
            $this->get(route('seller.store.profile'))->assertOk()
                ->assertSee('src="'.asset('storage/'.$saved->logo_path).'"', false)
                ->assertSee('src="'.asset('storage/'.$saved->banner_path).'"', false);
        }
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->flushSession();
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'Old-pass-123'])->assertRedirect(route('seller.dashboard'));
        $response = $this->get(route('seller.store.profile'))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['shopPhotoPreview', 'storeBannerPreview'] as $id) {
            $image = $xpath->query('//img[@id="'.$id.'"]')->item(0);
            $this->assertStringNotContainsString('hidden', $image->getAttribute('class'));
            $this->assertStringContainsString('/storage/seller-profiles/'.$shop->id.'/', $image->getAttribute('src'));
        }
    }

    public function test_banner_removal_uses_existing_endpoint_and_keeps_logo_and_other_shop_images(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $other = $this->shop();
        $logo = 'seller-profiles/'.$shop->id.'/logo_path/logo.png';
        $banner = 'seller-profiles/'.$shop->id.'/banner_path/banner.png';
        $foreign = 'seller-profiles/'.$other->id.'/banner_path/banner.png';
        foreach ([$logo, $banner, $foreign] as $path) {
            Storage::disk('public')->put($path, 'existing image');
        }
        $shop->update(['logo_path' => $logo, 'banner_path' => $banner]);
        $other->update(['banner_path' => $foreign]);
        $this->actingAs($shop->owner)->post(route('seller.store.profile.photo.remove'), [
            'image' => 'banner', 'seller_id' => $other->id, 'path' => $foreign,
        ])->assertRedirect(route('seller.store.profile').'#shop-profile');
        $this->assertNull($shop->fresh()->banner_path);
        $this->assertSame($logo, $shop->fresh()->logo_path);
        $this->assertSame($foreign, $other->fresh()->banner_path);
        Storage::disk('public')->assertMissing($banner);
        Storage::disk('public')->assertExists([$logo, $foreign]);
        $this->get(route('seller.store.profile'))->assertOk()->assertSee('No store banner uploaded.');
    }

    public function test_removal_rejects_arbitrary_image_columns_and_preserves_shared_or_legacy_files(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $other = $this->shop();
        $path = 'seller-profiles/'.$shop->id.'/banner_path/shared.png';
        Storage::disk('public')->put($path, 'shared image');
        $shop->update(['banner_path' => $path]);
        $other->update(['banner_path' => $path]);
        $this->actingAs($shop->owner)->post(route('seller.store.profile.photo.remove'), ['image' => 'status'])->assertSessionHasErrors('image');
        $this->assertSame($path, $shop->fresh()->banner_path);
        $this->post(route('seller.store.profile.photo.remove'), ['image' => 'banner'])->assertRedirect();
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $other->fresh()->banner_path);
        $legacy = 'shared-assets/legacy-logo.png';
        Storage::disk('public')->put($legacy, 'legacy image');
        $shop->update(['logo_path' => $legacy]);
        $this->post(route('seller.store.profile.photo.remove'))->assertRedirect();
        $this->assertNull($shop->fresh()->logo_path);
        Storage::disk('public')->assertExists($legacy);
    }

    public function test_failed_removal_preserves_image_reference_and_file(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $path = 'seller-profiles/'.$shop->id.'/banner_path/existing.png';
        Storage::disk('public')->put($path, 'existing image');
        $shop->update(['banner_path' => $path]);
        DB::statement("CREATE TRIGGER reject_image_removal BEFORE UPDATE ON sellers BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->actingAs($shop->owner)->post(route('seller.store.profile.photo.remove'), ['image' => 'banner'])->assertStatus(500);
        $this->assertSame($path, $shop->fresh()->banner_path);
        Storage::disk('public')->assertExists($path);
    }
}
