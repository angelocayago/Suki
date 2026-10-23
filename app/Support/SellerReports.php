<?php

namespace App\Support;

use App\Models\Seller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SellerReports
{
    public const STATUSES = ['placed', 'pending', 'confirmed', 'accepted', 'preparing', 'packed', 'ready_for_pickup', 'ready_to_ship', 'picked_up', 'shipped', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'delivery_failed', 'returned'];

    public static function data(Seller $seller, Request $request): array
    {
        $context = SellerOrderItems::filtered($seller, $request);
        $query = $context['query'];
        $products = $context['products'];
        $categories = $context['filterCategories'];
        $paymentMethods = $context['paymentMethods'];
        $status = SellerOrderItems::STATUS_SQL;
        $data = $context['criteria'];
        $rows = $query->select('order_items.*', 'orders.order_number', 'orders.created_at as transaction_at', 'orders.payment_status')
            ->selectRaw($status.' as report_status')
            ->selectRaw(SellerOrderItems::GROSS_SQL.' as gross_amount')
            ->orderBy('order_items.id')->get();
        $completed = $rows->whereIn('report_status', ['delivered', 'completed']);
        $breakdown = $completed->groupBy(fn ($r) => $r->product_id ? 'id:'.$r->product_id : 'slug:'.$r->product_slug)->map(fn ($items) => [
            'name' => $items->first()->product_name, 'units' => $items->sum('quantity'), 'gross' => $items->sum('gross_amount'), 'recent' => $items->max('transaction_at'),
        ]);
        $breakdown = match ($data['sort'] ?? 'sales_desc') {
            'sales_asc' => $breakdown->sortBy('gross'), 'units_desc' => $breakdown->sortByDesc('units'), 'recent' => $breakdown->sortByDesc('recent'), default => $breakdown->sortByDesc('gross'),
        };
        $chart = $rows->groupBy(fn ($r) => CarbonImmutable::parse($r->transaction_at, config('app.timezone'))->setTimezone(config('seller_inventory.business_timezone'))->format('Y-m-d'))
            ->sortKeys()->map(fn ($items) => ['orders' => $items->pluck('order_id')->unique()->count(), 'gross' => $items->whereIn('report_status', ['delivered', 'completed'])->sum('gross_amount')]);
        $paginator = new LengthAwarePaginator($rows->sortByDesc('transaction_at')->values()->forPage($page = max(1, $request->integer('page', 1)), 20), $rows->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return ['products' => $products, 'filterCategories' => $categories, 'mainCategory' => SellerCategories::main($seller),
            'paymentMethods' => $paymentMethods,
            'totalOrders' => $rows->pluck('order_id')->unique()->count(), 'completedOrders' => $completed->pluck('order_id')->unique()->count(),
            'unitsSold' => $completed->sum('quantity'), 'grossSales' => $completed->sum('gross_amount'), 'breakdown' => $breakdown, 'chart' => $chart, 'paginator' => $paginator];
    }
}
