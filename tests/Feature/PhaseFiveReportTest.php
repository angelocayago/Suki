<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VoucherRedemption;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_snapshot_sales_reconcile_and_cancelled_orders_are_excluded(): void
    {
        $fixture = $this->completedSellerOrder();
        VoucherRedemption::create(['order_id'=>$fixture['order']->id,'seller_order_id'=>$fixture['sellerOrder']->id,'buyer_id'=>$fixture['buyer']->id,'code_snapshot'=>'STORE','scope_snapshot'=>'SELLER','seller_id_snapshot'=>$fixture['seller']->id,'discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>1000,'discount_minor'=>1000]);
        VoucherRedemption::create(['order_id'=>$fixture['order']->id,'seller_order_id'=>$fixture['sellerOrder']->id,'buyer_id'=>$fixture['buyer']->id,'code_snapshot'=>'PLATFORM','scope_snapshot'=>'PLATFORM','discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>500,'discount_minor'=>500]);
        $cancelled = $this->completedSellerOrder($fixture['seller']); $cancelled['sellerOrder']->update(['status'=>'cancelled']);
        $report = app(ReportService::class)->seller($fixture['seller'], null, null);
        $this->assertSame(10000, $report['summary']['raw_minor']);
        $this->assertSame(1000, $report['summary']['commission_minor']);
        $this->assertSame(1000, $report['summary']['seller_discount_minor']);
        $this->assertSame(500, $report['summary']['platform_discount_minor']);
        $this->assertSame(8000, $report['summary']['net_minor']);
        $this->assertSame(1, $report['completed_orders']);
        $this->assertSame(1, $report['cancelled_orders']);

        $admin = app(ReportService::class)->admin(null, null);
        $this->assertSame('Report item', $report['products'][0]['name']);
        $this->assertSame('Uncategorized', $admin['category_performance'][0]['category']);
    }

    public function test_logistics_and_rider_reports_are_scoped_to_their_shipments(): void
    {
        $fixture = $this->completedSellerOrder();
        $providerUser = User::factory()->create(['role'=>'logistics','status'=>'active']);
        $provider = \App\Models\LogisticsProvider::create(['user_id'=>$providerUser->id,'name'=>'P','slug'=>'p','status'=>'approved']);
        $riderUser = User::factory()->create(['role'=>'rider','status'=>'active']);
        $rider = \App\Models\Rider::create(['user_id'=>$riderUser->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'motorcycle','plate_no'=>'RPT-123','license_number'=>'LIC-'.$riderUser->id,'is_active'=>true]);
        Shipment::create(['seller_order_id'=>$fixture['sellerOrder']->id,'logistics_provider_id'=>$provider->id,'rider_id'=>$rider->id,'tracking_code'=>'T-'.$fixture['sellerOrder']->id,'status'=>'completed','workflow_status'=>OrderStatus::Completed,'fee_minor'=>0,'attempts'=>0,'cod_amount_minor'=>15000,'cod_collected_minor'=>15000,'cod_collected'=>true]);
        $report = app(ReportService::class);
        $this->assertSame(1, $report->logistics($provider, null, null)['total']);
        $this->assertSame(15000, $report->rider($rider, null, null)['cod_collected_minor']);
    }

    public function test_date_filters_use_completed_and_operational_timestamps(): void
    {
        $fixture = $this->completedSellerOrder();
        $future = now()->addDay()->startOfDay();
        $reports = app(ReportService::class);
        $this->assertSame(0, $reports->seller($fixture['seller'], $future, null)['completed_orders']);
        $this->assertSame(0, $reports->admin($future, null)['completed_orders']);
    }

    private function completedSellerOrder(?Seller $existingSeller = null): array
    {
        $sellerUser = $existingSeller ? User::findOrFail($existingSeller->user_id) : User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller = $existingSeller ?: Seller::create(['user_id'=>$sellerUser->id,'name'=>'S'.$sellerUser->id,'slug'=>'s'.$sellerUser->id,'status'=>'approved']);
        $buyer = User::factory()->create(['role'=>'buyer','status'=>'active']);
        $reference = 'R'.$seller->id.'-'.uniqid();
        $order = Order::create(['order_number'=>$reference,'reference'=>$reference,'buyer_id'=>$buyer->id,'status'=>'COMPLETED','payment_method'=>'cod','payment_status'=>'PAID','subtotal'=>100,'shipping_fee'=>50,'discount_amount'=>0,'total_amount'=>150,'total_minor'=>15000,'recipient_name'=>'x','recipient_phone'=>'x','province'=>'x','municipality'=>'x','barangay'=>'x','street_address'=>'x','shipping_address'=>[]]);
        $sellerOrder = SellerOrder::create(['order_id'=>$order->id,'seller_id'=>$seller->id,'subtotal_minor'=>10000,'shipping_fee_minor'=>5000,'commission_minor'=>1000,'status'=>'completed','workflow_status'=>OrderStatus::Completed,'completed_at'=>now()]);
        OrderItem::create(['order_id'=>$order->id,'seller_order_id'=>$sellerOrder->id,'seller_id'=>$sellerUser->id,'product_slug'=>'r'.$seller->id,'product_name'=>'Report item','category_name_snapshot'=>'Uncategorized','quantity'=>1,'unit_price_minor'=>10000,'unit_price'=>100,'line_total'=>100,'commission_minor'=>1000]);
        return compact('seller','buyer','order','sellerOrder');
    }
}
