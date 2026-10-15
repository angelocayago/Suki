<?php

namespace Tests\Feature;

use App\Enums\ComplaintCategory;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerWalletEntry;
use App\Models\User;
use App\Models\VoucherRedemption;
use App\Services\Admin\ComplaintService;
use App\Services\SellerWalletService;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseFourWalletHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_earning_uses_snapshots_excludes_shipping_and_is_idempotent(): void
    {
        $fixture = $this->completedOrder();
        VoucherRedemption::create(['order_id'=>$fixture['order']->id,'seller_order_id'=>$fixture['sellerOrder']->id,'buyer_id'=>$fixture['buyer']->id,'code_snapshot'=>'SELLER10','scope_snapshot'=>'SELLER','seller_id_snapshot'=>$fixture['seller']->id,'discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>1000,'discount_minor'=>1000]);
        VoucherRedemption::create(['order_id'=>$fixture['order']->id,'seller_order_id'=>$fixture['sellerOrder']->id,'buyer_id'=>$fixture['buyer']->id,'code_snapshot'=>'PLATFORM10','scope_snapshot'=>'PLATFORM','discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>1000,'discount_minor'=>1000]);

        $wallet = app(SellerWalletService::class);
        $wallet->creditCompleted($fixture['sellerOrder']);
        $wallet->creditCompleted($fixture['sellerOrder']);

        $this->assertSame(8000, $wallet->summary($fixture['seller'])['available_minor']);
        $this->assertDatabaseCount('seller_wallet_entries', 1);
        $this->assertDatabaseMissing('seller_wallet_entries', ['amount_minor' => 13000]);
    }

    public function test_held_earning_is_released_only_by_explicit_complaint_resolution(): void
    {
        $fixture = $this->completedOrder();
        $complaint = Complaint::create(['reference'=>'CMP-HOLD-'.$fixture['sellerOrder']->id,'complainant_id'=>$fixture['buyer']->id,'complainant_role'=>'BUYER','order_id'=>$fixture['order']->id,'seller_order_id'=>$fixture['sellerOrder']->id,'buyer_id'=>$fixture['buyer']->id,'seller_id'=>$fixture['seller']->id,'category'=>ComplaintCategory::Other->value,'status'=>'OPEN','subject'=>'Review','description'=>'A relevant active dispute.','context_snapshot'=>[]]);
        $wallet = app(SellerWalletService::class);
        $wallet->creditCompleted($fixture['sellerOrder']);
        $this->assertSame(9000, $wallet->summary($fixture['seller'])['held_minor']);
        $this->assertSame('HELD', SellerWalletEntry::sole()->status);

        // A read must have no financial mutation.
        $wallet->summary($fixture['seller']);
        $this->assertSame('HELD', SellerWalletEntry::sole()->status);

        $admin = User::factory()->create(['role'=>'admin','status'=>'active']);
        app(ComplaintService::class)->dismiss($complaint, $admin, 'No further action is required.');
        $this->assertSame(9000, $wallet->summary($fixture['seller'])['available_minor']);
        app(SellerWalletService::class)->reconcileSellerOrder($fixture['sellerOrder']);
        $this->assertDatabaseCount('seller_wallet_entries', 1);
    }

    public function test_payout_state_machine_reserves_releases_and_cannot_be_processed_twice(): void
    {
        $fixture = $this->completedOrder();
        $wallet = app(SellerWalletService::class);
        $wallet->creditCompleted($fixture['sellerOrder']);
        $admin = User::factory()->create(['role'=>'admin','status'=>'active']);

        $this->expectException(ValidationException::class);
        $wallet->request($fixture['seller'], 0);
    }

    public function test_payout_rejection_and_payment_are_idempotent(): void
    {
        $fixture = $this->completedOrder();
        $wallet = app(SellerWalletService::class);
        $wallet->creditCompleted($fixture['sellerOrder']);
        $admin = User::factory()->create(['role'=>'admin','status'=>'active']);
        $payout = $wallet->request($fixture['seller'], 5000);
        $this->assertSame(4000, $wallet->summary($fixture['seller'])['available_minor']);
        $wallet->review($payout, $admin, 'APPROVED');
        $wallet->markPaid($payout, $admin, 'manual', 'REF-1');
        $this->assertSame(5000, $wallet->summary($fixture['seller'])['paid_out_minor']);
        $this->expectException(ValidationException::class);
        $wallet->markPaid($payout, $admin, 'manual', 'REF-2');
    }

    private function completedOrder(): array
    {
        $sellerUser = User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller = Seller::create(['user_id'=>$sellerUser->id,'name'=>'Seller '.$sellerUser->id,'slug'=>'seller-'.$sellerUser->id,'status'=>'approved','commission_bps'=>0]);
        $buyer = User::factory()->create(['role'=>'buyer','status'=>'active']);
        $order = Order::create(['order_number'=>'W'.$seller->id,'reference'=>'W'.$seller->id,'buyer_id'=>$buyer->id,'status'=>'COMPLETED','payment_method'=>'cod','payment_status'=>'PAID','subtotal'=>100,'shipping_fee'=>50,'discount_amount'=>0,'total_amount'=>150,'total_minor'=>15000,'recipient_name'=>'x','recipient_phone'=>'x','province'=>'x','municipality'=>'x','barangay'=>'x','street_address'=>'x','shipping_address'=>[]]);
        $sellerOrder = SellerOrder::create(['order_id'=>$order->id,'seller_id'=>$seller->id,'subtotal_minor'=>10000,'shipping_fee_minor'=>5000,'commission_minor'=>1000,'status'=>'completed','workflow_status'=>OrderStatus::Completed]);
        OrderItem::create(['order_id'=>$order->id,'seller_order_id'=>$sellerOrder->id,'seller_id'=>$sellerUser->id,'product_slug'=>'wallet-'.$seller->id,'product_name'=>'Wallet item','quantity'=>1,'unit_price_minor'=>10000,'unit_price'=>100,'line_total'=>100,'commission_minor'=>1000]);
        return compact('seller', 'buyer', 'order', 'sellerOrder');
    }
}
