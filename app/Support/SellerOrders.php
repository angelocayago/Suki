<?php

namespace App\Support;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SellerOrders
{
    public const SORTS = ['newest' => 'Newest First', 'oldest' => 'Oldest First', 'reference' => 'Order Reference A–Z', 'amount_desc' => 'Highest Seller Item Gross', 'amount_asc' => 'Lowest Seller Item Gross'];

    public static function data(Seller $seller, Request $request): array
    {
        $owned = SellerOrderItems::owned($seller);
        $status = SellerOrderItems::STATUS_SQL;
        $savedStatuses = (clone $owned)->selectRaw($status.' as effective_status')->distinct()->pluck('effective_status')->filter(fn ($v) => $v !== null && $v !== '');
        $statuses = collect(SellerReports::STATUSES)->merge($savedStatuses)->unique()->mapWithKeys(fn ($v) => [$v => ucwords(str_replace('_', ' ', $v))])->all();
        $methods = (clone $owned)->whereNotNull('orders.payment_method')->distinct()->orderBy('orders.payment_method')->pluck('orders.payment_method')->filter(fn ($v) => $v !== '')->mapWithKeys(fn ($v) => [$v => $v])->all();
        $criteria = $request->validate([
            'search' => 'nullable|string|max:200',
            'order_status' => ['nullable', 'string', Rule::in(array_keys($statuses))],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys($methods))],
            'sort' => ['nullable', 'string', Rule::in(array_keys(self::SORTS))],
            'page' => 'nullable|integer|min:1',
        ]);
        SellerDateFilter::apply($owned, $request, 'orders.created_at');
        $criteria += $request->only(['date_range', 'from', 'to']);
        $grouped = (clone $owned)->select('orders.id as order_id', 'order_items.seller_order_id', 'orders.order_number', 'orders.reference', 'orders.created_at as transaction_at', 'orders.recipient_name', 'orders.status as parent_status', 'orders.payment_method')
            ->selectRaw($status.' as effective_status')
            ->selectRaw('SUM('.SellerOrderItems::GROSS_SQL.') as gross_amount, SUM(order_items.quantity) as units, COUNT(*) as item_count');
        // Search the authorized lines without shrinking the segment's items or amount.
        $search = $criteria['search'] ?? '';
        if ($search !== '') {
            $grouped->selectRaw('MAX(CASE WHEN order_items.product_name LIKE ? THEN 1 ELSE 0 END) as item_match', ['%'.$search.'%']);
        }
        $grouped->groupBy('orders.id', 'order_items.seller_order_id', 'orders.order_number', 'orders.reference', 'orders.created_at', 'orders.recipient_name', 'orders.status', 'orders.payment_method')->groupByRaw($status);
        $query = DB::query()->fromSub($grouped->toBase(), 'seller_segments');
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('order_number', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')->orWhere('item_match', 1));
        }
        if (($criteria['order_status'] ?? '') !== '') {
            $query->where('effective_status', $criteria['order_status']);
        }
        if (($criteria['payment_method'] ?? '') !== '') {
            $query->where('payment_method', $criteria['payment_method']);
        }
        $summary = (clone $query)->selectRaw("COUNT(*) as total, COALESCE(SUM(gross_amount), 0) as gross,
            COALESCE(SUM(CASE WHEN effective_status IN ('pending','placed') THEN 1 ELSE 0 END),0) as pending,
            COALESCE(SUM(CASE WHEN effective_status IN ('confirmed','accepted','preparing','packed') THEN 1 ELSE 0 END),0) as processing,
            COALESCE(SUM(CASE WHEN effective_status IN ('ready_for_pickup','ready_to_ship') THEN 1 ELSE 0 END),0) as ready,
            COALESCE(SUM(CASE WHEN effective_status IN ('delivered','completed') THEN 1 ELSE 0 END),0) as completed,
            COALESCE(SUM(CASE WHEN effective_status IN ('cancelled','delivery_failed','returned') THEN 1 ELSE 0 END),0) as cancelled")->first();
        [$column, $direction] = match ($criteria['sort'] ?? 'newest') {
            'oldest' => ['transaction_at', 'asc'], 'reference' => ['display_reference', 'asc'],
            'amount_desc' => ['gross_amount', 'desc'], 'amount_asc' => ['gross_amount', 'asc'], default => ['transaction_at', 'desc'],
        };
        $paginator = $query->select('seller_segments.*')->selectRaw("COALESCE(NULLIF(reference, ''), order_number) as display_reference")
            ->orderBy($column, $direction)->orderBy('order_id')->orderBy('seller_order_id')->paginate(15)->appends(array_diff_key($criteria, ['page' => true]));
        // A single page-bounded query; never load a parent's unrestricted item relation.
        $items = (clone SellerOrderItems::owned($seller))->whereIn('orders.id', $paginator->pluck('order_id'))
            ->select('order_items.id', 'order_items.order_id', 'order_items.seller_order_id', 'order_items.product_name', 'order_items.variant_name', 'order_items.variation', 'order_items.quantity', 'order_items.unit_price', 'order_items.unit_price_minor')
            ->selectRaw(SellerOrderItems::GROSS_SQL.' as gross_amount')->orderBy('order_items.id')->get()
            ->groupBy(fn ($item) => $item->order_id.':'.($item->seller_order_id ?? 'legacy'));
        foreach ($paginator as $segment) {
            $segment->items = $items->get($segment->order_id.':'.($segment->seller_order_id ?? 'legacy'), collect());
        }
        $filterChips = SellerFilterChips::make($criteria, [
            'order_status' => $statuses, 'payment_method' => $methods, 'sort' => self::SORTS,
        ], route('seller.orders'), ['search', 'order_status', 'payment_method', 'sort', 'date_range', 'from', 'to'], 'newest');

        return compact('paginator', 'summary', 'statuses', 'methods', 'criteria', 'filterChips');
    }
}
