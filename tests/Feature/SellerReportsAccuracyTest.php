<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerReportsAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private function signInSeller(): Seller
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shop = Seller::create(['user_id' => $owner->id, 'name' => 'Reports fixture', 'slug' => 'reports-fixture', 'status' => 'approved']);
        $this->actingAs($owner);

        return $shop;
    }

    public function test_reports_show_empty_states_and_ignore_unverified_session_sales(): void
    {
        $this->signInSeller();
        $this->withSession(['orders' => [['total_amount' => 98000, 'status' => 'delivered']], 'seller_orders' => [['total_amount' => 98000, 'status' => 'delivered']]])
            ->get(route('seller.reports'))->assertOk()
            ->assertSee('No completed sales yet. The chart is showing zero activity.')
            ->assertSee('No orders yet. The chart is showing zero activity.')
            ->assertSee('No sales yet')->assertDontSee('98,000.00');
    }

    public function test_reports_chart_and_metrics_use_persisted_seller_order_items(): void
    {
        $seller = $this->signInSeller();
        $order = Order::create(['seller_id' => $seller->user_id, 'buyer_id' => User::factory()->create()->id,
            'order_number' => 'SELLER-1001', 'status' => 'delivered', 'payment_status' => 'UNPAID',
            'recipient_name' => 'Test Buyer', 'recipient_phone' => '09000000000', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'Test']);
        $order->items()->create(['seller_id' => $seller->user_id, 'product_slug' => 'historic-item', 'product_name' => 'Test item', 'quantity' => 2, 'unit_price' => 21.25, 'line_total' => 42.50]);
        $this->get(route('seller.reports'))->assertOk()->assertSee('₱42.50')->assertSee('Test item')->assertSee('SELLER-1001')
            ->assertViewHas('grossSales', 42.50)->assertViewHas('chart', fn ($chart) => $chart->sum('gross') === 42.50)
            ->assertSee('do not imply payment');
    }

    public function test_reports_require_a_seller_session_and_persisted_identity(): void
    {
        $this->get(route('seller.reports'))->assertRedirect(route('seller.register'));
        $this->withSession(['seller_logged_in' => true])->get(route('seller.reports'))->assertForbidden();
    }
}
