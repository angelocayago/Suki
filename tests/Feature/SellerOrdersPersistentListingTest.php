<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerDateFilter;
use App\Support\SellerOrders;
use App\Support\SellerReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerOrdersPersistentListingTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        // Deliberately make the shop ID different from its owner's user ID.
        User::factory()->create();
        $this->shop = $this->shop();
        $this->actingAs($this->shop->owner);
    }

    private function shop(): Seller
    {
        return Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id,
            'name' => 'Orders fixture', 'slug' => 'orders-'.uniqid(), 'status' => 'approved', 'business_category' => 'Pet Supplies']);
    }

    private function order(array $changes = []): Order
    {
        return Order::create($changes + ['buyer_id' => User::factory()->create()->id, 'seller_id' => $this->shop->user_id,
            'order_number' => 'ORD-'.++$this->sequence, 'status' => 'PLACED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID',
            'recipient_name' => 'Saved Buyer', 'recipient_phone' => 'PRIVATE-PHONE', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'PRIVATE-ADDRESS',
            'total_amount' => 987654.32, 'shipping_fee' => 888, 'discount_amount' => 777]);
    }

    private function item(Order $order, array $changes = []): OrderItem
    {
        return $order->items()->create($changes + ['seller_id' => $this->shop->user_id, 'product_slug' => 'same-snapshot-slug', 'product_name' => 'Saved Seller Item',
            'unit_price' => 25, 'quantity' => 2, 'line_total' => 50]);
    }

    private function normalized(Order $order, ?Seller $shop = null, string $status = 'completed')
    {
        return $order->sellerOrders()->create(['seller_id' => ($shop ?? $this->shop)->id, 'status' => $status, 'subtotal_minor' => 999999]);
    }

    private function date(Order $order, string $at): void
    {
        $order->timestamps = false;
        $order->forceFill(['created_at' => $at])->save();
    }

    public function test_legacy_listing_ignores_sessions_and_displays_only_saved_gross_and_minimal_buyer_data(): void
    {
        $order = $this->order();
        $this->item($order);
        $this->withSession(['orders' => [['id' => 'SESSION-SECRET', 'total' => 99999]]])->get(route('seller.orders'))
            ->assertOk()->assertSee('ORD-1')->assertSee('Saved Seller Item')->assertSee('₱50.00')->assertSee('Saved Buyer')
            ->assertDontSee('SESSION-SECRET')->assertDontSee('987,654.32')->assertDontSee('PRIVATE-PHONE')->assertDontSee('PRIVATE-ADDRESS')
            ->assertViewHas('paginator', fn ($p) => $p->total() === 1 && $p->first()->gross_amount == 50);
    }

    public function test_shared_parent_preview_search_amounts_and_choices_are_seller_scoped(): void
    {
        $foreign = $this->shop();
        $order = $this->order(['seller_id' => null, 'status' => 'CANCELLED']);
        $own = $this->normalized($order);
        $other = $this->normalized($order, $foreign);
        $a = $this->item($order, ['seller_order_id' => $own->id, 'seller_id' => null, 'unit_price_minor' => 1234, 'unit_price' => 999, 'product_name' => 'Owned line']);
        $this->item($order, ['seller_order_id' => $other->id, 'seller_id' => null, 'product_name' => 'FOREIGN-LINE-SECRET', 'unit_price' => 9999]);
        $this->get(route('seller.orders'))->assertOk()->assertSee('Owned line')->assertDontSee('FOREIGN-LINE-SECRET')
            ->assertSee('Seller fulfillment')->assertSee('CANCELLED')->assertViewHas('summary', fn ($s) => $s->total == 1 && $s->completed == 1 && $s->gross == 24.68)
            ->assertViewHas('paginator', fn ($p) => $p->first()->items->pluck('id')->all() === [$a->id]);
        $this->get(route('seller.orders', ['search' => 'FOREIGN-LINE-SECRET']))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 0);
        $this->actingAs($foreign->owner)->get(route('seller.orders'))->assertOk()->assertSee('FOREIGN-LINE-SECRET')->assertDontSee('Owned line');
    }

    public function test_foreign_legacy_records_and_manipulated_owner_parameters_cannot_expand_scope(): void
    {
        $this->item($this->order());
        $foreign = $this->shop();
        $other = $this->order(['seller_id' => $foreign->user_id, 'payment_method' => 'FOREIGN-METHOD', 'status' => 'FOREIGN-STATUS']);
        $this->item($other, ['seller_id' => $foreign->user_id, 'product_name' => 'FOREIGN-LEGACY']);
        $this->get(route('seller.orders', ['seller_id' => $foreign->id, 'user_id' => $foreign->user_id]))->assertOk()->assertDontSee('FOREIGN-LEGACY')->assertDontSee('FOREIGN-METHOD')->assertDontSee('Foreign-status')
            ->assertViewHas('paginator', fn ($p) => $p->total() === 1);
        $this->getJson(route('seller.orders', ['payment_method' => 'FOREIGN-METHOD']))->assertUnprocessable();
        $this->getJson(route('seller.orders', ['order_status' => 'FOREIGN-STATUS']))->assertUnprocessable();
    }

    public function test_null_and_mismatched_legacy_owners_are_excluded(): void
    {
        $foreign = $this->shop();
        $this->item($this->order(['seller_id' => null]), ['seller_id' => null]);
        $this->item($this->order(['seller_id' => $foreign->user_id]));
        $this->item($this->order(), ['seller_id' => $foreign->user_id]);
        $this->get(route('seller.orders'))->assertOk()->assertSeeText('No attributable seller orders found.')->assertViewHas('summary', fn ($s) => $s->total == 0 && $s->gross == 0);
    }

    public function test_normalized_precedence_and_inconsistent_parent_links_do_not_duplicate_or_leak(): void
    {
        $order = $this->order();
        $group = $this->normalized($order);
        $this->item($order, ['product_name' => 'LEFTOVER-LEGACY']);
        $this->item($order, ['seller_order_id' => $group->id, 'product_name' => 'Normalized snapshot']);
        $wrongParent = $this->order();
        $this->item($wrongParent, ['seller_order_id' => $group->id, 'product_name' => 'MALFORMED-ITEM']);
        $partial = $this->order();
        $this->normalized($partial, $this->shop());
        $this->item($partial, ['product_name' => 'PARTIAL-LEGACY']);
        $this->get(route('seller.orders'))->assertOk()->assertSee('Normalized snapshot')->assertDontSee('LEFTOVER-LEGACY')->assertDontSee('MALFORMED-ITEM')->assertDontSee('PARTIAL-LEGACY')
            ->assertViewHas('summary', fn ($s) => $s->total == 1 && $s->gross == 50);
    }

    public function test_snapshots_survive_inactive_and_deleted_catalogue_and_same_slug_lines_remain_distinct(): void
    {
        SellerCategories::populate();
        $product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'Catalogue', 'slug' => 'catalogue', 'is_active' => false]);
        $variant = $product->variants()->create(['name' => 'Saved default', 'sku' => 'ORD-TEST', 'price_minor' => 100, 'stock' => 10, 'is_active' => false]);
        $order = $this->order();
        $a = $this->item($order, ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => 'Snapshot A', 'variation' => ['Color' => 'Green']]);
        $b = $this->item($order, ['product_name' => 'Snapshot B', 'variant_name' => 'Saved variant']);
        $variant->delete();
        $product->delete();
        $this->get(route('seller.orders'))->assertOk()->assertSee('Snapshot A')->assertSee('Snapshot B')->assertSee('Saved variant')->assertSee('Green')
            ->assertViewHas('paginator', fn ($p) => $p->first()->items->pluck('id')->all() === [$a->id, $b->id] && $p->first()->gross_amount == 100);
    }

    public function test_item_search_keeps_all_owned_lines_and_reference_search_supports_literal_zero(): void
    {
        $order = $this->order(['reference' => 'REF-0']);
        $this->item($order, ['product_name' => 'Needle']);
        $this->item($order, ['product_name' => 'Other owned line']);
        foreach (['Needle', 'REF-0', 'ORD-1', '0'] as $search) {
            $this->get(route('seller.orders', compact('search')))->assertOk()->assertSee('Other owned line')->assertViewHas('paginator', fn ($p) => $p->total() === 1 && $p->first()->gross_amount == 100);
        }
        $this->get(route('seller.orders', ['search' => "' OR 1=1 --"]))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 0);
    }

    public function test_unknown_owned_statuses_remain_visible_filterable_and_outside_named_summary_categories(): void
    {
        $this->item($this->order(['status' => 'AWAITING_REVIEW']));
        $this->get(route('seller.orders', ['order_status' => 'awaiting_review']))->assertOk()->assertSee('Awaiting Review')
            ->assertViewHas('summary', fn ($s) => $s->total == 1 && $s->pending == 0 && $s->processing == 0 && $s->completed == 0);
    }

    public function test_combined_filters_use_inclusive_manila_creation_date_and_effective_status(): void
    {
        $inside = $this->order(['status' => 'CANCELLED', 'reference' => 'Combined']);
        $group = $this->normalized($inside);
        $this->item($inside, ['seller_order_id' => $group->id]);
        $this->date($inside, '2026-10-23 15:59:59');
        $outside = $this->order(['status' => 'COMPLETED']);
        $this->item($outside);
        $this->date($outside, '2026-10-23 16:00:00');
        $this->get(route('seller.orders', ['search' => 'Combined', 'order_status' => 'completed', 'payment_method' => 'cod', 'date_range' => 'custom', 'from' => '2026-10-23', 'to' => '2026-10-23']))
            ->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1 && $p->first()->order_id === $inside->id);
    }

    public static function presets(): array
    {
        return array_map(fn ($p) => [$p], array_keys(SellerDateFilter::PRESETS));
    }

    #[DataProvider('presets')]
    public function test_date_presets_use_shared_boundaries(string $preset): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(4, 0));
        $this->item($this->order());
        $query = ['date_range' => $preset, 'from' => '2026-10-23', 'to' => '2026-10-23'];
        $expected = in_array($preset, ['yesterday', 'last_week', 'last_month']) ? 0 : 1;
        $this->get(route('seller.orders', $query))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === $expected);
    }

    public static function invalidFilters(): array
    {
        return [[['sort' => 'gross desc; DROP TABLE orders'], 'sort'], [['search' => ['bad']], 'search'], [['page' => 0], 'page'],
            [['order_status' => 'invented'], 'order_status'], [['payment_method' => 'foreign'], 'payment_method'],
            [['date_range' => 'custom', 'from' => '2026-10-24', 'to' => '2026-10-23'], 'to'],
            [['date_range' => 'custom', 'from' => 'invalid', 'to' => '2026-10-23'], 'from'], [['date_range' => 'custom'], 'from']];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $query, string $field): void
    {
        $this->getJson(route('seller.orders', $query))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_sorting_pagination_matching_counts_and_summaries_cover_the_database_cohort(): void
    {
        for ($i = 0; $i < 19; $i++) {
            $order = $this->order(['status' => 'COMPLETED']);
            $this->item($order, ['unit_price' => $i + 1, 'quantity' => 1]);
            $this->date($order, '2026-10-23 00:00:00');
        }
        $this->get(route('seller.orders', ['order_status' => 'completed', 'sort' => 'amount_desc']))->assertOk()->assertSeeText('Showing 1–15 of 19 matching orders')
            ->assertViewHas('summary', fn ($s) => $s->total == 19 && $s->completed == 19 && $s->gross == 190)
            ->assertViewHas('paginator', fn ($p) => $p->count() === 15 && $p->first()->gross_amount == 19 && str_contains($p->url(2), 'order_status=completed'));
        $this->get(route('seller.orders', ['sort' => 'amount_desc', 'page' => 2]))->assertOk()->assertViewHas('paginator', fn ($p) => $p->count() === 4 && $p->first()->gross_amount == 4);
        foreach (['newest', 'oldest', 'amount_asc', 'reference'] as $sort) {
            $this->get(route('seller.orders', compact('sort')))->assertOk()->assertViewHas('paginator', fn ($p) => $p->first()->order_id === 1);
        }
        $this->get(route('seller.orders', ['page' => 99]))->assertOk()->assertSeeText('19 matching orders. No orders on this page.')->assertDontSeeText('Showing 0');
    }

    public function test_chips_are_validated_grouped_escaped_and_preserve_other_criteria_without_page(): void
    {
        $this->item($this->order(['status' => 'COMPLETED']));
        $response = $this->get(route('seller.orders', ['search' => '<script>alert(1)</script>', 'order_status' => 'completed', 'payment_method' => 'cod', 'sort' => 'oldest', 'date_range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-23', 'page' => 3, 'seller_id' => 999]))->assertOk();
        $response->assertViewHas('filterChips', function ($chips) {
            $this->assertCount(5, $chips);
            foreach ($chips as $chip) {
                parse_str(parse_url($chip['url'], PHP_URL_QUERY) ?? '', $q);
                $this->assertArrayNotHasKey('page', $q);
                $this->assertArrayNotHasKey('seller_id', $q);
                if ($chip['key'] === 'order_status') {
                    $this->assertArrayNotHasKey('order_status', $q);
                    $this->assertSame('cod', $q['payment_method']);
                    $this->assertSame('custom', $q['date_range']);
                }
                if ($chip['key'] === 'date_range') {
                    $this->assertArrayNotHasKey('from', $q);
                    $this->assertArrayNotHasKey('to', $q);
                }
            }

            return true;
        })->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get(route('seller.orders', ['sort' => 'newest', 'date_range' => 'all', 'page' => 1, 'unsupported' => 'x']))->assertOk()->assertViewHas('filterChips', []);
        $this->get(route('seller.orders'))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1);
    }

    public function test_summary_categories_have_explicit_status_membership_across_pages(): void
    {
        foreach (array_merge(SellerReports::STATUSES, ['unknown_saved_state']) as $status) {
            $this->item($this->order(compact('status')));
        }
        $this->get(route('seller.orders'))->assertOk()->assertViewHas('summary', fn ($s) => $s->total == 20 && $s->pending == 2 && $s->processing == 4 && $s->ready == 2 && $s->completed == 2 && $s->cancelled == 3)
            ->assertViewHas('paginator', fn ($p) => $p->total() === 20 && $p->count() === 15);
    }

    public function test_saved_date_and_reference_sorts_apply_before_pagination(): void
    {
        $older = $this->order(['reference' => 'Z-reference']);
        $this->item($older);
        $this->date($older, '2026-10-01 00:00:00');
        $newer = $this->order(['reference' => 'A-reference']);
        $this->item($newer);
        $this->date($newer, '2026-10-02 00:00:00');
        foreach (['newest' => $newer->id, 'oldest' => $older->id, 'reference' => $newer->id] as $sort => $expected) {
            $this->get(route('seller.orders', compact('sort')))->assertOk()->assertViewHas('paginator', fn ($p) => $p->first()->order_id === $expected);
        }
    }

    public function test_query_count_is_constant_and_preview_materialization_is_page_bounded(): void
    {
        $this->item($this->order());
        DB::enableQueryLog();
        DB::flushQueryLog();
        SellerOrders::data($this->shop, new Request);
        $single = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 20; $i++) {
            $this->item($this->order());
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $data = SellerOrders::data($this->shop, new Request);
        $this->assertSame($single, count(DB::getQueryLog()));
        $this->assertSame(21, $data['paginator']->total());
        $this->assertSame(15, $data['paginator']->sum(fn ($segment) => $segment->items->count()));
        DB::disableQueryLog();
    }

    public function test_shared_filter_accessibility_and_preview_are_read_only_with_no_mutation_forms(): void
    {
        $this->item($this->order());
        $response = $this->get(route('seller.orders'))->assertOk()->assertSee('data-seller-filters', false)->assertSee('aria-expanded="false"', false)->assertSee('aria-controls="orders-advanced-filters"', false)
            ->assertSee('Read-only seller order preview')->assertDontSee(route('order.status.update', 'ORD-1'));
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//section[@id="orders-advanced-filters" and @hidden]')->length);
        $this->assertSame(0, $xpath->query('//form[translate(@method,"POST","post")="post" and contains(@action,"/orders/")]')->length);
        $this->get(route('seller.orders', ['order_status' => 'placed']))->assertOk()->assertSee('aria-expanded="true"', false);
    }

    public function test_listing_reconciles_completed_gross_with_sales_and_reports(): void
    {
        $this->item($this->order(['status' => 'COMPLETED']));
        $order = $this->order(['seller_id' => null]);
        $group = $this->normalized($order, status: 'delivered');
        $this->item($order, ['seller_order_id' => $group->id, 'unit_price_minor' => 1234]);
        $this->item($this->order(['status' => 'CANCELLED']));
        $this->get(route('seller.orders', ['order_status' => 'completed']))->assertOk()->assertViewHas('summary', fn ($s) => $s->gross == 50);
        $this->get(route('seller.orders', ['order_status' => 'delivered']))->assertOk()->assertViewHas('summary', fn ($s) => $s->gross == 24.68);
        $this->get(route('seller.sales'))->assertOk()->assertViewHas('grossCompleted', 74.68);
        $this->get(route('seller.reports'))->assertOk()->assertViewHas('grossSales', 74.68);
    }

    public function test_get_does_not_change_commerce_tables(): void
    {
        $order = $this->order();
        $group = $this->normalized($order);
        $this->item($order, ['seller_order_id' => $group->id]);
        SellerCategories::populate();
        $product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'Stock fixture', 'slug' => 'stock-fixture', 'is_active' => true]);
        $product->variants()->create(['name' => 'Default', 'sku' => 'READ-ONLY', 'price_minor' => 100, 'stock' => 10, 'is_active' => true]);
        $order->payments()->create(['method' => 'cod', 'amount_minor' => 5000, 'status' => 'pending']);
        $provider = DB::table('logistics_providers')->insertGetId(['user_id' => $this->shop->user_id, 'name' => 'Fixture provider', 'slug' => 'fixture-provider', 'status' => 'approved']);
        $shipment = DB::table('shipments')->insertGetId(['seller_order_id' => $group->id, 'logistics_provider_id' => $provider, 'tracking_code' => 'FIXTURE-TRACK', 'status' => 'assigned', 'fee_minor' => 100, 'cod_amount_minor' => 5000, 'cod_collected' => false, 'attempts' => 0]);
        DB::table('delivery_events')->insert(['shipment_id' => $shipment, 'status' => 'assigned', 'attempt' => 0, 'user_id' => $this->shop->user_id, 'occurred_at' => now()]);
        $tables = ['orders', 'order_items', 'seller_orders', 'products', 'product_variants', 'payments', 'shipments', 'delivery_events'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $this->get(route('seller.orders'))->assertOk();
        $this->get(route('seller.orders', ['search' => 'Saved']))->assertOk();
        $this->assertSame($before, $snapshot());
    }

    public function test_guest_session_flags_and_other_roles_do_not_authorize_persisted_listing(): void
    {
        $this->item($this->order());
        auth()->logout();
        $this->get(route('seller.orders'))->assertRedirect(route('seller.register'));
        $this->withSession(['seller_logged_in' => true])->get(route('seller.orders'))->assertForbidden();
        foreach (['buyer', 'admin', 'rider', 'logistics'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']))->get(route('seller.orders'))->assertForbidden();
        }
    }

    public function test_pending_access_is_local_only_and_never_approves_the_shop(): void
    {
        $this->shop->update(['status' => 'pending']);
        foreach (['testing', 'staging', 'production'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->get(route('seller.orders'))->assertForbidden();
        }
        $this->app->detectEnvironment(fn () => 'local');
        $this->get(route('seller.orders'))->assertOk();
        $this->assertSame('pending', $this->shop->fresh()->status);
    }

    public function test_inactive_suspended_rejected_and_ambiguous_accounts_are_denied(): void
    {
        $user = $this->shop->owner;
        $user->update(['status' => 'inactive']);
        $this->get(route('seller.orders'))->assertForbidden();
        $user->update(['status' => 'active', 'is_suspended' => true]);
        $this->get(route('seller.orders'))->assertForbidden();
        $user->update(['is_suspended' => false]);
        foreach (['rejected', 'suspended'] as $status) {
            $this->shop->update(compact('status'));
            $this->get(route('seller.orders'))->assertForbidden();
        }
        $this->shop->update(['status' => 'approved']);
        Seller::create(['user_id' => $user->id, 'name' => 'Second shop', 'slug' => 'second-shop', 'status' => 'approved']);
        $this->get(route('seller.orders'))->assertStatus(409);
    }
}
