<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed every role supported by the existing users.role and role_user setup.
     */
    public function run(): void
    {
        foreach (['admin', 'buyer', 'logistics', 'rider', 'seller', 'superadmin'] as $role) {
            Role::query()->firstOrCreate(['name' => $role]);
        }
    }
}
