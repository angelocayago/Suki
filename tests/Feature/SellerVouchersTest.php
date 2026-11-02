<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherService;
use App\Support\SellerVouchers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Guarded in-memory fixtures. No migrations or live database writes. */
class SellerVouchersTest extends TestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName(),'Refuse writes outside the isolated memory database.');
        $this->assertEmpty(config('database.connections.sqlite.url'));
        foreach([
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, status TEXT, created_at TEXT, updated_at TEXT',
            'vouchers'=>'id INTEGER PRIMARY KEY, code TEXT UNIQUE, scope TEXT, seller_id INTEGER, discount_type TEXT, discount_minor INTEGER DEFAULT 0, discount_bps INTEGER DEFAULT 0, minimum_spend_minor INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, starts_at TEXT, expires_at TEXT, usage_limit INTEGER, used_count INTEGER DEFAULT 0, archived_at TEXT, created_at TEXT, updated_at TEXT',
            'voucher_redemptions'=>'id INTEGER PRIMARY KEY, voucher_id INTEGER, code_snapshot TEXT, discount_minor INTEGER',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
        ] as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        $this->seller=User::forceCreate(['id'=>11,'name'=>'Seller Fixture','role'=>'seller','status'=>'active']);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'name'=>'Owned shop','status'=>'approved'],['id'=>202,'user_id'=>22,'name'=>'Foreign shop','status'=>'approved']]);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00','UTC'));
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();
        $this->travelBack();
        parent::tearDown();
    }

    private function payload(array $overrides=[]): array
    {
        return array_replace(['code'=>'SHOPTEST','discount_type'=>'FIXED','discount_value'=>'11.23','usage_limit'=>'','is_active'=>'1'],$overrides);
    }

    private function voucher(array $overrides=[]): Voucher
    {
        return Voucher::create(array_replace(['code'=>'EXISTING','scope'=>'SELLER','seller_id'=>101,'discount_type'=>'FIXED','discount_minor'=>100,'is_active'=>true],$overrides))->fresh();
    }

    private function unavailable(string $code, array $subtotals=[101=>2000]): void
    {
        try { app(VoucherService::class)->resolve([$code],collect($subtotals)); $this->fail('Unavailable voucher was redeemed.'); }
        catch(ValidationException $e) { $this->assertArrayHasKey('voucher',$e->errors()); }
    }

    public function test_create_and_management_pages_render_real_vouchers_and_supported_fields(): void
    {
        $this->voucher(); $this->voucher(['code'=>'FOREIGN','seller_id'=>202]); $this->voucher(['code'=>'PLATFORM','scope'=>'PLATFORM','seller_id'=>null]);
        $create=$this->actingAs($this->seller)->get(route('seller.vouchers.create'))->assertOk()
            ->assertSee('Create Shop Voucher')->assertSee('Basic Information')->assertSee('Discount Settings')->assertSee('Usage &amp; Validity',false)
            ->assertSee('LEAVE EMPTY TO AUTO-GENERATE')->assertSee('Generate Code')->assertSee('name="_token"',false)->assertSee('name="creation_token"',false)
            ->assertSee('name="minimum_spend"',false)->assertDontSee('name="min_purchase_amount"',false)->assertDontSee('name="per_buyer_limit"',false)->assertDontSee('name="description"',false)
            ->assertDontSee('not currently supported')->assertDontSee('Codes are saved in uppercase.')->assertDontSee('voucherDiscountHelp')->assertDontSee('Dates and times are in Asia/Manila.');
        $this->actingAs($this->seller)->withSession(['seller_promotions'=>[['name'=>'POISON']]])->get(route('seller.vouchers.index'))->assertOk()->assertSee('EXISTING')->assertDontSee('FOREIGN')->assertDontSee('PLATFORM')->assertDontSee('POISON')->assertSee(route('seller.vouchers.edit',1));
        $this->assertSame(3,Voucher::count());
        if($dir=getenv('SELLER_VOUCHER_CHECK_DIR')) file_put_contents($dir.'/voucher.html',$create->getContent());
    }

    public function test_manual_code_is_normalized_preserved_and_created_with_canonical_attributes(): void
    {
        $this->actingAs($this->seller)->post(route('seller.vouchers.store'),$this->payload(['code'=>'  my-shop  ','is_active'=>'0']))->assertSessionHasNoErrors()->assertRedirect(route('seller.vouchers.index'));
        $voucher=Voucher::sole();
        $this->assertSame('MY-SHOP',$voucher->code); $this->assertSame('SELLER',$voucher->scope); $this->assertSame(101,$voucher->seller_id);
        $this->assertSame(1123,$voucher->discount_minor); $this->assertSame(0,$voucher->discount_bps);
        $this->assertFalse($voucher->is_active); $this->assertNull($voucher->usage_limit); $this->assertSame(0,$voucher->used_count);
        $this->unavailable('MY-SHOP');
    }

    public function test_blank_code_is_generated_and_form_replay_does_not_create_duplicates(): void
    {
        $page=$this->actingAs($this->seller)->get(route('seller.vouchers.create'))->assertOk();
        $data=$this->payload(['code'=>'','creation_token'=>$page->viewData('creationToken')]);
        $this->post(route('seller.vouchers.store'),$data)->assertSessionHasNoErrors()->assertRedirect(route('seller.vouchers.index'));
        $this->assertMatchesRegularExpression('/^SHOP-[A-Z0-9]{10}$/',Voucher::sole()->code);
        $this->post(route('seller.vouchers.store'),$data)->assertRedirect(route('seller.vouchers.index'))->assertSessionHas('success','This voucher was already created.');
        $this->assertSame(1,Voucher::count());
        $next=$this->get(route('seller.vouchers.create'))->viewData('creationToken');
        $this->post(route('seller.vouchers.store'),$this->payload(['code'=>'','creation_token'=>$next]))->assertSessionHasNoErrors();
        $this->assertSame(2,Voucher::count());
        $route=app('router')->getRoutes()->getByName('seller.vouchers.store');
        $this->assertSame(10,$route->locksFor());
    }

    public function test_generate_endpoint_is_read_only_and_skips_global_archived_code_collisions(): void
    {
        $this->voucher(['code'=>'SHOP-AAAAAAAAAA','scope'=>'PLATFORM','seller_id'=>null,'archived_at'=>now()]);
        $sequence=['AAAAAAAAAA','BBBBBBBBBB'];
        Str::createRandomStringsUsing(function($length)use(&$sequence){ return $length===10?array_shift($sequence):substr(bin2hex(random_bytes($length)),0,$length); });
        $this->actingAs($this->seller)->getJson(route('seller.vouchers.generate'))->assertOk()->assertJson(['code'=>'SHOP-BBBBBBBBBB']);
        $this->assertSame(1,Voucher::count());
    }

    public function test_database_unique_races_retry_generated_codes_but_preserve_manual_codes(): void
    {
        DB::statement("CREATE TRIGGER code_race BEFORE INSERT ON vouchers WHEN NEW.code = 'SHOP-AAAAAAAAAA' BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: vouchers.code'); END");
        $sequence=['AAAAAAAAAA','BBBBBBBBBB'];
        Str::createRandomStringsUsing(function($length)use(&$sequence){ return $length===10?array_shift($sequence):substr(bin2hex(random_bytes($length)),0,$length); });
        $this->actingAs($this->seller)->post(route('seller.vouchers.store'),$this->payload(['code'=>'']))->assertSessionHasNoErrors();
        $this->assertSame('SHOP-BBBBBBBBBB',Voucher::sole()->code);
        $this->from(route('seller.vouchers.create'))->post(route('seller.vouchers.store'),$this->payload(['code'=>'SHOP-AAAAAAAAAA']))->assertSessionHasErrors('code');
        $this->assertSame(1,Voucher::count());
        $this->assertSame('SHOP-AAAAAAAAAA',session()->getOldInput('code'));
    }

    public function test_duplicate_codes_across_shops_platform_and_case_are_rejected(): void
    {
        $this->voucher(['code'=>'reserved','scope'=>'PLATFORM','seller_id'=>null]);
        $this->actingAs($this->seller)->post(route('seller.vouchers.store'),$this->payload(['code'=>' ReSeRvEd ']))->assertSessionHasErrors('code');
        $this->assertSame(1,Voucher::count());
    }

    public function test_discount_usage_and_datetime_validation_do_not_create_bad_records(): void
    {
        foreach([
            [['discount_value'=>'0'],'discount_value'], [['discount_value'=>'-1'],'discount_value'], [['discount_value'=>'0.001'],'discount_value'], [['discount_value'=>'1e2'],'discount_value'],
            [['discount_type'=>'PERCENTAGE','discount_value'=>'100.01'],'discount_value'], [['discount_value'=>'21474836.48'],'discount_value'],
            [['discount_type'=>'FAKE'],'discount_type'], [['usage_limit'=>0],'usage_limit'], [['usage_limit'=>1.5],'usage_limit'], [['usage_limit'=>2147483648],'usage_limit'],
            [['code'=>str_repeat('A',65)],'code'], [['starts_at'=>'2026-02-30T10:00'],'starts_at'],
            [['starts_at'=>'2026-11-01T10:00','expires_at'=>'2026-11-01T09:59'],'expires_at'],
            [['starts_at'=>'2026-11-01T10:00','expires_at'=>'2026-11-01T10:00'],'expires_at'],
        ] as [$overrides,$error]) $this->actingAs($this->seller)->from(route('seller.vouchers.create'))->post(route('seller.vouchers.store'),$this->payload($overrides))->assertSessionHasErrors($error);
        $this->assertSame(0,Voucher::count());
        $this->post(route('seller.vouchers.store'),$this->payload(['discount_type'=>'PERCENTAGE','discount_value'=>'100']))->assertSessionHasNoErrors();
        $this->assertSame(10000,Voucher::sole()->discount_bps);
    }

    public function test_unsupported_restrictions_and_forged_ownership_or_usage_are_rejected(): void
    {
        foreach(['name','description','min_purchase_amount','max_discount_cap','per_buyer_limit','scope','seller_id','used_count','archived_at','product_id'] as $field) {
            $this->actingAs($this->seller)->post(route('seller.vouchers.store'),$this->payload([$field=>'1']))->assertSessionHasErrors($field);
        }
        $this->post(route('seller.vouchers.store'),$this->payload(['creation_token'=>str_repeat('A',40)]))->assertSessionHasErrors('creation_token');
        $this->assertSame(0,Voucher::count());
    }

    public function test_scheduling_uses_manila_input_utc_storage_and_the_actual_redemption_service(): void
    {
        $this->actingAs($this->seller)->post(route('seller.vouchers.store'),$this->payload(['discount_type'=>'PERCENTAGE','discount_value'=>'12.35','starts_at'=>'2026-11-01T09:30:15','expires_at'=>'2026-11-01T10:45:30','usage_limit'=>2]))->assertSessionHasNoErrors();
        $voucher=Voucher::sole();
        $this->assertSame('2026-11-01 01:30:15',$voucher->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 02:45:30',$voucher->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('scheduled',SellerVouchers::status($voucher)); $this->unavailable('SHOPTEST');
        $this->get(route('seller.vouchers.edit',$voucher))->assertOk()->assertSee('2026-11-01T09:30:15')->assertSee('2026-11-01T10:45:30')->assertSee('value="PATCH"',false);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 02:00:00','UTC'));
        $resolved=app(VoucherService::class)->resolve([' shoptest '],collect([101=>2000,202=>9000]));
        $this->assertSame(247,$resolved[0]['amount']); $this->assertSame([101=>247],$resolved[0]['allocation']);
        $this->unavailable('SHOPTEST',[202=>9000]);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 03:00:00','UTC')); $this->unavailable('SHOPTEST');
    }

    public function test_fixed_amount_is_capped_by_actual_merchandise_and_usage_limits_remain_enforced(): void
    {
        $this->actingAs($this->seller)->post(route('seller.vouchers.store'),$this->payload(['usage_limit'=>1]))->assertSessionHasNoErrors();
        $resolved=app(VoucherService::class)->resolve(['SHOPTEST'],collect([101=>500,202=>20000]));
        $this->assertSame(500,$resolved[0]['amount']); $this->assertSame([101=>500],$resolved[0]['allocation']);
        // Fixture represents the canonical counter increment performed by checkout.
        DB::table('vouchers')->update(['used_count'=>1]);
        $this->unavailable('SHOPTEST'); $this->assertSame('exhausted',SellerVouchers::status(Voucher::sole()));
    }

    public function test_edit_and_archive_keep_identity_usage_and_redemption_snapshots(): void
    {
        $voucher=$this->voucher(); DB::table('vouchers')->where('id',$voucher->id)->update(['used_count'=>3]);
        DB::table('voucher_redemptions')->insert(['id'=>1,'voucher_id'=>$voucher->id,'code_snapshot'=>'EXISTING','discount_minor'=>100]);
        $this->actingAs($this->seller)->patch(route('seller.vouchers.update',$voucher),$this->payload(['code'=>'EXISTING','discount_type'=>'PERCENTAGE','discount_value'=>'20','usage_limit'=>10]))->assertSessionHasNoErrors()->assertRedirect(route('seller.vouchers.index'));
        $voucher->refresh(); $this->assertSame(3,$voucher->used_count); $this->assertSame(2000,$voucher->discount_bps); $this->assertSame(1,$voucher->id);
        $this->delete(route('seller.vouchers.archive',$voucher))->assertSessionHasNoErrors();
        $voucher->refresh(); $this->assertNotNull($voucher->archived_at); $this->assertFalse($voucher->is_active); $this->unavailable('EXISTING');
        $this->patch(route('seller.vouchers.update',$voucher),$this->payload(['code'=>'EXISTING','is_active'=>1]))->assertSessionHasNoErrors();
        $this->assertFalse($voucher->fresh()->is_active); $this->assertNotNull($voucher->fresh()->archived_at);
        $this->assertSame(1,Voucher::count()); $this->assertSame('EXISTING',DB::table('voucher_redemptions')->value('code_snapshot'));
        $this->assertSame(100,DB::table('voucher_redemptions')->value('discount_minor'));
    }

    public function test_listing_status_search_and_pagination_are_real_and_seller_scoped(): void
    {
        foreach(range(1,21) as $id) $this->voucher(['code'=>'OWN-'.$id]);
        $this->voucher(['code'=>'FOREIGN','seller_id'=>202]);
        $this->voucher(['code'=>'SCHEDULED','starts_at'=>now()->addDay()]);
        $this->voucher(['code'=>'INACTIVE','is_active'=>false]);
        $this->voucher(['code'=>'EXPIRED','expires_at'=>now()->subDay()]);
        $used=$this->voucher(['code'=>'EXHAUSTED','usage_limit'=>1]); DB::table('vouchers')->where('id',$used->id)->update(['used_count'=>1]);
        $this->voucher(['code'=>'ARCHIVED','archived_at'=>now()]);
        $response=$this->actingAs($this->seller)->get(route('seller.vouchers.index',['search'=>'own-','status'=>'active']))->assertOk()->assertDontSee('FOREIGN')->assertViewHas('vouchers',fn($p)=>$p->total()===21 && $p->count()===20);
        $next=$response->viewData('vouchers')->nextPageUrl();
        $this->assertStringContainsString('search=own-',$next); $this->assertStringContainsString('status=active',$next);
        $this->get($next)->assertOk()->assertViewHas('vouchers',fn($p)=>$p->count()===1);
        foreach(['scheduled','inactive','expired','exhausted','archived'] as $status) $this->get(route('seller.vouchers.index',['status'=>$status]))->assertOk()->assertViewHas('vouchers',fn($p)=>$p->total()===1);
        $this->get(route('seller.vouchers.index',['search'=>'no-match']))->assertOk()->assertSee('No vouchers match your filters.')->assertSee('Clear Filters');
        $this->get(route('seller.vouchers.index',['status'=>'fake']))->assertSessionHasErrors('status');
    }

    public function test_invalid_input_is_restored_with_errors_and_a_fresh_form_token(): void
    {
        $this->actingAs($this->seller)->from(route('seller.vouchers.create'))->post(route('seller.vouchers.store'),$this->payload(['code'=>' MY-CODE ','discount_value'=>'0']))->assertSessionHasErrors('discount_value');
        $this->get(route('seller.vouchers.create'))->assertOk()->assertSee('Please review the voucher.')->assertSee('value="MY-CODE"',false)->assertSee('value="0"',false);
        $this->assertSame(0,Voucher::count());
    }

    public function test_expiry_boundary_matches_redemption_and_management_filters(): void
    {
        $this->voucher(['expires_at'=>now()]);
        $this->assertSame('active',SellerVouchers::status(Voucher::sole()));
        $this->assertCount(1,app(VoucherService::class)->resolve(['EXISTING'],collect([101=>2000])));
        $this->actingAs($this->seller)->get(route('seller.vouchers.index',['status'=>'active']))->assertOk()->assertViewHas('vouchers',fn($p)=>$p->total()===1);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00.500000','UTC'));
        $this->unavailable('EXISTING');
        $this->get(route('seller.vouchers.index',['status'=>'expired']))->assertOk()->assertViewHas('vouchers',fn($p)=>$p->total()===1);
    }

    public function test_all_seller_routes_guard_ownership_and_admin_routes_are_unchanged(): void
    {
        foreach(['index','create','generate','store','edit','update','archive'] as $action) {
            $route=app('router')->getRoutes()->getByName('seller.vouchers.'.$action);
            foreach(['web','auth','not_suspended','approved','role:seller'] as $guard) $this->assertContains($guard,$route->gatherMiddleware());
        }
        $this->get(route('seller.vouchers.create'))->assertRedirect(route('login'));
        $this->actingAs($this->seller)->get(route('seller.vouchers.edit','not-an-id'))->assertNotFound();
        $foreign=$this->voucher(['code'=>'OTHER','seller_id'=>202]); $platform=$this->voucher(['code'=>'ADMIN','scope'=>'PLATFORM']);
        foreach([$foreign,$platform] as $voucher) {
            $this->actingAs($this->seller)->get(route('seller.vouchers.edit',$voucher))->assertNotFound();
            $this->patch(route('seller.vouchers.update',$voucher),$this->payload())->assertNotFound();
            $this->delete(route('seller.vouchers.archive',$voucher))->assertNotFound();
        }
        foreach(['store','update','archive'] as $action) $this->assertSame(\App\Http\Controllers\VoucherController::class.'@'.$action,app('router')->getRoutes()->getByName('admin.vouchers.'.$action)->getActionName());
        DB::table('sellers')->where('id',101)->update(['status'=>'pending']);
        $this->get(route('seller.vouchers.create'))->assertNotFound();
    }
}
