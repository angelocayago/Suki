<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Seller;

class SellerSalesService
{
    /**
     * Completed merchandise sales only. Shipping and non-final transactions
     * are deliberately excluded from seller earnings.
     */
    public function completedSummary(Seller $seller): array
    {
        $totals = OrderItem::query()
            ->where('seller_order_id', '!=', null)
            ->whereHas('sellerOrder', function ($query) use ($seller) {
                $query->where('seller_id', $seller->id)
                    ->where('workflow_status', OrderStatus::Completed->value)
                    ->where('status', '!=', 'cancelled');
            })
            ->selectRaw('COALESCE(SUM(unit_price_minor * quantity), 0) as raw_sales_minor')
            ->selectRaw('COALESCE(SUM(commission_minor), 0) as commission_minor')
            ->first();

        $rawSalesMinor = (int) ($totals->raw_sales_minor ?? 0);
        $commissionMinor = (int) ($totals->commission_minor ?? 0);

        return [
            'raw_sales_minor' => $rawSalesMinor,
            'commission_minor' => $commissionMinor,
            'net_sales_minor' => $rawSalesMinor - $commissionMinor,
        ];
    }
}
