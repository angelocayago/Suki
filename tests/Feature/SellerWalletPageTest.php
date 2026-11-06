<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Models\Seller;
use App\Models\User;
use App\Services\SellerWalletService;
use App\Support\SellerWalletPage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Guarded memory-only fixtures. No migrations or shared financial writes. */
class SellerWalletPageTest extends TestCase
{
    private User $owner;
    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertEmpty(config('database.connections.sqlite.url'));
        foreach ([
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, slug TEXT, logo_path TEXT, category_id INTEGER, status TEXT, created_at TEXT, updated_at TEXT',
            'categories'=>'id INTEGER PRIMARY KEY, name TEXT',
            'return_refund_requests'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, status TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, seller_id INTEGER',
            'complaints'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, status TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT',
            'payouts'=>'id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER, amount_minor INTEGER, status TEXT, method TEXT, reference TEXT, reason TEXT, reviewed_by INTEGER, reviewed_at TEXT, paid_at TEXT, created_at TEXT, updated_at TEXT',
            'seller_wallet_entries'=>'id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER, seller_order_id INTEGER, payout_id INTEGER, type TEXT, status TEXT, amount_minor INTEGER, reference TEXT, description TEXT, created_at TEXT, updated_at TEXT',
        ] as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        $this->owner = User::forceCreate(['id'=>11, 'name'=>'Wallet Owner', 'first_name'=>'Wallet', 'last_name'=>'Owner', 'role'=>'seller', 'status'=>'active']);
        User::forceCreate(['id'=>22, 'name'=>'Other Seller', 'role'=>'seller', 'status'=>'active']);
        User::forceCreate(['id'=>33, 'name'=>'Buyer', 'role'=>'buyer', 'status'=>'active']);
        User::forceCreate(['id'=>44, 'name'=>'Admin', 'role'=>'admin', 'status'=>'active']);
        $this->shop = Seller::create(['user_id'=>11, 'name'=>'Owned Store', 'slug'=>'wallet-owned', 'status'=>'approved']);
        Seller::create(['user_id'=>22, 'name'=>'Foreign Store', 'slug'=>'wallet-foreign', 'status'=>'approved']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function entry(string $type, string $status, int $minor, ?int $payoutId = null, ?int $sellerId = null): void
    {
        DB::table('seller_wallet_entries')->insert(['seller_id'=>$sellerId ?? $this->shop->id, 'payout_id'=>$payoutId, 'type'=>$type, 'status'=>$status, 'amount_minor'=>$minor, 'created_at'=>'2026-11-01 00:00:00']);
    }

    private function payout(string $status = 'PENDING', string $created = '2026-11-01 00:00:00', array $extra = []): Payout
    {
        $payout = Payout::create($extra + ['seller_id'=>$this->shop->id, 'amount_minor'=>12345, 'status'=>$status]);
        $payout->forceFill(['created_at'=>$created])->save();
        return $payout;
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->owner)->get(route('seller.wallet', $query))->assertOk();
    }

    public function test_real_canonical_balances_render_read_only_and_ignore_foreign_records(): void
    {
        $this->entry('SALE_EARNING', 'AVAILABLE', 100000);
        $this->entry('REFUND_ADJUSTMENT', 'AVAILABLE', -5000);
        $this->entry('SALE_EARNING', 'HELD', 30000);
        $this->entry('PAYOUT_RESERVATION', 'RESERVED', 20000);
        $paid = $this->payout('PAID');
        $this->entry('PAYOUT_COMPLETED', 'POSTED', 12345, $paid->id);
        $foreign = Seller::where('user_id',22)->firstOrFail();
        $this->entry('SALE_EARNING', 'AVAILABLE', 99999999, null, $foreign->id);
        $this->payout('PAID', extra:['seller_id'=>$foreign->id, 'reference'=>'FOREIGN-REFERENCE', 'paid_at'=>'2026-11-01 02:00:00']);
        $before = DB::table('seller_wallet_entries')->get()->toJson();
        $response = $this->page(['seller_id'=>$foreign->id]);
        $response->assertViewHas('wallet', ['available_minor'=>62655, 'held_minor'=>30000, 'reserved_minor'=>20000, 'paid_out_minor'=>12345, 'total_earnings_minor'=>130000])
            ->assertSee('&#8369;626.55',false)->assertSee('&#8369;300.00',false)->assertSee('&#8369;200.00',false)->assertSee('&#8369;123.45',false)
            ->assertDontSee('FOREIGN-REFERENCE')->assertViewHas('payouts', fn ($p) => $p->total() === 1)
            ->assertSee('name="_token"',false)->assertSee('id="walletPayoutDialog"',false)->assertSee('action="'.route('seller.payouts.store').'"',false);
        $this->assertSame($before, DB::table('seller_wallet_entries')->get()->toJson());
        if ($dir=getenv('SELLER_WALLET_CHECK_DIR')) file_put_contents($dir.'/wallet.html', $response->getContent());
    }

    public function test_history_shows_only_canonical_statuses_and_authorized_details_escaped(): void
    {
        foreach (['PENDING','APPROVED','PAID','REJECTED'] as $status) $this->payout($status, extra: match ($status) {
            'PAID'=>['paid_at'=>'2026-11-01 03:00:00', 'reference'=>'PAID-REFERENCE'],
            'REJECTED'=>['reviewed_at'=>'2026-11-01 02:00:00', 'reason'=>'<script>rejection</script>'],
            'APPROVED'=>['reviewed_at'=>'2026-11-01 01:00:00'], default=>[],
        });
        $response = $this->page();
        foreach (SellerWalletPage::STATUSES as $value=>$label) $response->assertSee('wallet-status-'.strtolower($value))->assertSee($label);
        $response->assertSee('PAID-REFERENCE')->assertSee('&lt;script&gt;rejection&lt;/script&gt;',false)->assertDontSee('<script>rejection</script>',false)
            ->assertSee('Nov 1, 2026 · 11:00 AM')->assertSee('Reviewed')->assertSee('Times in Asia/Manila');
    }

    public function test_status_date_combination_paginates_preserves_url_and_never_filters_balances(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 04:00:00','UTC'));
        $this->entry('SALE_EARNING','AVAILABLE',54321);
        for ($i=0; $i<18; $i++) $this->payout('PENDING');
        $this->payout('PAID'); $this->payout('PENDING','2026-10-20 00:00:00');
        $response = $this->page(['status'=>'PENDING','date'=>'7d']);
        $response->assertViewHas('payouts', fn ($p) => $p->total() === 18 && $p->count() === 15)
            ->assertViewHas('wallet', fn ($w) => $w['available_minor'] === 54321)
            ->assertSee('status=PENDING',false)->assertSee('date=7d',false)->assertSee('page=2',false)->assertSee('2 active')
            ->assertSee('<details class="wallet-filters">',false)->assertSee('href="'.route('seller.wallet').'"',false);
        $ids = $response->viewData('payouts')->pluck('id')->all();
        $second = $this->page(['status'=>'PENDING','date'=>'7d','page'=>2])->viewData('payouts');
        $this->assertCount(3,$second); $this->assertEmpty(array_intersect($ids,$second->pluck('id')->all()));
        $this->assertSame([18,17,16], array_slice($ids,0,3));
        $this->assertSame(20,$this->page()->viewData('payouts')->total());
    }

    public function test_manila_calendar_presets_include_start_and_exclude_next_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 04:00:00','UTC'));
        foreach (['today'=>0, '7d'=>6, '30d'=>29] as $preset=>$days) {
            DB::table('payouts')->delete();
            $start=CarbonImmutable::parse('2026-11-01 00:00:00','Asia/Manila')->subDays($days)->utc();
            $included=$this->payout(created:$start->toDateTimeString());
            $this->payout(created:$start->subSecond()->toDateTimeString());
            $end=$this->payout(created:'2026-11-01 15:59:59');
            $this->payout(created:'2026-11-01 16:00:00');
            $this->assertSame([$end->id,$included->id],$this->page(['date'=>$preset])->viewData('payouts')->pluck('id')->all());
        }
    }

    public function test_filter_validation_empty_results_and_zero_balance(): void
    {
        $this->page()->assertSee('No payout requests yet.')->assertSee('&#8369;0.00',false)->assertSee('id="walletPayoutSubmit" class="wallet-primary" type="submit" disabled',false);
        $this->payout();
        $this->page(['status'=>'REJECTED'])->assertSee('No payout requests match your filters.');
        foreach (['status'=>'PROCESSING','date'=>'year','page'=>'-1'] as $key=>$value) $this->actingAs($this->owner)->from(route('seller.wallet'))->get(route('seller.wallet',[$key=>$value]))->assertRedirect(route('seller.wallet'))->assertSessionHasErrors($key);
    }

    public function test_valid_requests_reserve_owned_balance_and_cannot_spend_same_funds_twice(): void
    {
        $this->entry('SALE_EARNING','AVAILABLE',10001);
        $foreign=Seller::where('user_id',22)->firstOrFail();
        $this->actingAs($this->owner)->from(route('seller.wallet'))->post(route('seller.payouts.store'),['amount'=>'100.01','seller_id'=>$foreign->id,'status'=>'PAID'])
            ->assertRedirect(route('seller.wallet'))->assertSessionHas('success','Payout requested.');
        $payout=Payout::sole(); $this->assertSame($this->shop->id,$payout->seller_id); $this->assertSame('PENDING',$payout->status); $this->assertSame(10001,$payout->amount_minor);
        $this->assertSame(10001,(int)DB::table('seller_wallet_entries')->where('payout_id',$payout->id)->where('status','RESERVED')->value('amount_minor'));
        $this->assertSame(0,app(SellerWalletService::class)->summary($this->shop)['available_minor']);
        $this->post(route('seller.payouts.store'),['amount'=>'100.01'])->assertSessionHasErrors('amount'); $this->assertSame(1,Payout::count());
    }

    public function test_invalid_requests_do_not_change_ledger_and_errors_remain_visible(): void
    {
        $this->entry('SALE_EARNING','AVAILABLE',10000);
        foreach (['','0','0.00','-1','1.001','1e2','1,000','100.01','92233720368547758.08','999999999999999999999999.99',str_repeat('9',1000),"1.00\n1"] as $amount) {
            $this->actingAs($this->owner)->from(route('seller.wallet'))->post(route('seller.payouts.store'),['amount'=>$amount])->assertRedirect(route('seller.wallet'))->assertSessionHasErrors('amount');
            $this->assertSame(0,Payout::count()); $this->assertSame(1,DB::table('seller_wallet_entries')->count());
        }
        $this->actingAs($this->owner)->from(route('seller.wallet'))->post(route('seller.payouts.store'),['amount'=>'100.01'])->assertSessionHasErrors('amount');
        $this->page()->assertSee('id="walletAmountError"',false)->assertSee('Amount exceeds available balance.')->assertSee('value="100.01"',false);
    }

    public function test_existing_admin_review_and_payment_service_remains_compatible(): void
    {
        $wallet=app(SellerWalletService::class); $admin=User::findOrFail(44);
        $this->entry('SALE_EARNING','AVAILABLE',100000);
        $rejected=$wallet->request($this->shop,10000); $wallet->review($rejected,$admin,'REJECTED','Review reason');
        $this->assertSame('RELEASED',DB::table('seller_wallet_entries')->where('payout_id',$rejected->id)->value('status'));
        $paid=$wallet->request($this->shop,20000); $wallet->review($paid,$admin,'APPROVED'); $wallet->markPaid($paid,$admin,'manual','PAID-TEST');
        $this->assertSame('PAID',$paid->fresh()->status);
        $this->assertSame(80000,$wallet->summary($this->shop)['available_minor']);
        $this->assertSame(20000,$wallet->summary($this->shop)['paid_out_minor']);
        $this->assertSame(1,DB::table('seller_wallet_entries')->where('type','PAYOUT_COMPLETED')->count());
        if ($dir=getenv('SELLER_WALLET_CHECK_DIR')) file_put_contents($dir.'/financial-audit.json',json_encode(['opening_available_minor'=>100000,'paid_minor'=>20000,'summary_after_paid'=>$wallet->summary($this->shop)]));
        $this->expectException(ValidationException::class); $wallet->markPaid($paid,$admin,'manual','DUPLICATE');
    }

    public function test_ownership_eligibility_routes_and_admin_actions_are_protected(): void
    {
        $this->get(route('seller.wallet'))->assertRedirect(route('login'));
        $this->actingAs(User::findOrFail(33))->get(route('seller.wallet'))->assertForbidden();
        $this->actingAs(User::findOrFail(33))->post(route('seller.payouts.store'),['amount'=>'1'])->assertForbidden();
        $payout=$this->payout();
        $this->actingAs($this->owner)->patch(route('admin.payouts.review',$payout),['status'=>'APPROVED'])->assertForbidden();
        $this->post(route('admin.payouts.paid',$payout),['method'=>'manual'])->assertForbidden();
        foreach (['seller.wallet'=>'GET','seller.payouts.store'=>'POST'] as $name=>$method) {
            $route=app('router')->getRoutes()->getByName($name); $this->assertContains($method,$route->methods());
            foreach (['auth','not_suspended','approved','role:seller'] as $middleware) $this->assertContains($middleware,$route->gatherMiddleware());
        }
        $this->shop->update(['status'=>'pending']);
        $this->get(route('seller.wallet'))->assertNotFound();
        $this->post(route('seller.payouts.store'),['amount'=>'1'])->assertNotFound();
        $this->assertSame('PENDING',$payout->fresh()->status);
    }

    public function test_integer_money_formatting_preserves_centavos_at_large_and_negative_values(): void
    {
        foreach ([0=>'0.00',1=>'0.01',12345=>'123.45',-12345=>'-123.45',PHP_INT_MAX=>'92,233,720,368,547,758.07',PHP_INT_MIN=>'-92,233,720,368,547,758.08'] as $minor=>$expected) $this->assertSame($expected,SellerWalletPage::money($minor));
    }

    public function test_complete_lifecycle_and_sequential_payouts_never_restore_paid_funds(): void
    {
        $wallet=app(SellerWalletService::class); $admin=User::findOrFail(44);
        $this->entry('SALE_EARNING','AVAILABLE',100000);
        $assertBalances=function (int $available, int $reserved, int $paid) use ($wallet): void {
            $this->assertSame(['available_minor'=>$available,'held_minor'=>0,'reserved_minor'=>$reserved,'paid_out_minor'=>$paid,'total_earnings_minor'=>100000],$wallet->summary($this->shop));
        };
        $assertBalances(100000,0,0);
        $first=$wallet->request($this->shop,20000); $assertBalances(80000,20000,0);
        $wallet->review($first,$admin,'APPROVED'); $assertBalances(80000,20000,0);
        $wallet->markPaid($first,$admin,'manual','FIRST'); $assertBalances(80000,0,20000);
        $this->actingAs($this->owner)->from(route('seller.wallet'))->post(route('seller.payouts.store'),['amount'=>'800.01'])->assertSessionHasErrors('amount');
        $this->assertSame(1,Payout::count()); $assertBalances(80000,0,20000);
        // A legitimate repeated amount is allowed, using only the remaining funds.
        $second=$wallet->request($this->shop,20000); $assertBalances(60000,20000,20000);
        $wallet->review($second,$admin,'APPROVED'); $wallet->markPaid($second,$admin,'bank','SECOND'); $assertBalances(60000,0,40000);
        $rejected=$wallet->request($this->shop,10000); $assertBalances(50000,10000,40000);
        $wallet->review($rejected,$admin,'REJECTED','Not proceeding'); $assertBalances(60000,0,40000);
        $this->assertSame(2,DB::table('seller_wallet_entries')->where('type','PAYOUT_COMPLETED')->where('status','POSTED')->where('amount_minor',20000)->count());
        $this->assertSame(2,DB::table('seller_wallet_entries')->where('type','PAYOUT_RESERVATION')->where('status','SETTLED')->count());
        $this->assertSame(1,DB::table('seller_wallet_entries')->where('type','SALE_EARNING')->count());
    }

    public function test_historical_completed_entries_require_matching_paid_payout_and_deduct_each_identity_once(): void
    {
        $this->entry('SALE_EARNING','AVAILABLE',100000);
        $valid=$this->payout('PAID',extra:['amount_minor'=>20000]);
        $this->entry('PAYOUT_COMPLETED','POSTED',20000,$valid->id);
        $this->entry('PAYOUT_COMPLETED','POSTED',20000,$valid->id); // Duplicate historical row, no repair.
        $this->entry('PAYOUT_RESERVATION','SETTLED',20000,$valid->id);
        $this->entry('PAYOUT_RESERVATION','RELEASED',10000);
        $this->entry('PAYOUT_COMPLETED','POSTED',1000,$valid->id); // Amount mismatch.
        $this->entry('PAYOUT_COMPLETED','RESERVED',20000,$valid->id); // Invalid completion status.
        foreach (['PENDING','APPROVED','REJECTED'] as $status) $this->entry('PAYOUT_COMPLETED','POSTED',12345,$this->payout($status)->id);
        $this->entry('PAYOUT_COMPLETED','POSTED',12345,999999); // Missing parent.
        $foreign=Seller::where('user_id',22)->firstOrFail();
        $this->entry('PAYOUT_COMPLETED','POSTED',12345,$this->payout('PAID',extra:['seller_id'=>$foreign->id])->id);
        $before=DB::table('seller_wallet_entries')->get()->toJson();
        DB::enableQueryLog();
        $summary=app(SellerWalletService::class)->summary($this->shop);
        $queries=DB::getQueryLog(); DB::disableQueryLog();
        // SQLite uses two schema-capability reads for pre-migration deployments;
        // financial totals still use one coherent aggregate statement.
        $this->assertCount(3,$queries);
        $this->assertSame(80000,$summary['available_minor']); $this->assertSame(0,$summary['reserved_minor']);
        // Preserve the existing raw ledger paid-out reporting definition; anomalies need reconciliation.
        $this->assertSame((int)DB::table('seller_wallet_entries')->where('type','PAYOUT_COMPLETED')->sum('amount_minor'),$summary['paid_out_minor']);
        $this->assertSame($before,DB::table('seller_wallet_entries')->get()->toJson());
    }

    public function test_supported_integer_boundary_and_leading_zero_amounts_are_converted_without_overflow(): void
    {
        $this->entry('SALE_EARNING','AVAILABLE',PHP_INT_MAX);
        $this->actingAs($this->owner)->from(route('seller.wallet'))->post(route('seller.payouts.store'),['amount'=>'92233720368547758.07'])->assertSessionHasNoErrors();
        $this->assertSame(PHP_INT_MAX,Payout::sole()->amount_minor);
        $this->assertSame(0,app(SellerWalletService::class)->summary($this->shop)['available_minor']);
        DB::table('payouts')->delete(); DB::table('seller_wallet_entries')->where('type','PAYOUT_RESERVATION')->delete();
        foreach (['000200.00'=>20000,'0.01'=>1,'20.5'=>2050] as $amount=>$minor) {
            $this->post(route('seller.payouts.store'),['amount'=>$amount])->assertSessionHasNoErrors();
            $this->assertSame($minor,Payout::latest('id')->first()->amount_minor);
        }
    }
}
