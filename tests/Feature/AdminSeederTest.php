<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_one_active_admin_with_the_admin_role_idempotently(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::query()->where('email', 'admin@test.com')->sole();
        $this->assertSame('QA Admin', $admin->name);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertFalse($admin->is_suspended);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->roles()->whereKey(Role::query()->where('name', 'admin')->sole())->exists());

        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@test.com')->count());
    }
}
