<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUtilitiesPolicyHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_can_manage_safe_settings_and_policy_keys(): void
    {
        $admin = $this->activeUser('admin');
        $buyer = $this->activeUser('buyer');
        Setting::create(['key' => 'default_commission_rate', 'value' => '10', 'type' => 'text']);

        $this->actingAs($buyer)->patch(route('admin.settings.platform'), [
            'platform_name' => 'Unsafe', 'support_email' => 'support@example.test',
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('admin.settings.platform'), [
            'platform_name' => 'SUKI Support',
            'support_email' => 'support@example.test',
            'contact_number' => '09123456789',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('admin.settings.policies'), [
            'terms_of_service' => '<script>alert(1)</script> Terms',
            'privacy_policy' => 'Privacy content',
            'shipping_policy' => 'Shipping content',
            'returns_refund_policy' => 'Returns content',
            'default_commission_rate' => '99',
        ])->assertRedirect();

        $this->assertSame('SUKI Support', Setting::where('key', 'platform_name')->value('value'));
        $this->assertSame('10', Setting::where('key', 'default_commission_rate')->value('value'));
        $this->assertSame('Returns content', Setting::where('key', 'returns_refund_policy')->value('value'));
        $this->get(route('policies.show', 'terms-of-service'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Terms', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_announcements_are_admin_managed_and_only_active_items_appear_in_buyer_help(): void
    {
        $admin = $this->activeUser('admin');
        $buyer = $this->activeUser('buyer');
        $other = $this->activeUser('seller');

        $this->actingAs($other)->post(route('admin.settings.announcement.store'), [
            'title' => 'Nope', 'message' => 'Nope',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.settings.announcement.store'), [
            'title' => 'Delivery notice', 'message' => '<script>unsafe()</script>',
        ])->assertRedirect();
        $announcement = Announcement::firstOrFail();

        $this->actingAs($admin)->patch(route('admin.settings.announcement.update', $announcement), [
            'title' => 'Delivery notice updated',
            'message' => '<script>unsafe()</script>',
            'status' => 'active',
        ])->assertRedirect();
        $announcement->refresh();
        $this->assertSame('Delivery notice updated', $announcement->title);

        $this->actingAs($buyer)->get(route('buyer.help'))->assertOk()
            ->assertSee('Delivery notice updated')
            ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)
            ->assertDontSee('<script>unsafe()</script>', false);

        $this->actingAs($admin)->patch(route('admin.settings.announcement.deactivate', $announcement))->assertRedirect();
        $this->assertSame('inactive', $announcement->fresh()->status);
        $this->actingAs($buyer)->get(route('buyer.help'))->assertDontSee('Delivery notice');
    }

    public function test_buyer_help_reuses_complaints_instead_of_creating_support_records(): void
    {
        $buyer = $this->activeUser('buyer');

        $this->actingAs($buyer)->get(route('buyer.help'))->assertOk()
            ->assertSee(route('complaints.mine.index'), false)
            ->assertSee(route('buyer.my-orders'), false);
        $this->assertDatabaseCount('complaints', 0);
        $this->get(route('policies.show', 'shipping-policy'))->assertOk();
        $this->app['auth']->logout();
        $this->get(route('buyer.help'))->assertRedirect(route('login'));
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
            'is_suspended' => false,
        ]);
    }
}
