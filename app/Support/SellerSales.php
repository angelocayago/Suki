<?php

namespace App\Support;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerSales
{
    public static function data(Seller $seller, Request $request): array
    {
        $context = SellerOrderItems::filtered($seller, $request, true);
        $query = $context['query'];
        $status = SellerOrderItems::STATUS_SQL;
        $grouped = (clone $query)->select('orders.id as order_id', 'orders.order_number', 'orders.created_at as transaction_at', 'orders.payment_method')
            ->selectRaw($status.' as order_status')
            ->selectRaw('SUM('.SellerOrderItems::GROSS_SQL.') as gross_amount, SUM(order_items.quantity) as units, COUNT(*) as item_count')
            ->groupBy('orders.id', 'orders.order_number', 'orders.created_at', 'orders.payment_method')->groupByRaw($status);
        // SQL aggregates and pagination, not a materialized list of all seller orders.
        $transactions = fn () => DB::query()->fromSub((clone $grouped)->toBase(), 'seller_transactions');
        $completed = ['delivered', 'completed'];
        $excluded = ['cancelled', 'returned', 'delivery_failed'];
        $completedQuery = $transactions()->whereIn('order_status', $completed);
        $completedCount = (clone $completedQuery)->count();
        $grossCompleted = (float) (clone $completedQuery)->sum('gross_amount');
        $pendingValue = (float) $transactions()->whereIn('order_status', array_values(array_diff(SellerReports::STATUSES, $completed, $excluded)))->sum('gross_amount');
        $cancelledCount = $transactions()->whereIn('order_status', $excluded)->count();
        [$column, $direction] = match ($context['criteria']['sort'] ?? 'newest') {
            'oldest' => ['transaction_at', 'asc'], 'amount_desc' => ['gross_amount', 'desc'], 'amount_asc' => ['gross_amount', 'asc'], default => ['transaction_at', 'desc'],
        };
        $paginator = $transactions()->orderBy($column, $direction)->orderBy('order_id')->orderBy('order_status')->paginate(20)->withQueryString();
        unset($context['query'], $context['criteria']);

        return $context + ['paginator' => $paginator, 'completedCount' => $completedCount, 'grossCompleted' => $grossCompleted,
            'pendingValue' => $pendingValue, 'averageCompleted' => $completedCount ? $grossCompleted / $completedCount : null, 'cancelledCount' => $cancelledCount];
    }
}
