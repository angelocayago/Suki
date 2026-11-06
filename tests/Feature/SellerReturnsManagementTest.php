<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\ReturnRefundEvidence;
use App\Models\ReturnRefundRequest;
use App\Models\Seller;
use App\Models\User;
use App\Services\ReturnRefundAllocationService;
use App\Services\SellerWalletService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Explicit disposable SQLite schema: no application migrations or shared writes. */
class SellerReturnsManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertEmpty(config('database.connections.sqlite.url'));
        foreach ([
            'users' => 'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, role TEXT, status TEXT, email TEXT, phone TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, created_at TEXT, updated_at TEXT',
            'sellers' => 'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, slug TEXT, status TEXT, logo_path TEXT, category_id INTEGER',
            'categories' => 'id INTEGER PRIMARY KEY, name TEXT',
            'orders' => 'id INTEGER PRIMARY KEY, buyer_id INTEGER, order_number TEXT, reference TEXT, status TEXT, payment_method TEXT',
            'seller_orders' => 'id INTEGER PRIMARY KEY, seller_id INTEGER, order_id INTEGER, workflow_status TEXT, status TEXT',
            'order_items' => 'id INTEGER PRIMARY KEY, seller_order_id INTEGER, order_id INTEGER, product_name TEXT, quantity INTEGER, unit_price_minor INTEGER, commission_minor INTEGER, seller_funded_discount_minor INTEGER, platform_funded_discount_minor INTEGER',
            'shipments' => 'id INTEGER PRIMARY KEY, seller_order_id INTEGER',
            'return_refund_requests' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, buyer_id INTEGER, order_id INTEGER, seller_order_id INTEGER, order_item_id INTEGER, seller_id INTEGER, type TEXT, status TEXT, quantity INTEGER, buyer_reason TEXT, buyer_description TEXT, seller_recommendation TEXT, seller_note TEXT, seller_responded_at TEXT, reviewed_by INTEGER, admin_decision TEXT, admin_reason TEXT, decided_at TEXT, refund_minor INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(order_item_id,status)',
            'return_refund_events' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, return_refund_request_id INTEGER, actor_id INTEGER, action TEXT, data TEXT, created_at TEXT, updated_at TEXT',
            'return_refund_evidence' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, return_refund_request_id INTEGER, uploaded_by INTEGER, path TEXT, mime TEXT, size INTEGER, description TEXT, created_at TEXT, updated_at TEXT',
            'return_refund_accounting' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, return_refund_request_id INTEGER UNIQUE, merchandise_minor INTEGER, seller_adjustment_minor INTEGER, commission_reversal_minor INTEGER, seller_voucher_minor INTEGER, platform_voucher_minor INTEGER, refund_status TEXT, created_at TEXT, updated_at TEXT',
            'seller_wallet_entries' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER, seller_order_id INTEGER, payout_id INTEGER, type TEXT, status TEXT, amount_minor INTEGER, reference TEXT, description TEXT, created_at TEXT, updated_at TEXT',
            'payouts' => 'id INTEGER PRIMARY KEY, seller_id INTEGER, amount_minor INTEGER, status TEXT',
            'voucher_redemptions' => 'id INTEGER PRIMARY KEY, seller_order_id INTEGER',
            'notifications' => 'id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT',
            'notification_outbox' => 'id TEXT PRIMARY KEY, user_id INTEGER, title TEXT, message TEXT, context TEXT, available_at TEXT, processed_at TEXT, attempts INTEGER DEFAULT 0, last_error TEXT, created_at TEXT, updated_at TEXT',
        ] as $table => $columns) {
            DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        }
        foreach ([11 => 'seller', 22 => 'buyer', 33 => 'buyer', 44 => 'admin', 55 => 'seller'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'name' => $role.' '.$id, 'first_name' => $id === 22 ? 'Luna' : null,
                'last_name' => $id === 22 ? 'Buyer' : null, 'role' => $role, 'status' => 'active', 'email' => 'PRIVATE-'.$id.'@example.test', 'phone' => 'PRIVATE-PHONE']);
        }
        DB::table('sellers')->insert([
            ['id' => 101, 'user_id' => 11, 'name' => 'Owned Shop', 'slug' => 'owned', 'status' => 'approved'],
            ['id' => 202, 'user_id' => 55, 'name' => 'Other Shop', 'slug' => 'other', 'status' => 'approved'],
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 02:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function record(int $id = 1, string $status = 'SUBMITTED', int $seller = 101, string $date = '2026-11-01 01:00:00'): ReturnRefundRequest
    {
        DB::table('orders')->insert(['id' => $id, 'buyer_id' => 22, 'order_number' => 'ORDER-'.$id, 'reference' => 'REF-'.$id, 'status' => 'COMPLETED', 'payment_method' => 'cod']);
        DB::table('seller_orders')->insert(['id' => $id, 'seller_id' => $seller, 'order_id' => $id, 'workflow_status' => 'DELIVERED', 'status' => 'delivered']);
        DB::table('order_items')->insert(['id' => $id, 'seller_order_id' => $id, 'order_id' => $id, 'product_name' => 'Product '.$id,
            'quantity' => 2, 'unit_price_minor' => 1000, 'commission_minor' => 200, 'seller_funded_discount_minor' => 100, 'platform_funded_discount_minor' => 200]);

        $request = ReturnRefundRequest::create(['buyer_id' => 22, 'order_id' => $id, 'seller_order_id' => $id, 'order_item_id' => $id,
            'seller_id' => $seller, 'type' => 'REFUND', 'status' => $status, 'quantity' => 1, 'buyer_reason' => 'Damaged',
            'buyer_description' => '<script>untrusted description</script>']);
        $request->forceFill(['created_at' => $date])->save();

        return $request;
    }

    private function page(array $query = [])
    {
        return $this->actingAs(User::findOrFail(11))->get(route('seller.return-refunds.index', $query))->assertOk();
    }

    public function test_summary_uses_all_owned_records_and_listing_is_newest_first(): void
    {
        foreach (['SUBMITTED', 'SELLER_RESPONDED', 'UNDER_REVIEW', 'ADMIN_APPROVED', 'ADMIN_REJECTED'] as $index => $status) {
            $this->record($index + 1, $status);
        }
        $this->record(6, 'ADMIN_APPROVED', 202);
        $page = $this->page()->assertSee('Luna Buyer')->assertDontSee('Product 6');
        $this->assertSame(['Total requests' => 5, 'Pending review' => 3, 'Approved by Admin' => 1, 'Rejected by Admin' => 1], $page->viewData('summary'));
        $this->assertSame([5, 4, 3, 2, 1], $page->viewData('requests')->pluck('id')->all());
        $page->assertSee('<details class="rr-filter-panel">', false)->assertDontSee('<details class="rr-filter-panel" open', false);
        $this->assertSame(5, $this->page(['search' => 'missing'])->viewData('summary')['Total requests']);
        if ($dir = getenv('SELLER_RETURNS_CHECK_DIR')) file_put_contents($dir.'/index.html', $page->getContent());
    }

    public function test_search_status_and_date_filters_combine_with_literal_search_and_owned_scope(): void
    {
        $this->record(); $this->record(2, 'ADMIN_REJECTED'); $this->record(3, 'SUBMITTED', 202);
        foreach (['order-1', 'ref-1', 'Luna Buyer', 'BUYER 22', 'Product 1'] as $search) {
            $page = $this->page(['search' => $search, 'status' => 'SUBMITTED', 'date' => 'today']);
            $this->assertSame([1], $page->viewData('requests')->pluck('id')->all());
        }
        foreach (['%', '_', 'no match'] as $search) $this->assertSame(0, $this->page(['search' => $search])->viewData('requests')->total());
        foreach (['SUBMITTED', 'SELLER_RESPONDED', 'UNDER_REVIEW', 'ADMIN_APPROVED', 'ADMIN_REJECTED'] as $status) {
            $rows = $this->page(['status' => $status])->viewData('requests');
            foreach ($rows as $row) $this->assertSame($status, $row->status);
        }
    }

    public function test_manila_calendar_boundaries_for_today_seven_and_thirty_days(): void
    {
        foreach (['2026-10-31 15:59:59', '2026-10-31 16:00:00', '2026-11-01 15:59:59', '2026-11-01 16:00:00', '2026-10-25 16:00:00', '2026-10-25 15:59:59', '2026-10-02 16:00:00', '2026-10-02 15:59:59'] as $index => $date) $this->record($index + 1, date: $date);
        $this->assertSame([3, 2], $this->page(['date' => 'today'])->viewData('requests')->pluck('id')->all());
        $this->assertSame([3, 2, 1, 5], $this->page(['date' => '7'])->viewData('requests')->pluck('id')->all());
        $this->assertSame([3, 2, 1, 5, 6, 7], $this->page(['date' => '30'])->viewData('requests')->pluck('id')->all());
        $this->assertSame(8, $this->page()->viewData('requests')->total());
    }

    public function test_pagination_preserves_filters_clear_all_and_empty_states(): void
    {
        $this->page()->assertSee('No return or refund requests yet');
        for ($id = 1; $id <= 17; $id++) $this->record($id);
        $page = $this->page(['search' => 'Product', 'status' => 'SUBMITTED', 'date' => 'today']);
        $this->assertCount(15, $page->viewData('requests'));
        $page->assertSee('search=Product', false)->assertSee('status=SUBMITTED', false)->assertSee('date=today', false)->assertSee('page=2', false);
        $this->assertSame([2, 1], $this->page(['search' => 'Product', 'status' => 'SUBMITTED', 'date' => 'today', 'page' => 2])->viewData('requests')->pluck('id')->all());
        $this->page(['search' => 'missing'])->assertSee('No matching requests')->assertSee('href="'.route('seller.return-refunds.index').'"', false);
        $this->assertSame(17, $this->page()->viewData('requests')->total());
        foreach (['status' => 'APPROVED', 'date' => 'invalid', 'search' => str_repeat('a', 101), 'page' => 0] as $key => $value) {
            $this->getJson(route('seller.return-refunds.index', [$key => $value]))->assertUnprocessable();
        }
    }

    public function test_details_show_recorded_history_and_authorized_evidence_without_private_buyer_data(): void
    {
        $request = $this->record();
        DB::table('return_refund_events')->insert(['return_refund_request_id' => 1, 'actor_id' => 22, 'action' => 'SUBMITTED', 'created_at' => '2026-11-01 01:00:00']);
        $evidence = $this->evidence($request);
        $page = $this->actingAs(User::findOrFail(11))->get(route('seller.return-refunds.show', $request))->assertOk()
            ->assertSee('Request RR-1')->assertSee('Requested resolution')->assertSee('Request timeline')->assertSee('Awaiting seller response')
            ->assertSee('&lt;script&gt;untrusted description&lt;/script&gt;', false)->assertSee(route('return-refunds.evidence.show', [$request, $evidence]), false)
            ->assertDontSee('PRIVATE-22@example.test')->assertDontSee('PRIVATE-PHONE')->assertDontSee('secret-evidence-path')
            ->assertSee('name="_token"', false)->assertSee('name="_method" value="PATCH"', false)->assertSee('id="rrConfirm"', false);
        if ($dir = getenv('SELLER_RETURNS_CHECK_DIR')) file_put_contents($dir.'/show.html', $page->getContent());
        $this->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertOk();
        $this->actingAs(User::findOrFail(55))->get(route('seller.return-refunds.show', $request))->assertNotFound();
        $this->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertForbidden();
        $other = $this->record(2, seller: 202); $otherEvidence = $this->evidence($other);
        $this->actingAs(User::findOrFail(11))->get(route('return-refunds.evidence.show', [$request, $otherEvidence]))->assertNotFound();
    }

    private function evidence(ReturnRefundRequest $request): ReturnRefundEvidence
    {
        $directory = getenv('SELLER_RETURNS_CHECK_DIR');
        $this->assertNotEmpty($directory, 'Tests require an explicit disposable storage directory.');
        $this->assertStringStartsWith(realpath(sys_get_temp_dir()), realpath($directory));
        config(['filesystems.disks.local.root' => $directory.'/evidence/'.$this->name()]);
        Storage::forgetDisk('local');
        Storage::disk('local')->put('secret-evidence-path/'.$request->id.'.jpg', 'disposable evidence');
        return ReturnRefundEvidence::create(['return_refund_request_id' => $request->id, 'uploaded_by' => 22,
            'path' => 'secret-evidence-path/'.$request->id.'.jpg', 'mime' => 'image/jpeg', 'size' => 19, 'description' => 'Damaged packaging']);
    }

    public function test_recommendations_require_explanation_are_owned_and_cannot_be_duplicated_or_finalize_money(): void
    {
        $request = $this->record(); $foreign = $this->record(2, seller: 202);
        $this->actingAs(User::findOrFail(11));
        $this->patchJson(route('seller.return-refunds.respond', $foreign), ['recommendation' => 'APPROVE', 'note' => 'Forged'])->assertForbidden();
        foreach ([['recommendation' => 'APPROVE', 'note' => ''], ['recommendation' => 'ADMIN_APPROVED', 'note' => 'Invalid']] as $data) {
            $this->patchJson(route('seller.return-refunds.respond', $request), $data)->assertUnprocessable();
        }
        foreach (['APPROVE', 'REJECT'] as $index => $recommendation) {
            $owned = $index ? $this->record(3) : $request;
            $this->patchJson(route('seller.return-refunds.respond', $owned), ['recommendation' => $recommendation, 'note' => 'Evidence reviewed'])->assertOk();
            $this->assertDatabaseHas('return_refund_requests', ['id' => $owned->id, 'status' => 'SELLER_RESPONDED', 'seller_recommendation' => $recommendation]);
            $this->patchJson(route('seller.return-refunds.respond', $owned), ['recommendation' => $recommendation, 'note' => 'Duplicate'])->assertUnprocessable();
            $this->get(route('seller.return-refunds.show', $owned))->assertOk()->assertSee('Evidence reviewed')->assertDontSee('<form id="rrRecommendation"', false);
        }
        $this->patchJson(route('admin.return-refunds.decide', $request), ['decision' => 'APPROVED', 'reason' => 'No authority'])->assertForbidden();
        $this->assertSame(0, DB::table('seller_wallet_entries')->count());
        $this->assertSame(0, DB::table('return_refund_accounting')->count());
        $this->assertSame(2, DB::table('return_refund_events')->count());
    }

    public function test_existing_request_pages_remain_accessible_but_unreviewed_new_financial_flows_are_disabled(): void
    {
        $request=$this->record();
        $data=['type'=>'REFUND','quantity'=>1,'reason'=>'Damaged','description'=>'Damaged on arrival'];
        $this->actingAs(User::findOrFail(22))->postJson(route('buyer.return-refunds.store',1),$data)->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->actingAs(User::findOrFail(33))->get(route('buyer.return-refunds.show',$request))->assertNotFound();
        $this->actingAs(User::findOrFail(22))->get(route('buyer.return-refunds.show',$request))->assertOk();
        $this->get(route('seller.return-refunds.index'))->assertForbidden();
        $this->actingAs(User::findOrFail(44))->get(route('admin.return-refunds.show',$request))->assertOk()->assertSee('Finalize decision');
        $this->patchJson(route('admin.return-refunds.decide',$request),['decision'=>'APPROVED','reason'=>'Not reviewed for deployment'])->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->assertSame('SUBMITTED',$request->fresh()->status);
        $this->assertSame(0,DB::table('seller_wallet_entries')->count());
        $this->assertSame(0,DB::table('return_refund_accounting')->count());
    }
    public function test_admin_rejection_has_no_refund_adjustment_and_closed_states_do_not_offer_response(): void
    {
        $request = $this->record();
        $this->actingAs(User::findOrFail(44))->patchJson(route('admin.return-refunds.decide', $request), ['decision' => 'REJECTED', 'reason' => 'Evidence insufficient'])->assertOk();
        $this->actingAs(User::findOrFail(11))->get(route('seller.return-refunds.show', $request))->assertOk()->assertSee('Rejected by Admin')->assertSee('Evidence insufficient')->assertDontSee('<form id="rrRecommendation"', false);
        foreach (['ADMIN_REJECTED', 'ADMIN_APPROVED', 'UNDER_REVIEW'] as $index => $status) {
            $closed = $this->record($index + 2, $status);
            $this->patchJson(route('seller.return-refunds.respond', $closed), ['recommendation' => 'APPROVE', 'note' => 'Too late'])->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('seller_wallet_entries')->count());
    }

    public function test_wallet_summary_and_allocation_are_unchanged_and_reads_have_no_financial_writes(): void
    {
        $request = $this->record();
        DB::table('seller_wallet_entries')->insert(['seller_id' => 101, 'type' => 'SALE_EARNING', 'status' => 'AVAILABLE', 'amount_minor' => 2000]);
        DB::table('seller_wallet_entries')->insert(['seller_id' => 101, 'type' => 'PAYOUT_RESERVATION', 'status' => 'RESERVED', 'amount_minor' => 500]);
        $service = app(SellerWalletService::class); $seller = Seller::findOrFail(101); $before = $service->summary($seller);
        $this->assertSame(1500, $before['available_minor']);
        $calculation = app(ReturnRefundAllocationService::class)->calculate(OrderItem::findOrFail(1), 2);
        $this->assertSame(1700, $calculation['buyer_refund_due_minor']);
        $this->assertSame(1700, $calculation['seller_adjustment_minor']);
        $this->page(); $this->get(route('seller.return-refunds.show', $request))->assertOk();
        $this->assertSame($before, $service->summary($seller));
        $this->assertSame(2, DB::table('seller_wallet_entries')->count());
        $this->assertSame(0, DB::table('return_refund_accounting')->count());
    }

    public function test_owned_order_constraint_middleware_and_csrf_remain_in_place(): void
    {
        $request = $this->record(); DB::table('seller_orders')->where('id', 1)->update(['seller_id' => 202]);
        $this->assertSame(0, $this->page()->viewData('requests')->total());
        $this->get(route('seller.return-refunds.show', $request))->assertNotFound();
        foreach (['index', 'show', 'respond'] as $action) {
            $middleware = app('router')->getRoutes()->getByName('seller.return-refunds.'.$action)->gatherMiddleware();
            foreach (['web', 'auth', 'not_suspended', 'approved', 'role:seller'] as $required) $this->assertContains($required, $middleware);
        }
        $this->app['env'] = 'production'; // Activate real CSRF middleware; DB stays verified SQLite memory.
        $this->patchJson(route('seller.return-refunds.respond', $request), ['recommendation' => 'APPROVE', 'note' => 'No token'])->assertStatus(419);
        $this->assertSame('SUBMITTED', $request->fresh()->status);
    }
}
