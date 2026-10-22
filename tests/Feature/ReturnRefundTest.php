<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Services\ReturnRefundAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_voucher_full_refund_uses_authoritative_item_snapshot(): void
    {
        $item = new OrderItem(['seller_order_id' => 1, 'quantity' => 2, 'unit_price_minor' => 1050, 'commission_minor' => 210, 'seller_funded_discount_minor' => 0, 'platform_funded_discount_minor' => 0]);
        $calculation = app(ReturnRefundAllocationService::class)->calculate($item, 2);
        $this->assertSame(2100, $calculation['gross_merchandise_minor']);
        $this->assertSame(1890, $calculation['seller_adjustment_minor']);
        $this->assertSame(2100, $calculation['buyer_refund_due_minor']);
    }

    public function test_seller_and_platform_funding_are_separate_for_partial_quantity(): void
    {
        $item = new OrderItem(['seller_order_id' => 1, 'quantity' => 4, 'unit_price_minor' => 1000, 'commission_minor' => 400, 'seller_funded_discount_minor' => 400, 'platform_funded_discount_minor' => 800]);
        $calculation = app(ReturnRefundAllocationService::class)->calculate($item, 2);
        $this->assertSame(2000, $calculation['gross_merchandise_minor']);
        $this->assertSame(200, $calculation['seller_funded_discount_minor']);
        $this->assertSame(400, $calculation['platform_funded_discount_minor']);
        $this->assertSame(1400, $calculation['buyer_refund_due_minor']);
        $this->assertSame(1600, $calculation['seller_adjustment_minor']);
    }

    public function test_partial_quantity_allocation_is_integer_based(): void { $this->assertSame(1000, app(ReturnRefundAllocationService::class)->calculate($this->item(3, 1000, 1, 0, 0), 1)['gross_merchandise_minor']); }
    public function test_full_quantity_reproduces_gross_snapshot(): void { $this->assertSame(3000, app(ReturnRefundAllocationService::class)->calculate($this->item(3, 1000, 0, 0, 0), 3)['gross_merchandise_minor']); }
    public function test_seller_discount_reduces_buyer_refund_due(): void { $this->assertSame(900, app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,0,100,0),1)['buyer_refund_due_minor']); }
    public function test_platform_discount_reduces_buyer_refund_due(): void { $this->assertSame(900, app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,0,0,100),1)['buyer_refund_due_minor']); }
    public function test_platform_discount_does_not_reduce_seller_adjustment(): void { $this->assertSame(1000, app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,0,0,100),1)['seller_adjustment_minor']); }
    public function test_commission_is_quantity_scoped(): void { $this->assertSame(100, app(ReturnRefundAllocationService::class)->calculate($this->item(2,1000,200,0,0),1)['commission_reversed_minor']); }
    public function test_seller_adjustment_excludes_platform_subsidy(): void { $this->assertSame(800, app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,200,0,500),1)['seller_adjustment_minor']); }
    public function test_shipping_is_not_part_of_item_refund_math(): void { $this->assertSame(1000, app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,0,0,0),1)['buyer_refund_due_minor']); }
    public function test_zero_quantity_is_rejected(): void { $this->expectException(\Illuminate\Validation\ValidationException::class); app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,0,0,0),0); }
    public function test_quantity_above_snapshot_is_rejected(): void { $this->expectException(\Illuminate\Validation\ValidationException::class); app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,0,0,0),2); }
    public function test_no_voucher_item_allows_zero_allocations(): void { $this->assertSame(0, app(ReturnRefundAllocationService::class)->calculate($this->item(1,1000,0,0,0),1)['seller_funded_discount_minor']); }
    public function test_partial_seller_discount_is_proportional(): void { $this->assertSame(50, app(ReturnRefundAllocationService::class)->calculate($this->item(2,1000,0,100,0),1)['seller_funded_discount_minor']); }
    public function test_partial_platform_discount_is_proportional(): void { $this->assertSame(50, app(ReturnRefundAllocationService::class)->calculate($this->item(2,1000,0,0,100),1)['platform_funded_discount_minor']); }

    private function item(int $quantity, int $unit, int $commission, int $seller, int $platform): OrderItem { return new OrderItem(['seller_order_id'=>1,'quantity'=>$quantity,'unit_price_minor'=>$unit,'commission_minor'=>$commission,'seller_funded_discount_minor'=>$seller,'platform_funded_discount_minor'=>$platform]); }
}
