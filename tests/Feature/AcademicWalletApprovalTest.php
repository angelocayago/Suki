<?php

namespace Tests\Feature;

use App\Models\{BuyerRefundFunding, ReturnRefundRequest};
use App\Services\{BuyerRefundFundingService, BuyerWalletService, CheckoutService, ReturnRefundService, SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class AcademicWalletApprovalTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Disposable SQLite required.');
        }
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        Http::preventStrayRequests(); Mail::fake(); Notification::fake();
        Storage::fake('local'); Storage::fake('public');
        config(['academic_wallet.seller_approval_credit_enabled' => true,
            'simulation_settlement.enabled' => true, 'simulation_settlement.max_scenario_minor' => 100000,
            'suki_wallet.enabled' => true, 'suki_wallet.refund_credit_enabled' => true,
            'suki_wallet.checkout_enabled' => false, 'returns.backend_enabled' => false,
            'returns.admin_review_enabled' => true, 'returns.earning_release_enabled' => false]);
    }

    protected function fixture(): array
    {
        return (new SimulationSettlementTest('test_exact_discounted_source_is_conserved_and_reverse_shipping_is_explicitly_no_charge'))->fixture(true);
    }

    protected function request(array $f): ReturnRefundRequest
    {
        return app(ReturnRefundService::class)->submitForSeller($f['buyer'], $f['item'], [
            'type' => 'REFUND', 'quantity' => 2, 'reason' => 'Synthetic damaged goods',
            'description' => 'Academic fixture only', 'confirmed' => 1,
        ]);
    }

    public function test_seller_approval_credits_exact_backing_once_and_shows_owned_wallet(): void
    {
        $f = $this->fixture(); $r = $this->request($f);
        $s = app(ReturnRefundService::class);
        $this->actingAs($f['sellerUser'])->patchJson(route('seller.return-refunds.decide', $r), [
            'decision' => 'APPROVED', 'reason' => 'Reviewed synthetic purchase',
        ])->assertOk()->assertJsonPath('status', 'ADMIN_APPROVED');
        $credit = DB::table('buyer_wallet_entries')->sole();
        $this->assertSame(19698, (int) $credit->amount_minor);
        $this->assertSame('REFUND_CREDIT', $credit->type);
        $this->assertSame(19698, app(BuyerWalletService::class)->summary($f['buyer'])['balance_minor']);
        $this->assertDatabaseCount('simulation_wallet_backings', 1);
        $this->assertDatabaseHas('buyer_refund_fundings', ['return_refund_request_id' => $r->id, 'status' => BuyerRefundFunding::CREDITED]);
        $before = $this->financialSnapshot();
        $s->sellerDecide($r, $f['sellerUser'], 'APPROVED', 'Repeated approval');
        $again = app(BuyerRefundFundingService::class)->credit($r, $f['admin']);
        $this->assertSame((int) $credit->id, $again->id);
        $this->assertSame($before, $this->financialSnapshot());
        $this->assertSame(1, DB::table('seller_wallet_entries')->where('type', 'REFUND_ADJUSTMENT')->count());
        $this->actingAs($f['buyer'])->get(route('buyer.wallet'))->assertOk()
            ->assertSee('196.98')->assertSee('Refund Credit')->assertSee('Credited to Suki Wallet');
    }

    public function test_partial_funding_preserves_approval_and_deficit_without_credit(): void
    {
        $f = $this->fixture();
        app(SellerWalletService::class)->request($f['seller'], 10000);
        $r = $this->request($f);
        app(ReturnRefundService::class)->sellerDecide($r, $f['sellerUser'], 'APPROVED', 'Partial source remains pending');
        $this->assertSame('ADMIN_APPROVED', $r->fresh()->status);
        $funding = BuyerRefundFunding::findOrFail($r->id);
        $this->assertSame(BuyerRefundFunding::AWAITING, $funding->status);
        $this->assertSame('Approved — Awaiting Funding', $funding->statusLabel());
        $this->assertDatabaseCount('buyer_wallet_entries', 0);
        $this->assertSame(-10000, app(SellerWalletService::class)->summary($f['seller'])['available_minor']);
        $this->actingAs($f['buyer'])->get(route('buyer.wallet'))->assertOk()->assertSee('Approved — Awaiting Funding');
    }

    public function test_old_approval_is_not_backfilled_when_demo_switch_is_enabled_later(): void
    {
        $f = $this->fixture(); $r = $this->request($f);
        config(['academic_wallet.seller_approval_credit_enabled' => false]);
        app(ReturnRefundService::class)->sellerDecide($r, $f['sellerUser'], 'APPROVED', 'Existing approval');
        $before = $this->financialSnapshot();
        config(['academic_wallet.seller_approval_credit_enabled' => true]);
        app(ReturnRefundService::class)->sellerDecide($r, $f['sellerUser'], 'APPROVED', 'Replay is not a backfill');
        $this->assertSame($before, $this->financialSnapshot());
        $this->assertDatabaseCount('buyer_wallet_entries', 0);
    }

    public function test_disabled_credit_gate_never_credits(): void
    {
        $f = $this->fixture(); $r = $this->request($f);
        config(['suki_wallet.refund_credit_enabled' => false]);
        app(ReturnRefundService::class)->sellerDecide($r, $f['sellerUser'], 'APPROVED', 'Credit rollout remains disabled');
        $this->assertDatabaseCount('buyer_wallet_entries', 0);
        $this->assertSame(BuyerRefundFunding::READY, BuyerRefundFunding::findOrFail($r->id)->status);
    }

    public function test_credit_failure_rolls_back_the_entire_seller_decision(): void
    {
        $f = $this->fixture(); $r = $this->request($f); $before = $this->financialSnapshot();
        DB::unprepared("CREATE TRIGGER academic_fail_credit BEFORE INSERT ON buyer_wallet_entries BEGIN SELECT RAISE(ABORT,'Injected academic credit failure'); END");
        try {
            app(ReturnRefundService::class)->sellerDecide($r, $f['sellerUser'], 'APPROVED', 'Injected failure');
            $this->fail('Credit failure was ignored');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('Injected academic credit failure', $e->getMessage());
        }
        $this->assertSame($before, $this->financialSnapshot());
        $this->assertSame('SUBMITTED', $r->fresh()->status);
    }

    public function test_unrelated_seller_and_rejected_decisions_cannot_post_credit(): void
    {
        $f = $this->fixture(); $r = $this->request($f); $before = $this->financialSnapshot();
        $other = \App\Models\User::factory()->create(['role' => 'seller', 'status' => 'active']);
        try {
            app(ReturnRefundService::class)->sellerDecide($r, $other, 'APPROVED', 'Not the owning Seller');
            $this->fail('Another Seller approved the purchase');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertContains($e->getStatusCode(), [403, 404]);
        }
        $this->assertSame($before, $this->financialSnapshot());
        app(ReturnRefundService::class)->sellerDecide($r, $f['sellerUser'], 'REJECTED', 'Rejected synthetic request');
        $this->assertDatabaseCount('buyer_wallet_entries', 0);
        $this->assertDatabaseCount('buyer_refund_fundings', 0);
    }

    public function test_existing_backing_guard_rejects_spending_instead_of_creating_fake_payments(): void
    {
        $f = $this->fixture(); $r = $this->request($f);
        app(ReturnRefundService::class)->sellerDecide($r, $f['sellerUser'], 'APPROVED', 'Funded approval');
        $before = $this->financialSnapshot();
        try {
            DB::transaction(fn () => DB::table('buyer_wallet_entries')->insert([
                'buyer_id' => $f['buyer']->id, 'type' => 'ORDER_PAYMENT', 'status' => 'POSTED',
                'amount_minor' => -1, 'order_id' => $f['order']->id,
                'source_identity' => 'ACADEMIC-UNSUPPORTED-SPEND', 'created_at' => now(),
            ]));
            $this->fail('The credit-only backing accepted an unallocated spend');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('simulation', strtolower($e->getMessage()));
        }
        $this->assertSame($before, $this->financialSnapshot());
    }

    public function test_checkout_requires_a_stable_identity_even_with_rollout_flag(): void
    {
        $f = $this->fixture(); $before = $this->financialSnapshot();
        config(['suki_wallet.checkout_enabled' => true]);
        try {
            app(CheckoutService::class)->checkout($f['buyer'], $f['address'], 'wallet');
            $this->fail('An unsupported wallet order was created');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method', $e->errors());
        }
        $this->assertSame($before, $this->financialSnapshot());
        // The additive payment contract now makes checkout available, but never
        // permits the identity-free call above to create an unpaid wallet order.
        $this->assertTrue(app(BuyerWalletService::class)->checkoutAvailable());
    }

    protected function financialSnapshot(): array
    {
        $out = [];
        foreach (['orders', 'seller_orders', 'shipments', 'order_items', 'product_variants',
            'return_refund_requests', 'return_refund_events', 'return_refund_accounting',
            'seller_wallet_entries', 'payouts', 'buyer_wallets', 'buyer_wallet_entries', 'buyer_refund_fundings',
            'simulation_settlement_mutex', 'simulation_settlements', 'simulation_commitments',
            'simulation_wallet_backings', 'simulation_settlement_events'] as $table) {
            $out[$table] = DB::table($table)->orderByRaw('1')->get()->toJson();
        }
        return $out;
    }
}
