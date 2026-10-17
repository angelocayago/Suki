<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerMyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $status = 'approved'): Seller
    {
        $owner = User::factory()->create([
            'role' => 'seller', 'status' => 'active', 'first_name' => 'Fixture', 'last_name' => 'Owner',
            'name' => 'Fixture Owner', 'phone' => '0'.(9171234567 + User::count()), 'password' => 'Old-pass-123',
        ]);
        $pickup = Address::create([
            'user_id' => $owner->id, 'label' => 'Pickup', 'recipient' => $owner->name,
            'phone' => '09171234567', 'region' => 'Region IV-A (CALABARZON)', 'province' => 'Laguna',
            'city' => 'City of Biñan', 'barangay' => 'Santo Domingo', 'line1' => 'Store pickup',
            'postal_code' => '4024', 'is_default' => true,
        ]);

        return Seller::create([
            'user_id' => $owner->id, 'name' => 'Fixture Shop '.$owner->id, 'slug' => 'fixture-shop-'.$owner->id,
            'business_category' => 'Pet Supplies', 'status' => $status, 'pickup_address_id' => $pickup->id,
        ])->fresh(['owner', 'pickupAddress']);
    }

    private function info(array $extra = []): array
    {
        return array_replace(['first_name' => 'Updated', 'middle_initial' => 'A', 'last_name' => 'Owner', 'phone' => '+639181234567'], $extra);
    }

    private function photo()
    {
        return UploadedFile::fake()->createWithContent('photo.png', file_get_contents(public_path('images/logo.png')));
    }

    private function address(array $extra = []): array
    {
        return array_replace([
            'region' => '0400000000', 'province' => '0403400000', 'municipality' => '0403403000',
            'barangay' => '0403403024', 'address' => 'Personal home', 'postal_code' => '4024',
        ], $extra);
    }

    private function locations(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/regions' => Http::response(['data' => [['code' => '0400000000', 'name' => 'Region IV-A (CALABARZON)']]]),
            '*/regions/0400000000/provinces' => Http::response(['data' => [['code' => '0403400000', 'name' => 'Laguna']]]),
            '*/provinces/0403400000/cities-municipalities' => Http::response(['data' => [['code' => '0403403000', 'name' => 'City of Biñan']]]),
            '*/cities-municipalities/0403403000/barangays' => Http::response(['data' => [['code' => '0403403024', 'name' => 'Santo Domingo']]]),
        ]);
    }

    public function test_guests_and_legacy_flags_cannot_access_any_personal_endpoint(): void
    {
        $routes = ['seller.profile', 'seller.profile.update', 'seller.profile.photo.update', 'seller.profile.photo.remove', 'seller.profile.address.update'];
        foreach ($routes as $route) {
            $this->flushSession();
            $this->withSession(['seller_logged_in' => true]);
            ($route === 'seller.profile' ? $this->get(route($route)) : $this->post(route($route)))
                ->assertRedirect(route('login'));
        }
    }

    public function test_other_roles_and_inactive_owners_are_denied(): void
    {
        foreach (['buyer', 'admin', 'superadmin', 'rider', 'logistics'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get(route('seller.profile'))->assertForbidden();
            $this->post(route('seller.profile.update'), $this->info())->assertForbidden();
            $this->post(route('seller.profile.photo.remove'))->assertForbidden();
        }
        $shop = $this->shop();
        $shop->owner->update(['is_suspended' => true]);
        $this->actingAs($shop->owner->fresh())->get(route('seller.profile'))->assertForbidden();
    }

    public function test_pending_access_is_local_only_and_does_not_change_approval(): void
    {
        $shop = $this->shop('pending');
        $this->app->instance('env', 'local');
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->actingAs($shop->owner)->get(route('seller.profile'))->assertOk()->assertSee('Pending');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $personal = $xpath->query('//dt[text()="Personal account status"]/following-sibling::dd/span')->item(0);
        $approval = $xpath->query('//dt[text()="Store approval status"]/following-sibling::dd/span')->item(0);
        $this->assertSame('Active', trim($personal->textContent));
        $this->assertStringContainsString('bg-emerald-50', $personal->getAttribute('class'));
        $this->assertSame('Pending', trim($approval->textContent));
        $this->assertStringContainsString('bg-amber-50', $approval->getAttribute('class'));
        $this->post(route('seller.profile.update'), $this->info())->assertSessionHasNoErrors();
        foreach (['production', 'staging'] as $environment) {
            $this->app->instance('env', $environment);
            config(['app.debug' => true]);
            $this->get(route('seller.profile'))->assertForbidden();
            $this->post(route('seller.profile.update'), $this->info())->assertForbidden();
        }
        $this->assertSame('pending', $shop->fresh()->status);
    }

    public function test_personal_seller_id_uses_owner_key_and_navigation_links_share_the_destination(): void
    {
        User::factory()->create();
        $shop = $this->shop();
        $this->assertNotSame($shop->id, $shop->user_id);
        $code = 'SLR-'.str_pad((string) $shop->user_id, 5, '0', STR_PAD_LEFT);
        $this->assertSame($code, $shop->owner->seller_code);
        $this->assertSame($code, $shop->owner->fresh()->seller_code);
        $response = $this->actingAs($shop->owner)->get(route('seller.profile'))->assertOk()
            ->assertSee('id="sellerId" readonly value="'.$code.'"', false)
            ->assertSee($shop->owner->email)->assertSee($shop->owner->phone)->assertSee('Fixture Owner')
            ->assertDontSee($shop->owner->password);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertFalse($xpath->query('//input[@id="sellerId"]')->item(0)->hasAttribute('name'));
        $this->assertCount(1, $xpath->query('//nav[@aria-label="Seller navigation"]//a[@aria-current="page"][@href="'.route('seller.profile').'"]'));
        $this->assertCount(1, $xpath->query('//*[@id="sellerAccountPanel"]//a[@href="'.route('seller.profile').'"]'));
        $this->assertCount(1, $xpath->query('//form[@action="'.route('seller.store.password.update').'"]'));
        $storeResponse = $this->get(route('seller.store.profile'))->assertOk()->assertSee($shop->shop_code)
            ->assertDontSee('Password management is now in My Profile.')
            ->assertDontSee('Open account security');
        $storeDom = new \DOMDocument;
        @$storeDom->loadHTML($storeResponse->getContent());
        $storeXpath = new \DOMXPath($storeDom);
        $this->assertCount(0, $storeXpath->query('//section[@id="security"]'));
        $this->assertCount(0, $storeXpath->query('//a[@href="'.route('seller.profile').'#security"]'));
        $this->assertCount(0, $storeXpath->query('//form[@action="'.route('seller.store.password.update').'"]'));
    }

    public function test_personal_updates_ignore_ids_roles_status_and_store_fields(): void
    {
        $shop = $this->shop();
        $other = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.profile.update'), $this->info([
            'user_id' => $other->user_id, 'id' => $other->user_id, 'seller_id' => $other->id, 'seller_code' => 'FORGED',
            'role' => 'admin', 'status' => 'approved', 'is_suspended' => true,
            'shop_name' => 'Changed store', 'business_category' => 'Changed category', 'tin' => '123456789012',
            'commission_bps' => 0, 'profile_photo_path' => 'someone-elses-photo.png',
        ]))->assertRedirect(route('seller.profile'))->assertSessionHasNoErrors();
        $saved = $shop->owner->fresh();
        $this->assertSame('Updated A Owner', $saved->name);
        $this->assertSame('09181234567', $saved->phone);
        $this->assertSame('seller', $saved->role);
        $this->assertSame('active', $saved->status);
        $this->assertFalse($saved->is_suspended);
        $this->assertNull($saved->profile_photo_path);
        $this->assertSame($shop->owner->email, $saved->email);
        $this->assertSame($shop->getAttributes(), $shop->fresh()->getAttributes());
        $this->assertSame($shop->pickupAddress->getAttributes(), $shop->pickupAddress->fresh()->getAttributes());
        $this->assertSame('Fixture Owner', $other->owner->fresh()->name);
        $this->get(route('seller.profile'))->assertOk()->assertSee('Updated A Owner');
    }

    public function test_invalid_names_and_phones_preserve_data_and_old_input(): void
    {
        $shop = $this->shop();
        $this->actingAs($shop->owner)->from(route('seller.profile'))->post(route('seller.profile.update'), $this->info([
            'first_name' => 'Name123', 'last_name' => '', 'middle_initial' => '12', 'phone' => 'invalid',
        ]))->assertSessionHasErrors(['first_name', 'last_name', 'middle_initial', 'phone'])->assertSessionHasInput('first_name', 'Name123');
        $this->assertSame('Fixture Owner', $shop->owner->fresh()->name);
        $this->post(route('seller.profile.update'), $this->info(['phone' => ['invalid']]))->assertSessionHasErrors('phone');
    }

    public function test_duplicate_phone_in_either_format_is_rejected(): void
    {
        $shop = $this->shop();
        $other = $this->shop();
        foreach ([$other->owner->phone, '+63'.substr($other->owner->phone, 1)] as $phone) {
            $this->actingAs($shop->owner)->post(route('seller.profile.update'), $this->info(['phone' => $phone]))->assertSessionHasErrors('phone');
        }
    }

    public function test_email_remains_read_only_and_crafted_changes_do_not_bypass_verification(): void
    {
        $shop = $this->shop();
        $other = $this->shop();
        $timestamp = $shop->owner->email_verified_at;
        foreach ([$other->owner->email, 'new-address@example.test'] as $email) {
            $this->actingAs($shop->owner)->post(route('seller.profile.update'), $this->info(['email' => $email]))->assertSessionHasErrors('email');
        }
        $this->assertSame($shop->owner->email, $shop->owner->fresh()->email);
        $this->assertEquals($timestamp, $shop->owner->fresh()->email_verified_at);
        $this->get(route('seller.profile'))->assertSee('id="personalEmail" type="email" readonly', false);
    }

    public function test_photo_upload_replace_remove_preserves_store_images_and_other_users(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $other = $this->shop();
        $shop->update(['logo_path' => 'store/logo.png', 'banner_path' => 'store/banner.png']);
        $this->actingAs($shop->owner)->post(route('seller.profile.photo.update'), ['photo' => $this->photo(), 'user_id' => $other->user_id])->assertSessionHasNoErrors();
        $first = $shop->owner->fresh()->profile_photo_path;
        Storage::disk('public')->assertExists($first);
        $this->assertStringStartsWith('seller-accounts/'.$shop->user_id.'/photos/', $first);
        $this->post(route('seller.profile.photo.update'), ['photo' => $this->photo()])->assertSessionHasNoErrors();
        $second = $shop->owner->fresh()->profile_photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->post(route('seller.profile.photo.remove'), ['user_id' => $other->user_id])->assertRedirect();
        $this->assertNull($shop->owner->fresh()->profile_photo_path);
        $this->assertNull($other->owner->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($second);
        $this->assertSame('store/logo.png', $shop->fresh()->logo_path);
        $this->assertSame('store/banner.png', $shop->fresh()->banner_path);
    }

    public function test_invalid_or_oversized_photo_does_not_remove_existing_photo(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.profile.photo.update'), ['photo' => $this->photo()])->assertSessionHasNoErrors();
        $path = $shop->owner->fresh()->profile_photo_path;
        foreach ([UploadedFile::fake()->create('payload.php', 1, 'text/plain'), $this->photo()->size(2049)] as $file) {
            $this->post(route('seller.profile.photo.update'), ['photo' => $file])->assertSessionHasErrors('photo');
            Storage::disk('public')->assertExists($path);
            $this->assertSame($path, $shop->owner->fresh()->profile_photo_path);
        }
    }

    public function test_shared_personal_photos_are_not_deleted(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $other = $this->shop();
        $path = 'seller-accounts/'.$shop->user_id.'/photos/shared.png';
        Storage::disk('public')->put($path, 'shared');
        $shop->owner->profile_photo_path = $path;
        $shop->owner->save();
        $other->owner->profile_photo_path = $path;
        $other->owner->save();
        $this->actingAs($shop->owner)->post(route('seller.profile.photo.remove'))->assertRedirect();
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $other->owner->fresh()->profile_photo_path);
    }

    public function test_database_failure_rolls_back_photo_reference_and_cleans_new_file(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        DB::statement("CREATE TRIGGER reject_personal_photo BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->actingAs($shop->owner)->post(route('seller.profile.photo.update'), ['photo' => $this->photo()])->assertStatus(500);
        $this->assertNull($shop->owner->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_personal_name_phone_and_photo_survive_new_login_and_use_active_site_url(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.profile.update'), $this->info())->assertSessionHasNoErrors();
        $this->post(route('seller.profile.photo.update'), ['photo' => $this->photo()])->assertSessionHasNoErrors();
        $path = $shop->owner->fresh()->profile_photo_path;
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->flushSession();
        $this->post(route('login.submit'), ['login' => '09181234567', 'password' => 'Old-pass-123'])->assertRedirect(route('seller.dashboard'));
        $this->get('http://127.0.0.1:8123/seller/my-profile')->assertOk()->assertSee('Updated A Owner')
            ->assertSee('src="http://127.0.0.1:8123/storage/'.$path.'"', false);
        $this->get(route('seller.profile'))->assertOk()->assertSee('09181234567');
        Storage::disk('public')->assertExists($path);
    }

    public function test_personal_address_is_distinct_from_pickup_and_persists_validated_locations(): void
    {
        $this->locations();
        $shop = $this->shop();
        $other = $this->shop();
        $this->actingAs($shop->owner)->get(route('seller.profile'))->assertViewHas('personalAddress', null);
        $this->post(route('seller.profile.address.update'), $this->address(['address_id' => $other->pickup_address_id, 'user_id' => $other->user_id]))->assertSessionHasNoErrors();
        $home = $shop->owner->addresses()->where('label', 'Home')->sole();
        $this->assertSame('Personal home', $home->line1);
        $this->assertSame('City of Biñan', $home->city);
        $this->assertSame('Store pickup', $shop->pickupAddress->fresh()->line1);
        $this->assertSame('Store pickup', $other->pickupAddress->fresh()->line1);
        $this->assertSame($shop->pickup_address_id, $shop->fresh()->pickup_address_id);
        $this->post(route('seller.profile.address.update'), $this->address(['address' => 'Changed personal home']))->assertSessionHasNoErrors();
        $this->assertSame(1, $shop->owner->addresses()->where('label', 'Home')->count());
        $this->assertSame('Changed personal home', $home->fresh()->line1);
        $this->get(route('seller.profile'))->assertOk()->assertSee('Changed personal home');
    }

    public function test_home_record_referenced_as_pickup_is_never_modified_by_personal_address_updates(): void
    {
        $this->locations();
        $shop = $this->shop();
        $shop->pickupAddress->update(['label' => 'Home']);
        $this->actingAs($shop->owner)->post(route('seller.profile.address.update'), $this->address())->assertSessionHasNoErrors();
        $this->assertSame('Store pickup', $shop->pickupAddress->fresh()->line1);
        $this->assertSame(2, $shop->owner->addresses()->where('label', 'Home')->count());
        $this->get(route('seller.profile'))->assertOk()->assertViewHas('personalAddress', fn ($address) => $address->id !== $shop->pickup_address_id);
    }

    public function test_invalid_locations_and_upstream_failure_preserve_personal_address(): void
    {
        $this->locations();
        $shop = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.profile.address.update'), $this->address())->assertSessionHasNoErrors();
        foreach (['region', 'province', 'municipality', 'barangay'] as $field) {
            $this->post(route('seller.profile.address.update'), $this->address([$field => '9999999999']))->assertSessionHasErrors($field);
        }
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 503)]);
        $this->post(route('seller.profile.address.update'), $this->address())->assertSessionHasErrors('region');
        $this->assertSame('Personal home', $shop->owner->addresses()->where('label', 'Home')->sole()->line1);
    }

    public function test_existing_password_route_updates_only_owner_and_redirects_to_personal_security(): void
    {
        $shop = $this->shop();
        $other = $this->shop();
        $this->actingAs($shop->owner)->post(route('seller.store.password.update'), [
            'current_password' => 'wrong', 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123',
        ])->assertSessionHasErrors('current_password');
        $this->post(route('seller.store.password.update'), [
            'current_password' => 'Old-pass-123', 'password' => 'New-pass-123', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
        $this->post(route('seller.store.password.update'), [
            'current_password' => 'Old-pass-123', 'password' => 'New-pass-123', 'password_confirmation' => 'New-pass-123',
            'user_id' => $other->user_id,
        ])->assertRedirect(route('seller.profile').'#security');
        $this->assertTrue(Hash::check('New-pass-123', $shop->owner->fresh()->password));
        $this->assertNotSame('New-pass-123', $shop->owner->fresh()->password);
        $this->assertTrue(Hash::check('Old-pass-123', $other->owner->fresh()->password));
        $this->post(route('logout'));
        $this->post(route('login.submit'), ['login' => $shop->owner->email, 'password' => 'New-pass-123'])->assertRedirect(route('seller.dashboard'));
    }

    public function test_personal_update_routes_keep_csrf_protection(): void
    {
        $shop = $this->shop();
        // Simulate a normal production request so Laravel's test-only CSRF exemption is off.
        $this->app->instance('env', 'production');
        $this->actingAs($shop->owner)->post(route('seller.profile.update'), $this->info())->assertStatus(419);
        $this->assertSame('Fixture Owner', $shop->owner->fresh()->name);
        $this->withSession(['_token' => 'profile-test-token'])->post(route('seller.profile.update'), $this->info(['_token' => 'profile-test-token']))
            ->assertRedirect(route('seller.profile'));
    }

    public function test_failed_photo_removal_preserves_existing_personal_photo(): void
    {
        Storage::fake('public');
        $shop = $this->shop();
        $path = 'seller-accounts/'.$shop->user_id.'/photos/existing.png';
        Storage::disk('public')->put($path, 'existing');
        $shop->owner->profile_photo_path = $path;
        $shop->owner->save();
        DB::statement("CREATE TRIGGER reject_personal_removal BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->actingAs($shop->owner)->post(route('seller.profile.photo.remove'))->assertStatus(500);
        $this->assertSame($path, $shop->owner->fresh()->profile_photo_path);
        Storage::disk('public')->assertExists($path);
    }
}
