<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Rider;
use App\Models\RiderCodRemittance;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerWalletEntry;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CodRemittanceService;
use App\Services\MoneyReportService;
use App\Services\RiderShippingFeeService;
use App\Services\SellerWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSevenMoneyReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_successful_rider_earns_exactly_once_and_full_cod_is_never_netted(): void
    {
        $fixture = $this->fixture();
        app(CodRemittanceService::class)->createLiability($fixture['shipment']);
        app(RiderShippingFeeService::class)->creditDelivered($fixture['shipment']);
        app(RiderShippingFeeService::class)->creditDelivered($fixture['shipment']);

        $this->assertDatabaseCount('rider_shipping_fee_earnings', 1);
        $this->assertDatabaseHas('rider_shipping_fee_earnings', ['rider_id' => $fixture['finalRider']->id, 'amount_minor' => 5000]);
        $this->assertDatabaseHas('rider_cod_remittances', ['rider_id' => $fixture['finalRider']->id, 'expected_minor' => 15000]);
        $this->assertDatabaseMissing('rider_shipping_fee_earnings', ['rider_id' => $fixture['replacedRider']->id]);

        $data = app(MoneyReportService::class)->rider($fixture['finalRider']);
        $this->assertSame(15000, $data['cod_liability_minor']);
        $this->assertSame(15000, $data['cod_outstanding_minor']);
        $this->assertSame(5000, $data['earnings_available_minor']);
    }

    public function test_rider_and_logistics_reports_are_strictly_scoped_and_remittance_is_server_derived(): void
    {
        $fixture = $this->fixture();
        app(CodRemittanceService::class)->createLiability($fixture['shipment']);
        $row = RiderCodRemittance::sole();
        $other = $this->fixture('other');
        app(CodRemittanceService::class)->createLiability($other['shipment']);

        $this->actingAs($fixture['riderUser'])->get(route('rider.money'))->assertOk()->assertSee($fixture['shipment']->tracking_code)->assertDontSee($other['shipment']->tracking_code);
        $this->actingAs($fixture['providerUser'])->get(route('logistics.money'))->assertOk()->assertSee($fixture['shipment']->tracking_code)->assertDontSee($other['shipment']->tracking_code);
        $this->actingAs($other['providerUser'])->patch(route('logistics.cod-remittances.review', $row), ['status' => 'ACCEPTED'])->assertForbidden();
        $this->actingAs($fixture['riderUser'])->post(route('rider.cod-remittances.submit', $row), ['amount' => 999999])->assertRedirect();
        $this->assertSame(15000, $row->fresh()->submitted_minor);
    }

    public function test_logistics_upstream_balance_uses_full_accepted_cod_without_subtracting_rider_earnings(): void
    {
        $fixture = $this->fixture(); $service = app(CodRemittanceService::class);
        $service->createLiability($fixture['shipment']); $row = RiderCodRemittance::sole();
        $service->submitRider($row, $fixture['riderUser']); $service->reviewRider($row, $fixture['providerUser'], 'ACCEPTED', null);
        app(RiderShippingFeeService::class)->creditDelivered($fixture['shipment']);

        $data = app(MoneyReportService::class)->logistics($fixture['provider']);
        $this->assertSame(15000, $data['received_from_riders_minor']);
        $this->assertSame(15000, $data['held_by_logistics_minor']);
        $this->assertSame(0, $data['remitted_to_admin_minor']);
        $this->assertNotSame(10000, $data['held_by_logistics_minor']);
    }

    public function test_seller_settlement_and_admin_totals_use_canonical_entries_and_refund_adjustments(): void
    {
        $fixture = $this->fixture();
        app(SellerWalletService::class)->creditCompleted($fixture['sellerOrder']);
        SellerWalletEntry::create(['seller_id' => $fixture['seller']->id, 'seller_order_id' => $fixture['sellerOrder']->id, 'type' => 'REFUND_ADJUSTMENT', 'status' => 'AVAILABLE', 'amount_minor' => -1000, 'reference' => 'RR-1', 'description' => 'Refund due']);
        app(CodRemittanceService::class)->createLiability($fixture['shipment']);

        $seller = app(MoneyReportService::class)->seller($fixture['seller']);
        $sale = $seller['rows']->first(fn ($row) => $row['entry']->type === 'SALE_EARNING');
        $this->assertSame(10000, $sale['gross']);
        $this->assertSame(1000, $sale['commission']);
        $this->assertSame(9000, $sale['entry']->amount_minor);
        $this->assertSame(8000, $seller['rows']->sum(fn ($row) => (int) $row['entry']->amount_minor));

        $admin = app(MoneyReportService::class)->admin([]);
        $this->assertSame(15000, $admin['total_cod_collected_minor']);
        $this->assertSame(15000, $admin['cod_held_by_riders_minor']);
        $this->assertSame(1000, $admin['commission_revenue_minor']);
        $this->assertSame(8000, $admin['seller_payable_minor']);
    }

    public function test_seller_and_admin_financial_routes_are_role_scoped_and_csv_uses_filtered_data(): void
    {
        $fixture = $this->fixture(); $other = $this->fixture('other');
        app(CodRemittanceService::class)->createLiability($fixture['shipment']);
        app(SellerWalletService::class)->creditCompleted($fixture['sellerOrder']);
        app(SellerWalletService::class)->creditCompleted($other['sellerOrder']);
        $this->actingAs($fixture['sellerUser'])->get(route('seller.settlements'))->assertOk()->assertSee('SO-'.$fixture['sellerOrder']->id)->assertDontSee('SO-'.$other['sellerOrder']->id);
        $this->actingAs($fixture['sellerUser'])->get(route('admin.financial-report'))->assertForbidden();
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('admin.financial-report', ['provider_id' => $fixture['provider']->id, 'export' => 1]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertSee($fixture['provider']->name)->assertDontSee($other['provider']->name);
    }

    private function fixture(string $key = 'one'): array
    {
        $providerUser = $this->user('logistics');
        $provider = LogisticsProvider::create(['user_id' => $providerUser->id, 'name' => 'Provider '.$key, 'slug' => 'provider-'.$key.'-'.uniqid(), 'status' => 'approved']);
        $riderUser = $this->user('rider'); $replacedUser = $this->user('rider');
        $finalRider = Rider::create(['user_id' => $riderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'FIN-'.uniqid(), 'is_active' => true]);
        $replacedRider = Rider::create(['user_id' => $replacedUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'OLD-'.uniqid(), 'is_active' => true]);
        $sellerUser = $this->user('seller'); $buyer = $this->user('buyer');
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Seller '.$key, 'slug' => 'seller-'.$key.'-'.uniqid(), 'status' => 'approved']);
        $number = 'MONEY-'.strtoupper(uniqid());
        $order = Order::create(['order_number' => $number, 'reference' => $number, 'buyer_id' => $buyer->id, 'seller_id' => $sellerUser->id, 'status' => 'COMPLETED', 'payment_method' => 'cod', 'payment_status' => 'PAID', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150, 'total_minor' => 15000, 'recipient_name' => 'Buyer', 'recipient_phone' => '0912', 'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 5000, 'commission_minor' => 1000, 'status' => 'completed', 'workflow_status' => OrderStatus::Completed, 'completed_at' => now()]);
        OrderItem::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'seller_id' => $sellerUser->id, 'product_slug' => 'money-item', 'product_name' => 'Money item', 'quantity' => 1, 'unit_price_minor' => 10000, 'unit_price' => 100, 'line_total' => 100, 'commission_minor' => 1000]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'rider_id' => $replacedRider->id, 'tracking_code' => 'MONEY-'.strtoupper(uniqid()), 'status' => 'delivered', 'workflow_status' => OrderStatus::Delivered, 'fee_minor' => 5000, 'cod_amount_minor' => 15000, 'cod_collected' => true, 'cod_collected_minor' => 15000, 'cod_collected_at' => now(), 'delivered_by' => $riderUser->id, 'proof_delivered_at' => now(), 'attempts' => 0]);
        return compact('providerUser', 'provider', 'riderUser', 'finalRider', 'replacedRider', 'sellerUser', 'seller', 'sellerOrder', 'shipment');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active', 'is_suspended' => false]);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);
        return $user;
    }
}
