<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\Support\IsolatedStorageTestCase;

class BuyerAccountProfileTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        Storage::fake('public'); Http::preventStrayRequests();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false,
            'first_name' => 'Current', 'last_name' => 'Buyer', 'name' => 'Current Buyer',
            'email' => 'current@example.test', 'phone' => '09171234567', 'birthday' => null]);
        $this->actingAs($this->buyer);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    private function fields(): array
    {
        return ['first_name' => 'Current', 'last_name' => 'Buyer', 'email' => 'current@example.test', 'phone' => '09171234567'];
    }

    public function test_current_database_values_override_every_outdated_login_session_field(): void
    {
        $page = $this->withSession(['buyer_profile' => ['first_name' => 'Obsolete', 'last_name' => 'Snapshot', 'email' => 'obsolete@example.test', 'phone' => '09999999999']])
            ->get(route('buyer.account'))->assertOk();
        $xpath = $this->xpath($page->getContent());
        foreach ($this->fields() as $field => $value) {
            $this->assertSame($value, trim($xpath->query('//*[@data-profile-field="'.$field.'"]')->item(0)->textContent));
            $this->assertSame($value, $xpath->query('//*[@id="editProfileModal"]//input[@name="'.$field.'"]')->item(0)->getAttribute('value'));
        }
        $this->assertSame('Current Buyer', trim($xpath->query('//*[@data-profile-name]')->item(0)->textContent));
        $page->assertDontSee('obsolete@example.test')->assertDontSee('09999999999');
        $this->assertSame(1, $xpath->query('//*[@data-buyer-account-sidebar]')->length);
        $this->assertSame('Profile', trim($xpath->query('//nav[@aria-label="My Account"]//a[@aria-current="page"]')->item(0)->textContent));
    }

    public function test_missing_legacy_session_uses_saved_values_without_example_identity(): void
    {
        $this->assertFalse(session()->has('buyer_profile'));
        $page = $this->get(route('buyer.account'))->assertOk()->assertSee('Current Buyer')->assertSee('current@example.test')->assertSee('09171234567')
            ->assertDontSee('buyer@suki.com')->assertDontSee('juan@example.com');
        $this->assertFalse(session()->has('buyer_profile'), 'Rendering must not create a second profile snapshot.');
        $xpath = $this->xpath($page->getContent());
        $this->assertSame(3, $xpath->query('//*[@aria-label="Current Buyer avatar"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="editProfileModal"]//form')->length);
    }

    public function test_saved_updates_display_immediately_and_on_reload_even_with_stale_session(): void
    {
        $this->withSession(['buyer_profile' => $this->fields()]);
        $data = ['first_name' => 'Updated', 'last_name' => 'Person', 'email' => 'updated@example.test', 'phone' => '09179876543'];
        $this->post(route('buyer.account.update'), $data)->assertRedirect(route('buyer.account'))->assertSessionHas('success');
        foreach ([1, 2] as $reload) {
            $page = $this->get(route('buyer.account'))->assertOk()->assertSee('Updated Person');
            $xpath = $this->xpath($page->getContent());
            foreach ($data as $field => $value) {
                $this->assertSame($value, trim($xpath->query('//*[@data-profile-field="'.$field.'"]')->item(0)->textContent));
                $this->assertSame($value, $xpath->query('//*[@id="editProfileModal"]//input[@name="'.$field.'"]')->item(0)->getAttribute('value'));
            }
        }
        $this->assertDatabaseHas('users', ['id' => $this->buyer->id, 'name' => 'Updated Person'] + $data);
        $this->assertSame('Current', session('buyer_profile.first_name'), 'The Blade fix must not change the legacy session persistence contract.');
    }

    public function test_validation_recovers_submitted_fields_with_errors_while_saved_summary_stays_authoritative(): void
    {
        $data = ['first_name' => 'Attempted', 'last_name' => '', 'email' => 'invalid-email', 'phone' => ''];
        $this->from(route('buyer.account'))->post(route('buyer.account.update'), $data)->assertRedirect(route('buyer.account'))->assertSessionHasErrors(['last_name', 'email', 'phone']);
        $page = $this->get(route('buyer.account'))->assertOk();
        $xpath = $this->xpath($page->getContent());
        $this->assertSame('Current Buyer', trim($xpath->query('//*[@data-profile-name]')->item(0)->textContent));
        $this->assertSame('true', $xpath->query('//*[@id="editProfileModal"]')->item(0)->getAttribute('data-profile-errors'));
        foreach ($data as $field => $value) {
            $input = $xpath->query('//*[@id="editProfileModal"]//input[@name="'.$field.'"]')->item(0);
            $this->assertSame($value, $input->getAttribute('value'));
            $this->assertSame('edit-profile-'.$field.'-error', $input->getAttribute('aria-describedby'));
            $this->assertSame($field === 'first_name' ? 'false' : 'true', $input->getAttribute('aria-invalid'));
            if ($field !== 'first_name') $this->assertNotSame('', trim($xpath->query('//*[@id="edit-profile-'.$field.'-error"]')->item(0)->textContent));
        }
        $this->assertDatabaseHas('users', ['id' => $this->buyer->id, 'name' => 'Current Buyer', 'email' => 'current@example.test']);
    }

    public function test_email_phone_uniqueness_and_self_update_exclusion_remain_intact(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.test', 'phone' => '09170000000']);
        $this->from(route('buyer.account'))->post(route('buyer.account.update'), array_replace($this->fields(), ['email' => $other->email, 'phone' => $other->phone]))
            ->assertRedirect(route('buyer.account'))->assertSessionHasErrors(['email', 'phone']);
        $this->assertDatabaseHas('users', ['id' => $this->buyer->id, 'email' => 'current@example.test', 'phone' => '09171234567']);
        $this->get(route('buyer.account'))->assertOk();
        $this->post(route('buyer.account.update'), $this->fields())->assertRedirect(route('buyer.account'))->assertSessionHasNoErrors();
    }

    public function test_all_existing_editor_fields_remain_editable_with_same_post_and_csrf(): void
    {
        $page = $this->get(route('buyer.account'))->assertOk(); $xpath = $this->xpath($page->getContent());
        $form = $xpath->query('//*[@id="editProfileModal"]//form')->item(0);
        $this->assertSame(route('buyer.account.update'), $form->getAttribute('action'));
        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);
        $this->assertSame(4, $xpath->query('.//input[@required]', $form)->length);
        $this->assertSame(0, $xpath->query('.//input[@readonly or @disabled or @type="file"]', $form)->length);
        foreach (array_keys($this->fields()) as $field) $this->assertSame(1, $xpath->query('.//input[@name="'.$field.'"]', $form)->length);
        $page->assertSee('onclick="openEditProfile()"', false)->assertSee('#editProfileModal', false);
    }

    public function test_profile_edits_leave_verification_status_role_and_sensitive_fields_unchanged(): void
    {
        $this->buyer->update(['verification_required_at' => now(), 'email_verified_at' => null, 'phone_verified_at' => null, 'government_id' => 'private-identity-fixture.jpg']);
        $this->assertFalse($this->buyer->hasVerifiedContacts());
        $before = $this->buyer->fresh()->only(['role', 'status', 'is_suspended', 'government_id', 'verification_required_at', 'email_verified_at', 'phone_verified_at']);
        $this->get(route('buyer.account'))->assertOk(); // Preserve existing account-route policy; do not implement P5-01.
        $this->post(route('buyer.account.update'), $this->fields() + ['status' => 'inactive', 'role' => 'admin', 'email_verified_at' => now()->toISOString(), 'birthday' => '2000-01-01'])
            ->assertRedirect(route('buyer.account'))->assertSessionHasNoErrors();
        $this->assertEquals($before, $this->buyer->fresh()->only(array_keys($before)));
        $this->assertNull($this->buyer->fresh()->birthday);
        $page = $this->get(route('buyer.account'))->assertOk()->assertDontSee('private-identity-fixture.jpg')->assertDontSee('government-ID verified');
        $xpath = $this->xpath($page->getContent());
        $this->assertStringNotContainsString('verified', strtolower($xpath->query('//*[@data-profile-details]')->item(0)->textContent));
    }

    public function test_account_metadata_uses_stored_birthday_status_and_manila_member_date(): void
    {
        $this->buyer->forceFill(['birthday' => '1998-02-06', 'created_at' => \Carbon\CarbonImmutable::parse('2020-10-01 19:30:00', 'UTC')])->save();
        $page = $this->get(route('buyer.account'))->assertOk(); $xpath = $this->xpath($page->getContent());
        $this->assertSame('February 6, 1998', trim($xpath->query('//*[@data-profile-birthday]')->item(0)->textContent));
        $this->assertSame('October 2, 2020', trim($xpath->query('//*[@data-profile-member-since]')->item(0)->textContent));
        $this->assertSame('Active', trim($xpath->query('//*[@data-profile-status]')->item(0)->textContent));
    }

    public function test_absent_optional_values_have_honest_empty_states_and_no_fake_photo_actions(): void
    {
        $this->buyer->forceFill(['birthday' => null, 'created_at' => null, 'phone' => null])->save();
        $page = $this->get(route('buyer.account'))->assertOk()->assertDontSee('data-profile-birthday', false)->assertDontSee('Change Photo')->assertDontSee('Remove Photo');
        $xpath = $this->xpath($page->getContent());
        $this->assertSame('Not available', trim($xpath->query('//*[@data-profile-member-since]')->item(0)->textContent));
        $this->assertSame('Not provided', trim($xpath->query('//*[@data-profile-field="phone"]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//*[@data-profile-details]//img|//*[@id="editProfileModal"]//input[@type="file"]')->length);
    }

    public function test_password_section_remains_separate_and_does_not_repopulate_password_fields(): void
    {
        $url = route('buyer.account', ['section' => 'password']);
        $this->from($url)->post(route('buyer.account.password'), ['current_password' => 'wrong', 'password' => 'invalid', 'password_confirmation' => 'different'])->assertRedirect($url)->assertSessionHasErrors();
        $page = $this->get($url)->assertOk(); $xpath = $this->xpath($page->getContent());
        $this->assertSame(1, $xpath->query('//form[@data-account-password-form]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-profile-name]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-account-password-form]//input[@type="password" and @value]')->length);
        $this->assertSame('false', $xpath->query('//*[@id="editProfileModal"]')->item(0)->getAttribute('data-profile-errors'));
        $this->assertSame('Change Password', trim($xpath->query('//nav[@aria-label="My Account"]//a[@aria-current="page"]')->item(0)->textContent));
    }
}
