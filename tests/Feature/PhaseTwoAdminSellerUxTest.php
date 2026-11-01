<?php

namespace Tests\Feature;

use App\Models\AccountStatusEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoAdminSellerUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_logout_clears_authentication_and_protects_admin_routes(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post(route('admin.logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_suspend_with_predefined_reason_and_event_is_recorded(): void
    {
        $admin = $this->user('admin');
        $target = $this->user('buyer');
        $this->actingAs($admin)->patch(route('admin.users.status', $target), ['status' => 'suspended', 'reason_code' => 'policy_violation'])
            ->assertRedirect();
        $this->assertSame('suspended', $target->fresh()->status);
        $this->assertDatabaseHas('account_status_events', ['user_id' => $target->id, 'actor_id' => $admin->id, 'new_status' => 'suspended', 'reason' => 'policy_violation']);
    }

    public function test_other_suspension_reason_requires_custom_text_and_non_admin_is_forbidden(): void
    {
        $admin = $this->user('admin');
        $target = $this->user('buyer');
        $this->actingAs($admin)->from(route('admin.users'))->patch(route('admin.users.status', $target), ['status' => 'suspended', 'reason_code' => 'other'])
            ->assertRedirect(route('admin.users'))->assertSessionHasErrors('custom_reason');
        $this->actingAs($this->user('buyer'))->patch(route('admin.users.status', $target), ['status' => 'suspended', 'reason_code' => 'policy_violation'])->assertForbidden();
        $this->assertSame(0, AccountStatusEvent::count());
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active', 'is_suspended' => false]);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);
        return $user;
    }
}
