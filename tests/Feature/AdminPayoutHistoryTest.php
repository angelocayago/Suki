<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Seller withdrawals are disabled; the Admin payout page must render read-only. */
class AdminPayoutHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_payout_history_renders_pending_rows_without_dead_review_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_suspended' => false]);
        $admin->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'admin'])->id]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Payout Shop', 'slug' => 'payout-shop', 'status' => 'approved']);
        foreach (['PENDING', 'APPROVED'] as $status) {
            DB::table('payouts')->insert(['seller_id' => $seller->id, 'amount_minor' => 12345, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->actingAs($admin)->get(route('admin.payouts.index'))->assertOk()
            ->assertSee('Payout Shop')->assertSee('123.45')
            ->assertSee('Read-only — settled offline by Logistics')
            ->assertDontSee('Mark paid')->assertDontSee('name="status"', false);
        $this->assertSame(2, Payout::query()->count());
    }

    public function test_admin_payout_history_has_an_empty_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_suspended' => false]);
        $admin->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'admin'])->id]);
        $this->actingAs($admin)->get(route('admin.payouts.index'))->assertOk()->assertSee('No seller payout records.');
    }
}
