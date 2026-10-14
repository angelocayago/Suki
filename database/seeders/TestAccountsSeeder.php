<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
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
        $this->call(RoleSeeder::class);

        DB::transaction(function () {
            $buyerRole = Role::query()->where('name', 'buyer')->firstOrFail();
            $sellerRole = Role::query()->where('name', 'seller')->firstOrFail();
            $logisticsRole = Role::query()->where('name', 'logistics')->firstOrFail();
            $riderRole = Role::query()->where('name', 'rider')->firstOrFail();
            $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

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

            Address::updateOrCreate(
                [
                    'user_id' => $buyer->id,
                    'label' => 'Acceptance Home',
                ],
                [
                    'recipient' => $buyer->name,
                    'phone' => '09170000001',
                    'line1' => '1 Acceptance Street',
                    'barangay' => 'Acceptance Barangay',
                    'city' => 'Acceptance City',
                    'province' => 'Acceptance Province',
                    'postal_code' => '1000',
                    'is_default' => true,
                ]
            );

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

            $seller = Seller::updateOrCreate(
                ['user_id' => $sellerUser->id],
                [
                    'name' => 'Test Seller Store',
                    'slug' => 'test-seller-store',
                    'status' => 'approved',
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

            $provider = LogisticsProvider::updateOrCreate(
                ['user_id' => $logisticsUser->id],
                [
                    'name' => 'Test Logistics Provider',
                    'slug' => 'test-logistics-provider',
                    'status' => 'approved',
                ]
            );

            // Pickup rider account
            $pickupRiderUser = User::updateOrCreate(
                ['email' => 'rider@test.com'],
                [
                    'name' => 'Test Pickup Rider',
                    'first_name' => 'Test Pickup',
                    'last_name' => 'Rider',
                    'password' => Hash::make('password'),
                    'role' => 'rider',
                    'status' => 'active',
                    'is_suspended' => false,
                ]
            );
            $pickupRiderUser->roles()->syncWithoutDetaching([$riderRole->id]);

            Rider::updateOrCreate(
                ['user_id' => $pickupRiderUser->id],
                [
                    'logistics_provider_id' => $provider->id,
                    'vehicle_type' => 'Motorcycle',
                    'plate_no' => 'PICKUP-001',
                    'approved_by' => $logisticsUser->id,
                    'approved_at' => now(),
                    'is_active' => true,
                ]
            );

            // Final delivery rider account
            $deliveryRiderUser = User::updateOrCreate(
                ['email' => 'delivery.rider@test.com'],
                [
                    'name' => 'Test Delivery Rider',
                    'first_name' => 'Test Delivery',
                    'last_name' => 'Rider',
                    'password' => Hash::make('password'),
                    'role' => 'rider',
                    'status' => 'active',
                    'is_suspended' => false,
                ]
            );
            $deliveryRiderUser->roles()->syncWithoutDetaching([$riderRole->id]);

            Rider::updateOrCreate(
                ['user_id' => $deliveryRiderUser->id],
                [
                    'logistics_provider_id' => $provider->id,
                    'vehicle_type' => 'Motorcycle',
                    'plate_no' => 'DELIVERY-001',
                    'approved_by' => $logisticsUser->id,
                    'approved_at' => now(),
                    'is_active' => true,
                ]
            );

            // Reusable catalog fixture for manual browser acceptance testing.
            $category = Category::updateOrCreate(
                ['slug' => 'acceptance-catalog'],
                [
                    'name' => 'Acceptance Catalog',
                    'position' => 999,
                    'is_active' => true,
                ]
            );

            $seller->update([
                'category_id' => $category->id,
                'seller_type' => 'business',
                'tin' => '000-000-000-000',
            ]);

            $product = Product::withTrashed()->updateOrCreate(
                ['slug' => 'acceptance-product'],
                [
                    'seller_id' => $seller->id,
                    'category_id' => $category->id,
                    'name' => 'Acceptance Test Product',
                    'description' => 'Reusable product for manual ERP acceptance testing.',
                    'is_active' => true,
                    'moderation_status' => 'APPROVED',
                    'moderation_reason' => null,
                ]
            );
            $product->restore();

            $seller->allowedCategories()->syncWithoutDetaching([
                $category->id => ['assigned_by' => null],
            ]);

            $variant = ProductVariant::withTrashed()->updateOrCreate(
                ['sku' => 'ACCEPTANCE-DEFAULT'],
                [
                    'product_id' => $product->id,
                    'name' => 'Default',
                    'options' => ['purpose' => 'acceptance-testing'],
                    'price_minor' => 12900,
                    'stock' => 25,
                    'weight_grams' => 500,
                    'is_active' => true,
                ]
            );
            $variant->restore();

            // Administrator account
            $admin = User::updateOrCreate(
                ['email' => 'admin@test.com'],
                [
                    'name' => 'Test Administrator',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'status' => 'active',
                    'is_suspended' => false,
                ]
            );
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        });
    }
}
