<?php

namespace Tests\Feature;

use App\Models\{Address, CartItem, Category, Product, Role, Seller, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Blade, Hash, Http, Storage};
use Tests\Support\{FakesCheckoutLocations, IsolatedStorageTestCase};

class BuyerAccountPresentationTest extends IsolatedStorageTestCase
{
    use RefreshDatabase, FakesCheckoutLocations;

    private User $buyer;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        Storage::fake('public');
        Http::preventStrayRequests();
        $this->fakeCheckoutLocations();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false,
            'first_name' => 'Actual', 'last_name' => 'Buyer', 'name' => 'Actual Buyer', 'password' => 'password']);
        $this->actingAs($this->buyer);
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($document);
    }

    private function destinations(): array
    {
        return ['Profile' => route('buyer.account'), 'Addresses' => route('buyer.addresses'),
            'Change Password' => route('buyer.account', ['section' => 'password']),
            'My Purchases' => route('buyer.my-orders'), 'My Wishlist' => route('buyer.wishlist'),
            'Following' => route('buyer.stores.following'), 'Messages' => route('buyer.messages.index')];
    }

    public function test_account_has_one_sidebar_with_real_identity_seven_routes_and_active_profile(): void
    {
        $page = $this->get(route('buyer.account'))->assertOk();
        $xpath = $this->xpath($page->getContent());
        $this->assertSame(1, $xpath->query('//*[@data-buyer-account-shell]')->length);
        $this->assertSame(1, $xpath->query('//aside[@data-buyer-account-sidebar]')->length);
        $nav = $xpath->query('//nav[@aria-label="My Account"]')->item(0);
        $this->assertNotNull($nav);
        $links = [];
        foreach ($xpath->query('.//a', $nav) as $link) $links[trim($link->textContent)] = $link->getAttribute('href');
        $this->assertSame($this->destinations(), $links);
        $this->assertSame(1, $xpath->query('.//a[@aria-current="page"]', $nav)->length);
        $this->assertSame(route('buyer.account'), $xpath->query('.//a[@aria-current="page"]', $nav)->item(0)->getAttribute('href'));
        $this->assertStringContainsString('Actual Buyer', $xpath->query('//aside[@data-buyer-account-sidebar]')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//aside[@data-buyer-account-sidebar]//*[@aria-label="Actual Buyer avatar"]')->length);
        $this->assertSame(1, $xpath->query('//details[@data-buyer-account-navigation]/summary')->length);
        $this->assertSame(0, $xpath->query('//form/ancestor::form')->length);
    }

    public function test_reusable_sidebar_marks_each_active_section_without_claiming_other_pages_are_integrated(): void
    {
        foreach (['profile', 'addresses', 'password', 'purchases', 'wishlist', 'following', 'messages'] as $index => $active) {
            $html = Blade::render('<x-buyer.account-sidebar :active="$active" />', compact('active'));
            $xpath = $this->xpath($html);
            $links = $xpath->query('//nav[@aria-label="My Account"]//a[@aria-current="page"]');
            $this->assertSame(1, $links->length);
            $this->assertSame(array_values($this->destinations())[$index], $links->item(0)->getAttribute('href'));
        }
    }

    public function test_profile_bindings_and_edit_modal_remain_while_password_uses_the_same_native_post_inline(): void
    {
        $page = $this->withSession(['buyer_profile' => ['first_name' => 'Session', 'last_name' => 'Snapshot', 'email' => 'snapshot@example.test', 'phone' => '09123456789']])
            ->get(route('buyer.account'))->assertOk();
        $xpath = $this->xpath($page->getContent());
        $this->assertSame($this->buyer->first_name, $xpath->query('//*[@id="editProfileModal"]//input[@name="first_name"]')->item(0)->getAttribute('value'));
        $this->assertSame($this->buyer->email, $xpath->query('//*[@id="editProfileModal"]//input[@name="email"]')->item(0)->getAttribute('value'));
        $page->assertDontSee('snapshot@example.test');
        $profileForm = $xpath->query('//*[@id="editProfileModal"]//form')->item(0);
        $passwordPage = $this->get(route('buyer.account', ['section' => 'password']))->assertOk();
        $passwordXpath = $this->xpath($passwordPage->getContent());
        $passwordForm = $passwordXpath->query('//form[@data-account-password-form]')->item(0);
        foreach ([[$profileForm, 'buyer.account.update', $xpath], [$passwordForm, 'buyer.account.password', $passwordXpath]] as [$form, $route, $formXpath]) {
            $this->assertSame(route($route), $form->getAttribute('action'));
            $this->assertSame('POST', $form->getAttribute('method'));
            $this->assertSame(1, $formXpath->query('.//input[@name="_token"]', $form)->length);
        }
        $page->assertSee('onclick="openEditProfile()"', false);
        $this->assertSame(1, $xpath->query('//aside[@data-buyer-account-sidebar]//a[@href="'.route('buyer.account').'#editProfileModal"]')->length);
        $this->assertSame(0, $passwordXpath->query('//*[@id="passwordModal"]')->length);
        $this->assertSame(1, $passwordXpath->query('//form[@action="'.route('buyer.account.password').'"]')->length);
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertSame(1, $passwordXpath->query('//form[@data-account-password-form]//input[@type="password" and @name="'.$field.'"]')->length);
        }
    }

    public function test_existing_profile_update_and_password_mutations_still_persist_without_policy_changes(): void
    {
        $this->post(route('buyer.account.update'), ['first_name' => 'Updated', 'last_name' => 'Buyer', 'email' => 'updated@example.test', 'phone' => '09179876543'])
            ->assertRedirect(route('buyer.account'))->assertSessionHas('success');
        $this->assertSame('Updated Buyer', $this->buyer->fresh()->name);
        $this->post(route('buyer.account.password'), ['current_password' => 'wrong', 'password' => 'replacement123', 'password_confirmation' => 'replacement123'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $this->buyer->fresh()->password));
        $this->post(route('buyer.account.password'), ['current_password' => 'password', 'password' => 'replacement123', 'password_confirmation' => 'replacement123'])
            ->assertRedirect(route('buyer.account'))->assertSessionHas('success');
        $this->assertTrue(Hash::check('replacement123', $this->buyer->fresh()->password));
        $this->assertSame('buyer', $this->buyer->fresh()->role);
    }

    public function test_application_links_and_account_dropdown_remain_and_horizontal_cleanup_is_preserved(): void
    {
        $page = $this->get(route('buyer.account'))->assertOk();
        $xpath = $this->xpath($page->getContent());
        foreach (['Become a Seller', 'Apply as Rider'] as $label) {
            $links = $xpath->query('//*[@data-buyer-account-shell]//a[normalize-space(.)="'.$label.'"]');
            $this->assertSame(1, $links->length);
            $this->assertSame(route('register'), $links->item(0)->getAttribute('href'));
        }
        foreach (['buyer.account', 'buyer.my-orders', 'buyer.return-refunds.index', 'buyer.messages.index', 'buyer.vouchers.index', 'notifications.page', 'reviews.buyer-page', 'buyer.wishlist'] as $route) {
            $this->assertSame(1, $xpath->query('//header//div[contains(concat(" ",normalize-space(@class)," ")," invisible ")]//a[@href="'.route($route).'"]')->length);
        }
        $nav = $xpath->query('//header//nav')->item(0);
        foreach (['buyer.return-refunds.index', 'reviews.buyer-page', 'notifications.page'] as $route) $this->assertSame(0, $xpath->query('.//a[@href="'.route($route).'"]', $nav)->length);
        $page->assertSee('data-buyer-notification-count', false)->assertSee('data-catalog-navbar-search', false)->assertSee('data-suki-theme-toggle', false);
    }

    public function test_shell_is_not_injected_into_other_buyer_pages_including_real_checkout(): void
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Fixture Store', 'slug' => 'fixture-store', 'status' => 'approved']);
        $category = Category::create(['name' => 'Fixture', 'slug' => 'fixture', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Fixture Product', 'slug' => 'fixture-product', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $variant = $product->variants()->create(['sku' => 'ACCOUNT-FIXTURE', 'name' => 'Default', 'price_minor' => 10000, 'stock' => 5, 'is_active' => true]);
        $row = CartItem::create(['user_id' => $this->buyer->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 100]);
        Address::create($this->checkoutLocationCodes() + ['user_id' => $this->buyer->id, 'recipient' => 'Fixture Buyer', 'phone' => '09000000000', 'label' => 'Home', 'line1' => 'Fixture street', 'province' => 'Province A', 'city' => 'City A', 'barangay' => 'Barangay A', 'is_default' => true]);
        foreach (['buyer.shop', 'buyer.cart'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('data-buyer-account-shell', false);
        }
        $this->get(route('buyer.product', $product->slug))->assertOk()->assertDontSee('data-buyer-account-sidebar', false);
        $response = $this->post(route('buyer.cart.selection'), ['draft_revision' => 0, 'cart_item_ids' => [$row->id], 'intent' => 'checkout'])->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertDontSee('data-buyer-account-shell', false);
    }

    public function test_unapproved_or_foreign_role_account_cannot_gain_profile_access(): void
    {
        $this->buyer->update(['status' => 'inactive']);
        $this->get(route('buyer.account'))->assertForbidden();
        $this->post(route('buyer.account.update'), [])->assertForbidden();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('buyer.account'))->assertForbidden();
        $this->post(route('buyer.account.update'), ['first_name' => 'Not allowed'])->assertForbidden();
        $this->post(route('buyer.account.password'), [])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sidebar_escapes_long_real_names_and_never_invents_verification_or_a_photo(): void
    {
        $name = str_repeat('LongName', 12).'<script>alert(1)</script>';
        $this->buyer->update(['first_name' => $name, 'last_name' => 'Buyer']);
        $page = $this->get(route('buyer.account'))->assertOk();
        $xpath = $this->xpath($page->getContent());
        $sidebar = $xpath->query('//aside[@data-buyer-account-sidebar]')->item(0);
        $this->assertStringContainsString($name.' Buyer', $sidebar->textContent);
        $this->assertSame(0, $xpath->query('.//script|.//img', $sidebar)->length);
        $this->assertStringNotContainsString('Verified', $sidebar->textContent);
    }

    public function test_all_seven_account_destinations_are_directly_reloadable_with_one_correctly_active_sidebar(): void
    {
        foreach ($this->destinations() as $label => $url) {
            foreach ([1, 2] as $reload) {
                $page = $this->get($url)->assertOk();
                $xpath = $this->xpath($page->getContent());
                $this->assertSame(1, $xpath->query('//*[@data-buyer-account-shell]')->length, $label);
                $this->assertSame(1, $xpath->query('//*[@data-buyer-account-sidebar]')->length, $label);
                $this->assertSame(7, $xpath->query('//nav[@aria-label="My Account"]//a')->length);
                $active = $xpath->query('//nav[@aria-label="My Account"]//a[@aria-current="page"]');
                $this->assertSame(1, $active->length);
                $this->assertSame($label, trim($active->item(0)->textContent));
                $this->assertSame($url, $active->item(0)->getAttribute('href'));
            }
        }
    }

    public function test_password_validation_returns_to_inline_section_and_never_refills_passwords(): void
    {
        $url = route('buyer.account', ['section' => 'password']);
        foreach ([['current_password' => 'wrong', 'password' => 'replacement123', 'password_confirmation' => 'replacement123'],
            ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'],
            ['current_password' => 'password', 'password' => 'replacement123', 'password_confirmation' => 'mismatch'],
            ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password']] as $data) {
            $this->from($url)->post(route('buyer.account.password'), $data)->assertRedirect($url)->assertSessionHasErrors();
            $page = $this->get($url)->assertOk()->assertSee('role="alert"', false);
            $xpath = $this->xpath($page->getContent());
            $this->assertSame(0, $xpath->query('//form[@data-account-password-form]//input[@type="password" and @value]')->length);
            $this->assertTrue(Hash::check('password', $this->buyer->fresh()->password));
        }
        $this->from($url)->post(route('buyer.account.password'), ['current_password' => 'password', 'password' => 'replacement123', 'password_confirmation' => 'replacement123'])
            ->assertRedirect(route('buyer.account'))->assertSessionHas('success');
        $this->assertTrue(Hash::check('replacement123', $this->buyer->fresh()->password));
    }

    public function test_buyer_message_thread_keeps_its_ids_and_actions_and_seller_context_has_no_account_shell(): void
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $owner->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'buyer'])->id]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Message Store', 'slug' => 'message-store', 'status' => 'approved']);
        $category = Category::create(['name' => 'Messages', 'slug' => 'messages', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Message Product', 'slug' => 'message-product', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $product->variants()->create(['sku' => 'ACCOUNT-MESSAGE', 'name' => 'Default', 'price_minor' => 10000, 'stock' => 5, 'is_active' => true]);
        $messaging = app(\App\Services\MessagingService::class);
        $conversation = $messaging->createProductInquiry($this->buyer, $product, 'Fixture inquiry');
        $messaging->sendMessage($conversation, $owner, 'Fixture reply');
        $page = $this->get(route('buyer.messages.show', $conversation->reference))->assertOk()->assertSee('Fixture reply')
            ->assertSee('id="message-thread"', false)->assertSee('id="messageComposer"', false)
            ->assertSee(route('buyer.messages.poll', $conversation->reference), false)->assertSee(route('buyer.messages.store', $conversation->reference), false);
        $xpath = $this->xpath($page->getContent());
        $this->assertSame(1, $xpath->query('//*[@data-buyer-account-sidebar]')->length);
        $this->assertSame('Messages', trim($xpath->query('//nav[@aria-label="My Account"]//a[@aria-current="page"]')->item(0)->textContent));
        $this->actingAs($owner)->get(route('seller.messages.show', $conversation->reference))->assertOk()->assertSee('Fixture reply')->assertDontSee('data-buyer-account-shell', false)->assertDontSee('data-buyer-account-sidebar', false);
    }

    public function test_disabled_account_shell_is_transparent_for_every_non_buyer_portal(): void
    {
        foreach (['seller', 'rider', 'logistics', 'admin'] as $portal) {
            $html = Blade::render('<x-buyer.account-shell :enabled="$portal === \'buyer\'" active="messages"><div id="existing-message-layout">Unchanged</div></x-buyer.account-shell>', compact('portal'));
            $this->assertSame('<div id="existing-message-layout">Unchanged</div>', trim($html));
        }
    }

    public function test_address_crud_default_selection_and_ownership_survive_the_account_wrapper(): void
    {
        $payload = ['name' => 'Fixture Recipient', 'phone' => '09171234567', 'region' => '01', 'province' => '0101',
            'municipality' => '010101', 'barangay' => '010101001', 'street' => 'First fixture street', 'label' => 'Home'];
        $this->post(route('buyer.addresses.store'), $payload)->assertRedirect(route('buyer.addresses'))->assertSessionHasNoErrors();
        $first = $this->buyer->addresses()->sole();
        $this->assertTrue($first->is_default);
        $this->post(route('buyer.addresses.store'), array_replace($payload, ['street' => 'Second fixture street', 'label' => 'Work']))->assertRedirect(route('buyer.addresses'))->assertSessionHasNoErrors();
        $second = $this->buyer->addresses()->whereKeyNot($first->id)->sole();
        $this->patch(route('buyer.addresses.update', $second->id), array_replace($payload, ['street' => 'Edited fixture street', 'label' => 'Work']))->assertRedirect(route('buyer.addresses'))->assertSessionHasNoErrors();
        $this->assertSame('Edited fixture street', $second->fresh()->line1);
        $this->assertSame('010101001', $second->fresh()->barangay_code);
        $this->post(route('buyer.addresses.default', $second->id))->assertRedirect(route('buyer.addresses'));
        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->get(route('buyer.addresses'))->assertOk()->assertSee('Edited fixture street')->assertSee('data-buyer-account-sidebar', false);
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->post(route('buyer.addresses.delete', $second->id))->assertNotFound();
        $this->assertDatabaseHas('addresses', ['id' => $second->id, 'user_id' => $this->buyer->id]);
        $this->actingAs($this->buyer)->post(route('buyer.addresses.delete', $second->id))->assertRedirect(route('buyer.addresses'));
        $this->assertDatabaseMissing('addresses', ['id' => $second->id]);
        $this->assertTrue($first->fresh()->is_default);
    }
}
