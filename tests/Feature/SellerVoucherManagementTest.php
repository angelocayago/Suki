<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerVoucher;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerVouchers;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerVoucherManagementTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        SellerCategories::populate();
        User::factory()->create();
        $this->shop = $this->shop();
        $this->actingAs($this->shop->owner);
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(4, 0));
        $this->product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'name' => 'Eligible product', 'slug' => 'voucher-product', 'is_active' => true]);
        $this->product->variants()->create(['name' => 'Default', 'sku' => 'VOUCHER-SKU', 'price_minor' => 10000, 'stock' => 10, 'is_active' => true]);
    }

    private function shop(): Seller
    {
        return Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id,
            'name' => 'Voucher shop', 'slug' => uniqid('voucher-'), 'status' => 'approved', 'business_category' => 'Pet Supplies']);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['name' => 'Save ten', 'code' => ' save10 ', 'description' => 'Offer', 'discount_type' => 'percentage', 'discount_value' => '10.25',
            'minimum_spend' => '500', 'maximum_discount' => '100', 'starts_at' => '2026-10-23T12:00', 'ends_at' => '2026-10-30T12:00',
            'total_limit' => 100, 'per_buyer_limit' => 1, 'product_scope' => 'all', 'status' => 'paused'], $changes);
    }

    private function create(array $changes = []): SellerVoucher
    {
        $this->post(route('seller.vouchers.store'), $this->payload($changes))->assertSessionHasNoErrors()->assertRedirect();

        return SellerVoucher::latest('id')->firstOrFail();
    }

    public function test_create_persists_integer_terms_manila_schedule_and_ignores_forged_ownership_and_usage(): void
    {
        $v = $this->create(['seller_id' => 999, 'used_count' => 99, 'product_scope' => 'selected', 'product_ids' => [$this->product->id]]);
        $this->assertSame($this->shop->id, $v->seller_id);
        $this->assertSame('SAVE10', $v->code);
        $this->assertSame(1025, $v->discount_bps);
        $this->assertSame(50000, $v->minimum_minor);
        $this->assertSame(10000, $v->cap_minor);
        $this->assertSame(0, $v->used_count);
        $this->assertSame('2026-10-23 04:00:00', $v->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame([$this->product->id], $v->products->modelKeys());
        foreach (['index', 'create', 'show', 'edit'] as $action) {
            $this->get(route('seller.vouchers.'.$action, in_array($action, ['show', 'edit']) ? $v->id : []))->assertOk();
        }
    }

    public static function invalidTerms(): array
    {
        return [
            [['discount_value' => '-1'], 'discount_value'], [['discount_value' => '0'], 'discount_value'], [['discount_value' => '100.01'], 'discount_value'],
            [['discount_value' => '1.001'], 'discount_value'], [['discount_value' => '1e2'], 'discount_value'], [['minimum_spend' => '-1'], 'minimum_spend'],
            [['maximum_discount' => '0'], 'maximum_discount'], [['discount_type' => 'fixed'], 'maximum_discount'],
            [['ends_at' => '2026-10-23T11:59'], 'ends_at'], [['starts_at' => 'not-date'], 'starts_at'],
            [['total_limit' => 0], 'total_limit'], [['per_buyer_limit' => 101], 'per_buyer_limit'], [['per_buyer_limit' => 0], 'per_buyer_limit'],
            [['code' => 'A B'], 'code'], [['code' => 'AB'], 'code'], [['code' => '£SAVE'], 'code'], [['code' => str_repeat('A', 33)], 'code'],
            [['product_scope' => 'selected', 'product_ids' => []], 'product_ids'], [['product_ids' => 'bad'], 'product_ids'],
        ];
    }

    #[DataProvider('invalidTerms')]
    public function test_invalid_terms_are_rejected_atomically(array $changes, string $field): void
    {
        $this->post(route('seller.vouchers.store'), $this->payload($changes))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('seller_vouchers', 0);
        $this->assertDatabaseCount('seller_voucher_products', 0);
    }

    public function test_fixed_percentage_minimum_cap_rounding_and_preview_never_exceed_subtotal(): void
    {
        $v = $this->create();
        $this->assertSame(0, $v->estimate(49999));
        $this->assertSame(5125, $v->estimate(50000));
        $this->assertSame(10000, $v->estimate(200000));
        $this->put(route('seller.vouchers.update', $v->id), $this->payload(['discount_type' => 'fixed', 'discount_value' => '900', 'maximum_discount' => null]))->assertSessionHasNoErrors();
        $this->assertSame(50000, $v->fresh()->estimate(50000));
        $this->assertSame(90000, $v->fresh()->discount_minor);
        $v->forceFill(['discount_type' => 'percentage', 'discount_minor' => null, 'discount_bps' => 5000, 'minimum_minor' => 0, 'cap_minor' => null]);
        $this->assertSame(1, $v->estimate(1));
        $this->get(route('seller.vouchers.show', $v->id).'?eligible_subtotal=500')->assertOk()->assertSee('Illustrative');
        $this->getJson(route('seller.vouchers.show', $v->id).'?eligible_subtotal=-1')->assertUnprocessable();
    }

    public function test_codes_are_reserved_globally_and_immutable_even_after_archive(): void
    {
        $v = $this->create();
        $this->put(route('seller.vouchers.update', $v->id), $this->payload(['code' => 'NEWCODE']))->assertSessionHasErrors('code');
        $this->post(route('seller.vouchers.archive', $v->id))->assertRedirect();
        $this->actingAs($this->shop()->owner)->post(route('seller.vouchers.store'), $this->payload())->assertSessionHasErrors('code');
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('seller_vouchers')->insert(array_replace($v->getAttributes(), ['id' => 999, 'code' => 'save10']));
    }

    public function test_product_eligibility_rejects_foreign_inactive_and_deleted_new_selections(): void
    {
        $foreign = $this->product->replicate();
        $foreign->fill(['seller_id' => $this->shop()->id, 'slug' => 'foreign'])->save();
        foreach ([$foreign->id, 99999] as $id) {
            $this->post(route('seller.vouchers.store'), $this->payload(['product_scope' => 'selected', 'product_ids' => [$id]]))->assertSessionHasErrors('product_ids.0');
        }
        $v = $this->create(['product_scope' => 'selected', 'product_ids' => [$this->product->id]]);
        $this->product->update(['is_active' => false]);
        $this->post(route('seller.vouchers.activate', $v->id))->assertSessionHasErrors('status');
        $this->put(route('seller.vouchers.update', $v->id), $this->payload(['product_scope' => 'selected', 'product_ids' => [$this->product->id]]))->assertSessionHasNoErrors();
        $this->product->delete();
        $this->get(route('seller.vouchers.edit', $v->id))->assertOk()->assertSee('unavailable');
        $this->post(route('seller.vouchers.store'), $this->payload(['code' => 'ANOTHER', 'product_scope' => 'selected', 'product_ids' => [$this->product->id]]))->assertSessionHasErrors('product_ids.0');
    }

    public function test_lifecycle_computes_scheduling_expiry_exhaustion_and_retains_archived_history(): void
    {
        $v = $this->create(['status' => 'active']);
        $this->assertSame('active', SellerVouchers::availability($v));
        $this->travelBack();
        $this->travelTo($v->starts_at->subMinute());
        $this->assertSame('scheduled', SellerVouchers::availability($v));
        $this->travelTo($v->ends_at);
        $this->assertSame('expired', SellerVouchers::availability($v));
        $this->post(route('seller.vouchers.activate', $v->id))->assertSessionHasErrors('status');
        $this->travelTo($v->starts_at);
        $v->forceFill(['used_count' => 100])->save();
        $this->assertSame('exhausted', SellerVouchers::availability($v));
        $this->post(route('seller.vouchers.pause', $v->id))->assertRedirect();
        $this->assertSame('paused', SellerVouchers::availability($v));
        $this->post(route('seller.vouchers.archive', $v->id))->assertRedirect();
        $this->post(route('seller.vouchers.archive', $v->id))->assertRedirect();
        $this->assertSame('archived', SellerVouchers::availability($v));
        $this->assertSame(100, $v->fresh()->used_count);
        $this->post(route('seller.vouchers.activate', $v->id))->assertConflict();
        $this->get(route('seller.vouchers.edit', $v->id))->assertConflict();
        $this->delete(route('seller.vouchers.show', $v->id))->assertStatus(405);
    }

    public function test_used_terms_lock_while_labels_and_withdrawal_remain_available(): void
    {
        $v = $this->create();
        $v->forceFill(['used_count' => 1, 'terms_locked_at' => now()])->save();
        $this->put(route('seller.vouchers.update', $v->id), ['name' => 'Renamed', 'description' => 'New label', 'used_count' => 0])->assertSessionHasNoErrors();
        foreach (SellerVouchers::TERM_INPUTS as $field) {
            $this->put(route('seller.vouchers.update', $v->id), ['name' => 'Renamed', $field => $this->payload()[$field] ?? [$this->product->id]])->assertSessionHasErrors($field);
        }
        $this->assertSame(1, $v->fresh()->used_count);
        $this->assertSame(1025, $v->fresh()->discount_bps);
        $this->get(route('seller.vouchers.edit', $v->id))->assertOk()->assertDontSee('name="discount_value"', false);
        $this->post(route('seller.vouchers.pause', $v->id))->assertRedirect();
    }

    public function test_foreign_voucher_access_is_denied_on_every_endpoint(): void
    {
        $v = $this->create();
        $this->actingAs($this->shop()->owner);
        foreach (['show', 'edit'] as $action) {
            $this->get(route('seller.vouchers.'.$action, $v->id))->assertNotFound();
        }
        $this->put(route('seller.vouchers.update', $v->id), $this->payload())->assertNotFound();
        foreach (['activate', 'pause', 'archive'] as $action) {
            $this->post(route('seller.vouchers.'.$action, $v->id))->assertNotFound();
        }
        $this->get(route('seller.vouchers.index'))->assertOk()->assertDontSee('SAVE10');
    }

    public function test_listing_filters_chips_pagination_and_escaped_values(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->create(['code' => 'CODE'.$i, 'name' => '<b>Offer</b> '.$i]);
        }
        $this->get(route('seller.vouchers.index'))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 17 && $p->count() === 15)->assertViewHas('filterChips', []);
        $query = ['search' => 'Offer', 'availability' => 'paused', 'discount_type' => 'percentage', 'product_scope' => 'all', 'product_id' => $this->product->id,
            'date_range' => 'custom', 'from' => '2026-10-23', 'to' => '2026-10-23', 'sort' => 'name', 'page' => 2];
        $this->get(route('seller.vouchers.index', $query))->assertOk()->assertSee('&lt;b&gt;Offer&lt;/b&gt;', false)->assertDontSee('<b>Offer</b>', false)
            ->assertViewHas('paginator', fn ($p) => $p->total() === 17 && $p->count() === 2)
            ->assertViewHas('filterChips', function ($chips) {
                $this->assertCount(7, $chips);
                foreach ($chips as $chip) {
                    $this->assertStringNotContainsString('page=', $chip['url']);
                }
                $date = collect($chips)->firstWhere('key', 'date_range');
                $this->assertStringNotContainsString('from=', $date['url']);

                return true;
            });
        $this->getJson(route('seller.vouchers.index', ['product_id' => 9999]))->assertUnprocessable();
    }

    public function test_account_authorization_and_shop_eligibility(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'buyer']))->get(route('seller.vouchers.index'))->assertForbidden();
        $this->shop->owner->update(['is_suspended' => true]);
        $this->actingAs($this->shop->owner->fresh())->get(route('seller.vouchers.index'))->assertForbidden();
        $this->shop->owner->update(['is_suspended' => false]);
        $this->actingAs($this->shop->owner->fresh());
        $this->shop->update(['status' => 'pending']);
        $this->app->detectEnvironment(fn () => 'local');
        $this->get(route('seller.vouchers.index'))->assertOk();
        $this->app->detectEnvironment(fn () => 'production');
        $this->get(route('seller.vouchers.index'))->assertForbidden();
        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_voucher_actions_do_not_mutate_commerce_or_session_promotions(): void
    {
        $order = Order::create(['seller_id' => $this->shop->user_id, 'buyer_id' => User::factory()->create()->id,
            'order_number' => 'VOUCHER-READONLY', 'status' => 'PLACED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID',
            'recipient_name' => 'Saved recipient', 'recipient_phone' => '123', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test',
            'street_address' => 'Test', 'total_amount' => 100, 'shipping_fee' => 0]);
        $group = $order->sellerOrders()->create(['seller_id' => $this->shop->id, 'status' => 'completed', 'subtotal_minor' => 10000]);
        $order->items()->create(['seller_order_id' => $group->id, 'seller_id' => $this->shop->user_id, 'product_slug' => 'voucher-product',
            'product_name' => 'Snapshot', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $order->payments()->create(['method' => 'cod', 'amount_minor' => 10000, 'status' => 'pending']);
        $provider = DB::table('logistics_providers')->insertGetId(['user_id' => $this->shop->user_id, 'name' => 'Fixture', 'slug' => 'voucher-provider', 'status' => 'approved']);
        $shipment = DB::table('shipments')->insertGetId(['seller_order_id' => $group->id, 'logistics_provider_id' => $provider, 'tracking_code' => 'VOUCHER-TRACK', 'status' => 'assigned', 'fee_minor' => 0, 'cod_amount_minor' => 10000, 'cod_collected' => false, 'attempts' => 0]);
        DB::table('delivery_events')->insert(['shipment_id' => $shipment, 'status' => 'assigned', 'attempt' => 0, 'user_id' => $this->shop->user_id, 'occurred_at' => now()]);
        $tables = ['orders', 'order_items', 'seller_orders', 'products', 'product_variants', 'payments', 'shipments', 'delivery_events'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $this->withSession(['seller_promotions' => [['name' => 'Saved campaign']]]);
        $v = $this->create(['status' => 'active']);
        $this->get(route('seller.vouchers.show', $v->id))->assertOk()->assertSessionHas('seller_promotions.0.name', 'Saved campaign');
        $this->post(route('seller.vouchers.pause', $v->id))->assertRedirect();
        $this->post(route('seller.vouchers.archive', $v->id))->assertRedirect();
        $this->assertSame($before, $snapshot());
        $this->assertSame(0, $v->fresh()->used_count);
        $this->assertDatabaseCount('seller_vouchers', 1);
        $this->assertFalse(Schema::hasTable('voucher_redemptions'));
    }

    public function test_active_offer_becomes_unavailable_without_an_active_variant_and_activation_rolls_back(): void
    {
        $v = $this->create(['status' => 'active']);
        $this->product->variants()->update(['is_active' => false]);
        $this->assertSame('unavailable', SellerVouchers::availability($v));
        $this->post(route('seller.vouchers.store'), $this->payload(['code' => 'INVALID-ACTIVE', 'status' => 'active']))->assertSessionHasErrors('status');
        $this->assertDatabaseCount('seller_vouchers', 1);
        $this->get(route('seller.vouchers.index', ['availability' => 'unavailable']))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1);
    }

    public function test_guests_and_ambiguous_shops_cannot_access_vouchers(): void
    {
        auth()->logout();
        $this->withSession(['seller_logged_in' => true])->get(route('seller.vouchers.index'))->assertRedirect(route('login'));
        $this->actingAs($this->shop->owner);
        $second = $this->shop();
        $second->update(['user_id' => $this->shop->user_id]);
        $this->get(route('seller.vouchers.index'))->assertConflict();
    }

    public function test_two_concurrent_database_writers_cannot_reserve_case_only_duplicate_codes(): void
    {
        $directory = sys_get_temp_dir().'/suki-voucher-race-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $database = $directory.'/race.sqlite';
        $workers = [];
        try {
            $pdo = new \PDO('sqlite:'.$database);
            $pdo->exec('CREATE TABLE sellers (id INTEGER PRIMARY KEY)');
            $pdo->exec('INSERT INTO sellers VALUES (1)');
            foreach (DB::select("SELECT sql FROM sqlite_master WHERE tbl_name='seller_vouchers' AND sql IS NOT NULL ORDER BY type DESC") as $definition) {
                $pdo->exec($definition->sql);
            }
            foreach (['race-code', 'RACE-CODE'] as $index => $code) {
                $process = proc_open([PHP_BINARY, base_path('tests/Fixtures/create-voucher-concurrent.php'), $database, $directory.'/gate', $directory.'/ready'.$index, $code],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 10;
            while (! file_exists($directory.'/ready0') || ! file_exists($directory.'/ready1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Concurrent workers did not become ready.');
                }
                usleep(10000);
            }
            file_put_contents($directory.'/gate', 'go');
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $results[] = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $error);
            }
            $workers = [];
            sort($results);
            $this->assertSame(['collision', 'created'], $results);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM seller_vouchers')->fetchColumn());
            $pdo = null;
        } finally {
            foreach ($workers as [$process, $pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
            }
            foreach (glob($directory.'/*') as $temporaryFile) {
                unlink($temporaryFile);
            }
            rmdir($directory);
        }
    }
}
