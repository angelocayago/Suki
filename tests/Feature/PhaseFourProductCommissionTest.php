<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\SellerSalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFourProductCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sets_per_product_rate_and_rejects_out_of_range_values(): void
    {
        $admin = $this->user('admin');
        $product = $this->catalog('commission-admin', 50000, 500);

        $this->actingAs($admin)->patch(route('admin.products.commission.update', $product['product']), [
            'commission_rate' => '5.25',
        ])->assertSessionHasNoErrors();
        $this->assertSame(525, $product['product']->fresh()->commission_bps);

        foreach (['-0.01', '100.01'] as $rate) {
            $this->actingAs($admin)->patch(route('admin.products.commission.update', $product['product']), [
                'commission_rate' => $rate,
            ])->assertSessionHasErrors('commission_rate');
        }
    }

    public function test_checkout_snapshots_per_product_commission_and_excludes_shipping(): void
    {
        $buyer = $this->user('buyer');
        $first = $this->catalog('five-percent', 50000, 500);
        $second = $this->catalog('eight-percent', 25000, 800);
        $first['product']->update(['commission_bps' => 500]);
        $second['product']->update(['commission_bps' => 800]);
        $this->cart($buyer, $first, 2);
        $this->cart($buyer, $second, 1);

        $this->checkout($buyer);
        $order = $buyer->buyerOrders()->with('items', 'sellerOrders.shipment')->sole();
        $firstItem = $order->items->firstWhere('product_id', $first['product']->id);
        $secondItem = $order->items->firstWhere('product_id', $second['product']->id);

        $this->assertSame(500, $firstItem->commission_bps_snapshot);
        $this->assertSame(5000, $firstItem->commission_minor); // 5% of ₱1,000
        $this->assertSame(800, $secondItem->commission_bps_snapshot);
        $this->assertSame(2000, $secondItem->commission_minor); // 8% of ₱250
        $this->assertSame(7000, $order->sellerOrders->sum('commission_minor'));
        $this->assertSame(135000, $order->total_minor); // ₱1,250 merchandise + ₱100 shipping
        $this->assertSame(135000, $order->sellerOrders->sum(fn ($sellerOrder) => $sellerOrder->shipment->cod_amount_minor));

        $first['product']->update(['commission_bps' => 1000]);
        $this->assertSame(500, $firstItem->fresh()->commission_bps_snapshot);
        $this->assertSame(5000, $firstItem->fresh()->commission_minor);
    }

    public function test_completed_sales_are_seller_scoped_and_exclude_cancelled_or_shipping(): void
    {
        $buyer = $this->user('buyer');
        $first = $this->catalog('seller-one', 10000, 500);
        $second = $this->catalog('seller-two', 10000, 1000);
        $first['product']->update(['commission_bps' => 333]);
        $second['product']->update(['commission_bps' => 1000]);
        $this->cart($buyer, $first, 3);
        $this->cart($buyer, $second, 1);
        $this->checkout($buyer);

        $order = $buyer->buyerOrders()->with('sellerOrders')->sole();
        $sellerOneOrder = $order->sellerOrders->firstWhere('seller_id', $first['seller']->id);
        $sellerTwoOrder = $order->sellerOrders->firstWhere('seller_id', $second['seller']->id);
        $sellerOneOrder->update(['workflow_status' => OrderStatus::Completed, 'status' => 'completed']);
        $sellerTwoOrder->update(['workflow_status' => OrderStatus::Completed, 'status' => 'cancelled']);

        $summary = app(SellerSalesService::class)->completedSummary($first['seller']);
        $other = app(SellerSalesService::class)->completedSummary($second['seller']);
        $this->assertSame(30000, $summary['raw_sales_minor']);
        $this->assertSame(999, $summary['commission_minor']);
        $this->assertSame(29001, $summary['net_sales_minor']);
        $this->assertSame(0, $other['raw_sales_minor']);
        $this->assertSame(0, $other['commission_minor']);
    }

    public function test_commission_rounds_half_cent_deterministically(): void
    {
        $buyer = $this->user('buyer');
        $catalog = $this->catalog('rounding', 1, 0);
        $catalog['product']->update(['commission_bps' => 5000]);
        $this->cart($buyer, $catalog, 1);

        $this->checkout($buyer);

        $item = $buyer->buyerOrders()->sole()->items()->sole();
        $this->assertSame(1, $item->commission_minor);
    }

    private function checkout(User $buyer): void
    {
        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => Address::firstOrCreate(['user_id' => $buyer->id], [
                'label' => 'Home', 'recipient' => $buyer->name, 'phone' => '09123456789',
                'line1' => '123 Test Street', 'barangay' => 'Barangay', 'city' => 'City',
                'province' => 'Province', 'postal_code' => '4000', 'is_default' => true,
            ])->id,
            'payment_method' => 'cod',
        ])->assertSessionHasNoErrors();
    }

    private function cart(User $buyer, array $catalog, int $quantity): void
    {
        CartItem::create([
            'user_id' => $buyer->id, 'product_slug' => $catalog['product']->slug,
            'product_name' => $catalog['product']->name, 'product_variant_id' => $catalog['variant']->id,
            'price' => 0.01, 'quantity' => $quantity,
        ]);
    }

    private function catalog(string $slug, int $priceMinor, int $sellerCommissionBps): array
    {
        $owner = $this->user('seller');
        $seller = Seller::create(['user_id' => $owner->id, 'name' => $slug, 'slug' => $slug.'-store', 'status' => 'approved', 'commission_bps' => $sellerCommissionBps]);
        $category = Category::firstOrCreate(['slug' => 'commission-category'], ['name' => 'Commission', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => $slug, 'slug' => $slug, 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => strtoupper($slug), 'name' => 'Default', 'price_minor' => $priceMinor, 'stock' => 20, 'is_active' => true]);

        return compact('seller', 'product', 'variant');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active', 'is_suspended' => false]);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);

        return $user;
    }
}
