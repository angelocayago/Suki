@extends('layouts.seller')
@section('title', 'Reports & Analytics')
@section('page-title', 'Reports & Analytics')
@section('content')
@php
    $filterChips = \App\Support\SellerFilterChips::transactions(request()->query(), $products, $mainCategory, $filterCategories, $paymentMethods, route('seller.reports'));
    $filtersOpen = count($filterChips) > 0 || $errors->any();
    $filterId = 'reports-advanced-filters';
    $report = true;
@endphp
<div data-seller-filters>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3"><h1 class="text-xl font-bold text-[#173F35]">Reports & Analytics</h1>@include('seller.partials.filter-trigger')</div>
<div class="overflow-hidden rounded-2xl border border-[#DDE6E1] bg-white">
    @include('seller.partials.advanced-filter-panel')
    @include('seller.partials.active-filter-chips')
    <p class="p-4 text-xs text-[#68776F]">Based on saved seller order items, filtered by order creation date. Delivered/completed gross sales do not imply payment. Orders without recorded items are excluded. Discounts, refunds, net revenue and payouts are unavailable at this item scope.</p>
    <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach(['Matching orders with items' => $totalOrders, 'Completed/delivered orders' => $completedOrders, 'Units sold' => $unitsSold, 'Gross completed sales' => '₱'.number_format($grossSales, 2)] as $label => $value)
            <div class="rounded-xl bg-[#EEF5F1] p-4"><p class="text-xs text-[#68776F]">{{ $label }}</p><p class="mt-2 text-xl font-bold text-[#173F35]">{{ $value }}</p></div>
        @endforeach
    </div>
    <h2 class="px-4 pt-4 font-semibold text-[#173F35]">Daily activity (Asia/Manila)</h2>
    <div id="salesChart" class="space-y-2 p-4" role="figure" aria-label="Gross completed sales by order creation date">
        @forelse($chart as $date => $point)
            <div class="grid grid-cols-[100px_1fr_110px] items-center gap-3 text-xs"><span>{{ $date }}</span><div class="h-3 rounded bg-[#EEF5F1]"><div class="h-3 rounded bg-[#1F6F5B]" style="width: {{ $grossSales > 0 ? $point['gross'] / $grossSales * 100 : 0 }}%"></div></div><span>₱{{ number_format($point['gross'], 2) }}</span></div>
        @empty<p class="text-sm text-[#68776F]">No completed sales yet. The chart is showing zero activity.</p>@endforelse
    </div>
    <div id="ordersChart" class="px-4 pb-4 text-xs" role="figure" aria-label="Matching order counts by creation date">
        @forelse($chart as $date => $point)<p>{{ $date }}: {{ $point['orders'] }} orders</p>@empty<p>No orders yet. The chart is showing zero activity.</p>@endforelse
    </div>
    <h2 class="px-4 pt-4 font-semibold text-[#173F35]">Completed product breakdown</h2>
    <div class="overflow-x-auto p-4"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Product</th><th>Units sold</th><th>Gross sales</th></tr></thead><tbody>
        @forelse($breakdown as $row)<tr class="border-t border-[#EDF1EF]"><td class="p-2">{{ $row['name'] }}</td><td>{{ $row['units'] }}</td><td>₱{{ number_format($row['gross'], 2) }}</td></tr>@empty<tr><td colspan="3" class="p-4">No sales yet</td></tr>@endforelse
    </tbody></table></div>
    <h2 class="px-4 pt-4 font-semibold text-[#173F35]">Matching order items · {{ $paginator->total() }}</h2>
    <div class="overflow-x-auto p-4"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Order</th><th>Product</th><th>Status</th><th>Quantity</th><th>Gross amount</th></tr></thead><tbody>
        @forelse($paginator as $row)<tr class="border-t border-[#EDF1EF]"><td class="p-2">{{ $row->order_number }}</td><td>{{ $row->product_name }}</td><td>{{ $row->report_status }}</td><td>{{ $row->quantity }}</td><td>₱{{ number_format($row->gross_amount, 2) }}</td></tr>@empty<tr><td colspan="5" class="p-4">No matching order items. Try changing or resetting filters.</td></tr>@endforelse
    </tbody></table></div>
    <div class="p-4">{{ $paginator->links() }}</div>
</div>
</div>
@endsection
