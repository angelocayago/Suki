<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $admin = User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'QA Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'is_suspended' => false,
            ]
        );

        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
    }
}
