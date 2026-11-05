<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{LogisticsProvider, Order, Rider, Seller, SellerOrder, Shipment, User, Voucher, VoucherRedemption};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Notification, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RiderCodPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Memory-only fixtures required.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStringStartsWith(strtolower(str_replace('\\', '/', sys_get_temp_dir())).'/', strtolower(str_replace('\\', '/', storage_path())));
        Storage::fake('delivery_proofs'); Mail::fake(); Notification::fake();
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('afterCommit', 'adminsAfterCommit')->andReturnNull();
    }

    public static function discounts(): array
    {
        return ['no voucher' => [[]], 'shop voucher' => [[10000]], 'multiple allocations' => [[6000, 4000]]];
    }

    #[DataProvider('discounts')]
    public function test_each_assigned_shipment_uses_its_stored_cod_without_changing_snapshots(array $discounts): void
    {
        [$rider, $provider, $order, $first] = $this->fixture($discounts);
        $before = $this->snapshots();
        $expected = $first->shipment->cod_amount_minor;
        foreach (['rider.dashboard', 'rider.deliveries'] as $route) {
            $response = $this->actingAs($rider->user)->get(route($route))->assertOk();
            $response->assertViewHas('orders', function ($rows) use ($first, $expected) {
                $row = collect($rows)->firstWhere('id', $first->id);
                $this->assertEquals($expected / 100, $row['total']);
                $this->assertSame($expected, $row['cod_amount_minor']);
                return true;
            });
            if ($route === 'rider.deliveries') $response->assertSee('Shipment COD due:')->assertSee(number_format($expected / 100, 2));
        }
        $this->actingAs($provider->owner)->get(route('logistics.assignments'))->assertOk()->assertViewHas('orders', function ($rows) use ($first, $expected) {
            $this->assertEquals($expected / 100, collect($rows)->firstWhere('id', $first->id)['total']);
            return true;
        });
        $this->assertSame($before, $this->snapshots());
        $this->assertSame($order->total_minor, (int) $order->sellerOrders->sum('shipment.cod_amount_minor'));
    }

    public function test_collection_form_and_actual_collection_use_discounted_shipment_cod(): void
    {
        [$rider, , , $first] = $this->fixture([10000]);
        $first->update(['workflow_status' => OrderStatus::OutForDelivery]);
        $first->shipment->update(['workflow_status' => OrderStatus::OutForDelivery, 'rider_id' => $rider->id]);
        $this->actingAs($rider->user)->get(route('rider.deliveries'))->assertOk()->assertSee('COD collected: ₱950.00')
            ->assertSee('Add Delivery Photo')->assertSee('Replace Photo')->assertSee('Remove Photo')
            ->assertSee('enctype="multipart/form-data"', false)->assertSee('name="proof_photo"', false)
            ->assertSee('id="delivery-proof-'.$first->id.'"', false)->assertSee('role="alert"', false)
            ->assertSee('Confirm Delivery Completion')->assertSee('Confirm Delivery')
            ->assertSee('data-cod-amount="₱950.00"', false)->assertSee('data-cod-required="true"', false)
            ->assertSee('data-delivery-completion', false)->assertSee('aria-modal', false)
            ->assertDontSee("confirm('Confirm delivery and COD collection?')", false)
            ->assertSee(route('rider.database.order.status', $first), false)->assertSee('name="_token"', false);
        $this->post(route('rider.database.order.status', $first), [
            'status' => 'delivered', 'proof_photo' => \Tests\Support\DeliveryProofFile::image(),
            'received_by' => 'Test Recipient', 'cod_collected' => '1', 'amount' => 105000,
        ])->assertSessionHasNoErrors();
        $this->assertSame(95000, $first->shipment->fresh()->cod_collected_minor);
        Storage::disk('delivery_proofs')->assertExists($first->shipment->fresh()->proof_path);
        $this->assertDatabaseHas('rider_cod_remittances', ['shipment_id' => $first->shipment->id, 'expected_minor' => 95000]);
    }

    public function test_html_disguised_as_photo_is_rejected_without_proof_or_cod_writes(): void
    {
        [$rider, , , $first] = $this->fixture([10000]);
        $first->update(['workflow_status' => OrderStatus::OutForDelivery]);
        $first->shipment->update(['workflow_status' => OrderStatus::OutForDelivery, 'rider_id' => $rider->id]);
        // Use a real temporary upload: UploadedFile::fake() reports MIME from the
        // filename, which would hide the content mismatch this regression checks.
        $path = tempnam(storage_path(), 'invalid-proof-');
        file_put_contents($path, '<html><script>alert(1)</script></html>');
        try {
            $photo = new \Illuminate\Http\UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
            $this->actingAs($rider->user)->from(route('rider.deliveries'))->post(route('rider.database.order.status', $first), [
                'status' => 'delivered', 'proof_photo' => $photo,
                'received_by' => 'Test Recipient', 'cod_collected' => '1',
            ])->assertSessionHasErrors('proof_photo');
        } finally {
            if (is_file($path)) unlink($path);
        }
        $this->get(route('rider.deliveries'))->assertOk()->assertSee('aria-invalid="true"', false);
        $this->assertNull($first->shipment->fresh()->proof_path);
        $this->assertFalse($first->shipment->fresh()->cod_collected);
        $this->assertEmpty(Storage::disk('delivery_proofs')->allFiles());
    }

    public function test_non_cod_collection_screen_does_not_request_cash_confirmation(): void
    {
        [$rider, , $order, $first] = $this->fixture([]);
        $order->update(['payment_method' => 'card']);
        $first->update(['workflow_status' => OrderStatus::OutForDelivery]);
        $first->shipment->update(['workflow_status' => OrderStatus::OutForDelivery, 'rider_id' => $rider->id, 'cod_amount_minor' => 0]);
        $this->actingAs($rider->user)->get(route('rider.deliveries'))->assertOk()->assertSee('No COD collection required.')->assertDontSee('name="cod_collected"', false);
    }

    private function snapshots(): array
    {
        $result = [];
        foreach (['orders', 'seller_orders', 'shipments', 'order_items', 'vouchers', 'voucher_redemptions', 'delivery_events', 'rider_cod_remittances'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $result;
    }

    private function fixture(array $discounts): array
    {
        $providerUser = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $provider = LogisticsProvider::create(['user_id' => $providerUser->id, 'name' => 'Test Provider', 'slug' => 'test-provider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => User::factory()->create(['role' => 'rider', 'status' => 'active', 'is_suspended' => false])->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'TEST', 'is_active' => true, 'approved_at' => now(), 'availability' => 'ONLINE']);
        $order = Order::create(['order_number' => 'COD-PRESENTATION', 'buyer_id' => User::factory()->create(['role' => 'buyer', 'status' => 'active'])->id, 'status' => 'READY_FOR_PICKUP', 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 1200, 'shipping_fee' => 100, 'discount_amount' => array_sum($discounts) / 100, 'total_amount' => (130000 - array_sum($discounts)) / 100, 'total_minor' => 130000 - array_sum($discounts), 'recipient_name' => 'Test Buyer', 'recipient_phone' => '000', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'Test', 'shipping_address' => []]);
        $first = null;
        foreach ([100000, 20000] as $index => $subtotal) {
            $seller = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Shop '.$index, 'slug' => 'shop-'.$index, 'status' => 'approved']);
            $so = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => $subtotal, 'shipping_fee_minor' => 5000, 'commission_minor' => 0, 'status' => 'ready_to_ship', 'workflow_status' => OrderStatus::ReadyForPickup]);
            $order->items()->create(['seller_id' => $seller->user_id, 'seller_order_id' => $so->id, 'product_slug' => 'historic-'.$index, 'product_name' => 'Historical item '.$index, 'unit_price' => $subtotal / 100, 'unit_price_minor' => $subtotal, 'quantity' => 1, 'line_total' => $subtotal / 100, 'seller_funded_discount_minor' => $index === 0 ? array_sum($discounts) : 0]);
            Shipment::create(['seller_order_id' => $so->id, 'logistics_provider_id' => $provider->id, 'pickup_rider_id' => $index === 0 ? $rider->id : null, 'tracking_code' => 'TEST-COD-'.$index, 'status' => 'unassigned', 'workflow_status' => OrderStatus::ReadyForPickup, 'fee_minor' => 0, 'cod_amount_minor' => $subtotal + 5000 - ($index === 0 ? array_sum($discounts) : 0), 'cod_collected' => false, 'attempts' => 0]);
            if ($index === 0) {
                $first = $so;
                foreach ($discounts as $n => $amount) {
                    $voucher = Voucher::create(['code' => 'SHOP-'.$n, 'scope' => 'SELLER', 'seller_id' => $seller->id, 'discount_type' => 'FIXED', 'discount_minor' => $amount, 'is_active' => true]);
                    VoucherRedemption::create(['voucher_id' => $voucher->id, 'order_id' => $order->id, 'seller_order_id' => $so->id, 'buyer_id' => $order->buyer_id, 'code_snapshot' => $voucher->code, 'scope_snapshot' => 'SELLER', 'seller_id_snapshot' => $seller->id, 'discount_type_snapshot' => 'FIXED', 'discount_value_snapshot' => $amount, 'discount_minor' => $amount]);
                }
            }
        }
        return [$rider, $provider, $order, $first];
    }
}
