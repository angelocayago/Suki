<?php

namespace Database\Seeders;

use App\Models\LogisticsProvider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Required roles
            $buyerRole = Role::firstOrCreate([
                'name' => 'buyer',
            ]);

            $sellerRole = Role::firstOrCreate([
                'name' => 'seller',
            ]);

            $logisticsRole = Role::firstOrCreate([
                'name' => 'logistics',
            ]);

            // Buyer account
            $buyer = User::updateOrCreate(
                ['email' => 'buyer@test.com'],
                [
                    'name' => 'Test Buyer',
                    'password' => Hash::make('password'),
                    'role' => 'buyer',
                    'status' => 'active',
                    'is_suspended' => false,
                ]
            );

            $buyer->roles()->syncWithoutDetaching([
                $buyerRole->id,
            ]);

            // Seller account
            $sellerUser = User::updateOrCreate(
                ['email' => 'seller@test.com'],
                [
                    'name' => 'Test Seller',
                    'password' => Hash::make('password'),
                    'role' => 'seller',
                    'status' => 'active',
                    'is_suspended' => false,
                ]
            );

            $sellerUser->roles()->syncWithoutDetaching([
                $sellerRole->id,
            ]);

            Seller::updateOrCreate(
                ['user_id' => $sellerUser->id],
                [
                    'name' => 'Test Seller Store',
                    'slug' => 'test-seller-store',
                    'status' => 'pending',
                    'commission_bps' => 1000,
                ]
            );

            // Logistics account
            $logisticsUser = User::updateOrCreate(
                ['email' => 'logistics@test.com'],
                [
                    'name' => 'Test Logistics',
                    'password' => Hash::make('password'),
                    'role' => 'logistics',
                    'status' => 'active',
                    'is_suspended' => false,
                ]
            );

            $logisticsUser->roles()->syncWithoutDetaching([
                $logisticsRole->id,
            ]);

            LogisticsProvider::updateOrCreate(
                ['user_id' => $logisticsUser->id],
                [
                    'name' => 'Test Logistics Provider',
                    'slug' => 'test-logistics-provider',
                    'status' => 'pending',
                ]
            );
        });
    }
}