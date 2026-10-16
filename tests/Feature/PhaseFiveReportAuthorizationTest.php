<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_open_admin_report_or_export(): void
    {
        $buyer = $this->user('buyer');
        $this->actingAs($buyer)->get(route('admin.reports'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.reports', ['export'=>1]))->assertForbidden();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role'=>$role, 'status'=>'active']);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>$role])->id]);
        return $user;
    }
}
