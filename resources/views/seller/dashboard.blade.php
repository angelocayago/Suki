@extends('layouts.seller')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

<style>
    .seller-dashboard { min-width: 0; color: #24312C; font-family: 'Poppins', sans-serif; }
    .seller-dashboard > section { margin-bottom: 20px; }
    .seller-dashboard :is(h2,h3,p) { overflow-wrap: anywhere; }
    .seller-dashboard .dashboard-summary, .seller-dashboard .dashboard-financial { display: grid; grid-template-columns: minmax(0,1fr); gap: 12px; margin-bottom: 20px; }
    .seller-dashboard .dashboard-summary > a { min-width: 0; border: 1px solid #E1E8E4; border-radius: 16px; padding: 16px; background: #fff; box-shadow: 0 1px 3px #173F350a; }
    .seller-dashboard .dashboard-summary > a > div > div > p:first-child { font-size: 10px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: #839189; }
    .seller-dashboard .dashboard-summary > a > div > div > p:nth-child(2) { margin-top: 8px; font-size: 23px; font-weight: 600; letter-spacing: -.04em; color: #24312C; }
    .seller-dashboard .dashboard-summary > a > p { margin-top: 12px; font-size: 11px; color: #839189; }
    .seller-dashboard .dashboard-financial-card { min-width: 0; padding: 16px; border: 1px solid #E1E8E4; border-radius: 16px; background: #fff; }
    .seller-dashboard .dashboard-financial-card p { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #728078; }
    .seller-dashboard .dashboard-financial-card strong { display: block; margin-top: 8px; font-size: 18px; color: #173F35; }
    .seller-dashboard .dashboard-two-columns { display: grid; grid-template-columns: minmax(0,1fr); gap: 16px; }
    .seller-dashboard .dashboard-two-columns > div, .seller-dashboard > section:last-child { min-width: 0; border: 1px solid #E1E8E4; border-radius: 16px; background: #fff; overflow: hidden; }
    .seller-dashboard h3 { font-size: 14px; font-weight: 600; color: #24312C; }
    .seller-dashboard .dashboard-note { margin: 0 0 20px; font-size: 12px; line-height: 1.7; color: #728078; }
    .seller-dashboard .dashboard-note a { font-weight: 600; color: #1F6F5B; text-decoration: underline; }
    .seller-dashboard .dashboard-sales-chart { height: 192px; width: 100%; overflow: visible; }
    .seller-dashboard .dashboard-status-ring { display: flex; align-items: center; justify-content: center; width: 160px; height: 160px; border-radius: 50%; }
    .seller-dashboard .dashboard-status-centre { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 112px; height: 112px; border-radius: 50%; background: #fff; }
    .seller-dashboard .dashboard-quick-actions { display: grid; grid-template-columns: minmax(0,1fr); gap: 12px; padding: 16px; }
    .seller-dashboard table { width: 100%; min-width: 760px; }
    .seller-dashboard td { font-size: 12px; }
    .seller-dashboard .dashboard-tracking { display: block; margin-top: 4px; font-size: 10px; color: #7C8983; }
    .seller-dashboard :is(a,button):focus-visible { outline: 2px solid #1F6F5B; outline-offset: 3px; }
    .seller-dashboard .dashboard-top-products { border: 1px solid #E1E8E4; border-radius: 16px; overflow: hidden; background: #fff; }
    .seller-dashboard .dashboard-top-products header { padding: 16px 20px; border-bottom: 1px solid #EDF1EF; }
    .seller-dashboard .dashboard-top-products header p { margin-top: 2px; font-size: 11px; color: #7C8983; }
    .seller-dashboard .dashboard-top-products ul { list-style: none; margin: 0; padding: 0; }
    .seller-dashboard .dashboard-top-products li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding: 16px 20px; border-bottom: 1px solid #EDF1EF; font-size: 12px; }
    @media (min-width: 640px) {
        .seller-dashboard .dashboard-summary, .seller-dashboard .dashboard-financial { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .seller-dashboard .dashboard-quick-actions { grid-template-columns: repeat(3,minmax(0,1fr)); }
    }
    @media (min-width: 1280px) {
        .seller-dashboard .dashboard-summary, .seller-dashboard .dashboard-financial { grid-template-columns: repeat(4,minmax(0,1fr)); }
        .seller-dashboard .dashboard-two-columns { grid-template-columns: minmax(0,2fr) minmax(280px,1fr); }
    }
</style>
<div class="seller-dashboard">

@php
    if (!isset($salesByDay)) {
        extract(app(\App\Support\SellerDashboard::class)->forUser(auth()->user()));
    }
    $statusLabels = [
        'placed' => 'To Confirm',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready_for_pickup' => 'Ready for Pickup',
        'picked_up' => 'Picked Up',
        'at_sorting_center' => 'At Sorting Center',
        'sorted' => 'Sorted',
        'assigned_to_rider' => 'Assigned to Rider',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'delivery_failed' => 'Delivery Failed',
        'rts_initiated' => 'Return initiated',
        'rts_returning' => 'Returning to Seller',
        'rts_completed' => 'Returned to Seller',
        'unknown' => 'Unknown workflow',
        'cancelled' => 'Cancelled',
    ];
    $statusClasses = [
        'placed' => 'border-amber-200 bg-amber-50 text-amber-700',
        'confirmed' => 'border-blue-200 bg-blue-50 text-blue-700',
        'preparing' => 'border-violet-200 bg-violet-50 text-violet-700',
        'ready_for_pickup' => 'border-indigo-200 bg-indigo-50 text-indigo-700',
        'picked_up' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
        'at_sorting_center' => 'border-sky-200 bg-sky-50 text-sky-700',
        'sorted' => 'border-teal-200 bg-teal-50 text-teal-700',
        'assigned_to_rider' => 'border-violet-200 bg-violet-50 text-violet-700',
        'out_for_delivery' => 'border-orange-200 bg-orange-50 text-orange-700',
        'delivered' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'delivery_failed' => 'border-red-200 bg-red-50 text-red-700',
        'rts_initiated' => 'border-rose-200 bg-rose-50 text-rose-700',
        'rts_returning' => 'border-rose-200 bg-rose-50 text-rose-700',
        'rts_completed' => 'border-rose-200 bg-rose-50 text-rose-700',
        'unknown' => 'border-gray-200 bg-gray-100 text-gray-600',
        'cancelled' => 'border-gray-200 bg-gray-100 text-gray-600',
    ];

    $lastSevenDaysRevenue = $salesByDay->sum('gross');
    $chartMax = max(1, (float) $salesByDay->max('gross'));
    $chartPoints = [];
    foreach ($salesByDay as $index => $day) {
        $x = 38 + ($index * 104);
        $y = 164 - (((float) $day['gross'] / $chartMax) * 126);
        $chartPoints[] = ['x' => $x, 'y' => $y];
    }
    $linePath = implode(' ', array_map(function ($point, $index) {
        return ($index === 0 ? 'M' : 'L') . $point['x'] . ',' . $point['y'];
    }, $chartPoints, array_keys($chartPoints)));
    $areaPath = $linePath . ' L ' . end($chartPoints)['x'] . ',164 L ' . $chartPoints[0]['x'] . ',164 Z';

    $cancelledOrFailed = array_sum(array_intersect_key($statusCounts, array_flip(['cancelled', 'delivery_failed', 'rts_initiated', 'rts_returning', 'rts_completed'])));
    $deliveredCount = $statusCounts['delivered'] ?? 0;
    $unknownCount = $statusCounts['unknown'] ?? 0;
    $statusSlices = [
        ['label' => 'Completed', 'count' => $completedCount, 'color' => '#1F6F5B'],
        ['label' => 'In progress', 'count' => $orderCount - $completedCount - $deliveredCount - $cancelledOrFailed - $unknownCount, 'color' => '#75AA98'],
        ['label' => 'Delivered', 'count' => $deliveredCount, 'color' => '#9ABCCB'],
        ['label' => 'Cancelled / Failed / RTS', 'count' => $cancelledOrFailed, 'color' => '#E8B86D'],
        ['label' => 'Unknown workflow', 'count' => $unknownCount, 'color' => '#DDE6E1'],
    ];
    $statusGradient = [];
    $statusOffset = 0;
    foreach ($statusSlices as $slice) {
        if ($slice['count'] > 0) {
            $nextOffset = $statusOffset + ($slice['count'] / max(1, $orderCount) * 100);
            $statusGradient[] = $slice['color'] . ' ' . $statusOffset . '% ' . $nextOffset . '%';
            $statusOffset = $nextOffset;
        }
    }
    $statusRingStyle = $statusGradient
        ? 'background: conic-gradient(' . implode(', ', $statusGradient) . ');'
        : 'background: conic-gradient(#E8EEEA 0% 100%);';
@endphp

@if(session('success'))
    <div class="mb-5 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <i data-lucide="circle-check" class="h-4 w-4 shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="mb-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <i data-lucide="triangle-alert" class="h-4 w-4 shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

<section class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="mt-1 text-sm text-[#728078]">Manage your products, orders, and store performance in one place.</p>
    </div>
    <a href="{{ route('seller.products.create') }}" class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-xl bg-[#1F6F5B] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#173F35] sm:self-auto">
        <i data-lucide="plus" class="h-4 w-4"></i>
        Add Product
    </a>
</section>

<section aria-label="Store summary" class="dashboard-summary">
    @foreach([
        ['label' => 'Total Products', 'value' => $productCount, 'detail' => 'Manage your listings', 'icon' => 'package', 'href' => route('seller.products')],
        ['label' => 'Total Seller Orders', 'value' => $orderCount, 'detail' => 'Current Seller orders', 'icon' => 'clipboard-list', 'href' => route('seller.orders')],
        ['label' => 'Gross Completed Sales', 'value' => '₱' . number_format($grossCompleted, 2), 'detail' => 'Financially completed orders only', 'icon' => 'wallet-cards', 'href' => route('seller.reports')],
        ['label' => 'Customers', 'value' => $customerCount, 'detail' => 'Distinct saved Buyer identities', 'icon' => 'users-round', 'href' => route('seller.orders')],
    ] as $metric)
        <a href="{{ $metric['href'] }}" class="group rounded-2xl border border-[#E1E8E4] bg-white p-4 shadow-[0_1px_3px_rgba(23,63,53,0.04)] transition hover:-translate-y-0.5 hover:border-[#BFD2C9] hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#839189]">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-[23px] font-semibold tracking-[-0.04em] text-[#24312C]">{{ $metric['value'] }}</p>
                </div>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#DDF3EC] text-[#1F6F5B] transition group-hover:bg-[#1F6F5B] group-hover:text-white">
                    <i data-lucide="{{ $metric['icon'] }}" class="h-4 w-4"></i>
                </span>
            </div>
            <p class="mt-3 text-[11px] text-[#839189]">{{ $metric['detail'] }} <span class="text-[#1F6F5B]">→</span></p>
        </a>
    @endforeach
</section>
<p class="dashboard-note">Gross completed sales exclude shipping and are before commission and seller-funded discount deductions. Delivered orders are excluded until completed. Net sales are not a withdrawable wallet balance. <a href="{{ route('seller.wallet') }}">View wallet and payouts</a>.</p>
<section class="dashboard-financial" aria-label="Completed sales breakdown">
    @foreach([
        ['label' => 'Completed raw sales', 'minor' => $completedSales['raw_sales_minor']],
        ['label' => 'Platform commission', 'minor' => $completedSales['commission_minor']],
        ['label' => 'Seller-funded discounts', 'minor' => $completedSales['seller_funded_discount_minor']],
        ['label' => 'Completed net sales', 'minor' => $completedSales['net_sales_minor']],
    ] as $financial)
        <div class="dashboard-financial-card"><p>{{ $financial['label'] }}</p><strong>&#8369;{{ number_format($financial['minor'] / 100, 2) }}</strong></div>
    @endforeach
</section>

<section class="dashboard-two-columns">
    <div class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="flex items-center justify-between gap-3 border-b border-[#EDF1EF] px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-[#24312C]">Completed Sales Overview</h3>
                <p class="mt-0.5 text-[11px] text-[#7C8983]">Gross merchandise by completion date ({{ $businessTimezone }})</p>
            </div>
            <a href="{{ route('seller.reports') }}" class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#1F6F5B] hover:text-[#173F35]">
                View reports <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
            </a>
        </div>
        <div class="px-4 pb-3 pt-5 sm:px-5">
            <div class="flex items-end justify-between">
                <p class="text-xl font-semibold tracking-tight text-[#24312C]">₱{{ number_format($lastSevenDaysRevenue, 2) }}</p>
                <p class="text-[10px] text-[#839189]">Last 7 days</p>
            </div>
            @if($lastSevenDaysRevenue == 0)<p class="dashboard-note">No completed sales in the last seven days.</p>@endif
            <div class="mt-3 overflow-hidden">
                <svg viewBox="0 0 700 205" role="img" aria-label="Completed Seller-item gross over the last seven days" class="dashboard-sales-chart">
                    <desc>@foreach($salesByDay as $day){{ $day['label'] }}: {{ number_format($day['gross'], 2) }} PHP. @endforeach</desc><defs>
                        <linearGradient id="revenueFill" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#1F6F5B" stop-opacity=".2"></stop>
                            <stop offset="100%" stop-color="#1F6F5B" stop-opacity=".01"></stop>
                        </linearGradient>
                    </defs>
                    <line x1="38" y1="38" x2="662" y2="38" stroke="#EDF1EF" stroke-width="1"></line>
                    <line x1="38" y1="101" x2="662" y2="101" stroke="#EDF1EF" stroke-width="1"></line>
                    <line x1="38" y1="164" x2="662" y2="164" stroke="#DDE6E1" stroke-width="1"></line>
                    <text x="2" y="42" fill="#91A099" font-size="10">₱{{ number_format($chartMax, 0) }}</text>
                    <text x="2" y="105" fill="#91A099" font-size="10">₱{{ number_format($chartMax / 2, 0) }}</text>
                    <text x="2" y="168" fill="#91A099" font-size="10">₱0</text>
                    <path d="{{ $areaPath }}" fill="url(#revenueFill)"></path>
                    <path d="{{ $linePath }}" fill="none" stroke="#1F6F5B" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path>
                    @foreach($chartPoints as $index => $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="#fff" stroke="#1F6F5B" stroke-width="2"></circle>
                        <text x="{{ $point['x'] }}" y="190" fill="#839189" font-size="10" text-anchor="middle">{{ $salesByDay[$index]['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="border-b border-[#EDF1EF] px-5 py-4">
            <h3 class="text-sm font-semibold text-[#24312C]">Order Status</h3>
            <p class="mt-0.5 text-[11px] text-[#7C8983]">A quick look at fulfillment</p>
        </div>
        <div class="flex flex-col items-center px-5 py-5">
            <div class="dashboard-status-ring" style="{{ $statusRingStyle }}">
                <div class="dashboard-status-centre">
                    <span class="text-3xl font-semibold tracking-tight text-[#24312C]">{{ $orderCount }}</span>
                    <span class="mt-0.5 text-[10px] text-[#839189]">Seller segments</span>
                </div>
            </div>
            <div class="mt-5 grid w-full grid-cols-2 gap-x-3 gap-y-2">
                @foreach($statusSlices as $slice)
                    <div class="flex min-w-0 items-center gap-2 text-[10px]">
                        <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $slice['color'] }}"></span>
                        <span class="truncate text-[#728078]">{{ $slice['label'] }}</span>
                        <span class="ml-auto font-semibold text-[#34483F]">{{ $slice['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="dashboard-two-columns">
    <div class="rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="border-b border-[#EDF1EF] px-5 py-4">
            <h3 class="text-sm font-semibold text-[#24312C]">Quick Actions</h3>
            <p class="mt-0.5 text-[11px] text-[#7C8983]">Shortcuts for your everyday store tasks</p>
        </div>
        <div class="dashboard-quick-actions">
            <a href="{{ route('seller.products.create') }}" class="group flex items-center gap-3 rounded-xl border border-[#E8EEEA] px-3 py-3 transition hover:border-[#BFD2C9] hover:bg-[#F8FAF8]">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#DDF3EC] text-[#1F6F5B] group-hover:bg-[#1F6F5B] group-hover:text-white">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                </span>
                <span><span class="block text-xs font-semibold text-[#34483F]">Add Product</span><span class="mt-0.5 block text-[10px] text-[#839189]">Create a new listing</span></span>
            </a>
            <a href="{{ route('seller.orders') }}" class="group flex items-center gap-3 rounded-xl border border-[#E8EEEA] px-3 py-3 transition hover:border-[#BFD2C9] hover:bg-[#F8FAF8]">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#EEF5F1] text-[#1F6F5B] group-hover:bg-[#1F6F5B] group-hover:text-white">
                    <i data-lucide="clipboard-list" class="h-4 w-4"></i>
                </span>
                <span><span class="block text-xs font-semibold text-[#34483F]">View Orders</span><span class="mt-0.5 block text-[10px] text-[#839189]">{{ $toConfirm }} awaiting confirmation</span></span>
            </a>
            <a href="{{ route('seller.store.profile') }}" class="group flex items-center gap-3 rounded-xl border border-[#E8EEEA] px-3 py-3 transition hover:border-[#BFD2C9] hover:bg-[#F8FAF8]">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#F3F5F4] text-[#5F6E67] group-hover:bg-[#173F35] group-hover:text-white">
                    <i data-lucide="store" class="h-4 w-4"></i>
                </span>
                <span><span class="block text-xs font-semibold text-[#34483F]">Store Settings</span><span class="mt-0.5 block text-[10px] text-[#839189]">Update your store profile</span></span>
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="flex items-center justify-between gap-3 border-b border-[#EDF1EF] px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-[#24312C]">Inventory Alerts</h3>
                <p class="mt-0.5 text-[11px] text-[#7C8983]">Active variants with {{ $stockThreshold }} or fewer units; {{ $lowStockProducts }} products / {{ $lowStockVariants }} variants</p>
            </div>
            <a href="{{ route('seller.inventory') }}" class="shrink-0 text-[10px] font-semibold text-[#1F6F5B] hover:text-[#173F35]">Manage <span aria-hidden="true">→</span></a>
        </div>
        @if($lowStockList->isNotEmpty())
            <div class="divide-y divide-[#EDF1EF]">
                @foreach($lowStockList as $product)
                    @php $stock = (int) ($product['stock'] ?? 0); @endphp
                    <div class="flex items-center gap-3 px-4 py-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#F1F5F3]">
                            @if(!empty($product['image']))
                                <img src="{{ $product['image'] }}" alt="{{ $product['name'] ?? 'Product' }}" class="h-full w-full object-cover">
                            @else
                                <i data-lucide="package" class="h-4 w-4 text-[#91A099]"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-[#34483F]">{{ $product['name'] ?? 'Product' }}</p>
                            <p class="mt-0.5 text-[10px] text-[#839189]">{{ $stock }} total active units</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold {{ $stock <= 0 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $product['out_of_stock_variants'] }} out / {{ $product['low_stock_variants'] }} low
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex items-center gap-3 px-5 py-7">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><i data-lucide="package-check" class="h-4 w-4"></i></span>
                <div>
                    <p class="text-xs font-semibold text-[#34483F]">{{ $productCount === 0 ? 'No products yet' : 'No low-stock alerts' }}</p>
                    <p class="mt-0.5 text-[10px] text-[#839189]">{{ $productCount === 0 ? 'Add a product to begin tracking inventory.' : 'No active variants currently need restocking.' }}</p>
                </div>
            </div>
        @endif
    </div>
</section>

<section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
    <div class="flex items-center justify-between gap-3 border-b border-[#EDF1EF] px-5 py-4">
        <div>
            <h3 class="text-sm font-semibold text-[#24312C]">Recent Orders</h3>
            <p class="mt-0.5 text-[11px] text-[#7C8983]">Latest Seller-owned orders and saved purchase snapshots</p>
        </div>
        <a href="{{ route('seller.orders') }}" class="inline-flex shrink-0 items-center gap-1 text-[11px] font-semibold text-[#1F6F5B] hover:text-[#173F35]">
            View all <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
        </a>
    </div>
    @if($recentOrders->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px]">
                <thead class="bg-[#F8FAF8]">
                    <tr class="border-b border-[#EDF1EF] text-left text-[9px] font-semibold uppercase tracking-[0.08em] text-[#728078]">
                        <th class="px-4 py-3">Order ID</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Seller Item Gross</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F0F3F1]">
                    @foreach($recentOrders as $order)
                        @php
                            $status = $order['status'] ?? '';
                            $statusLabel = $status === '' ? 'Not recorded' : ($statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status)));
                            $statusClass = $statusClasses[$status] ?? 'border-gray-200 bg-gray-100 text-gray-600';
                            $orderTotal = (float) $order['gross_amount'];
                            $buyerName = $order['recipient_name'] ?: 'Not recorded';
                            $orderDate = $order['created_at'];
                            $productName = $order['product_name'];
                        @endphp
                        <tr class="text-xs transition hover:bg-[#FAFCFB]">
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-[#34483F]">#{{ $order['display_reference'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-[#617169]">{{ $buyerName }}</td>
                            <td class="max-w-[180px] truncate px-4 py-3 text-[#617169]">{{ $productName ?? '—' }} @if($order['tracking_code'])<span class="dashboard-tracking">{{ $order['tracking_code'] }}</span>@endif</td>
                            <td class="px-4 py-3"><span class="inline-flex whitespace-nowrap rounded-full border px-2 py-1 text-[9px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-[#34483F]">₱{{ number_format($orderTotal, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-[#7B8982]">
                                @if($orderDate) {{ \Carbon\CarbonImmutable::parse($orderDate, config('app.timezone'))->setTimezone($businessTimezone)->format('M d, Y') }} @else — @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <a href="{{ route('seller.orders') }}" class="font-semibold text-[#1F6F5B] hover:text-[#173F35]">View <span aria-hidden="true">→</span></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-[#F1F5F3] text-[#87958E]"><i data-lucide="shopping-bag" class="h-5 w-5"></i></span>
            <p class="mt-3 text-sm font-semibold text-[#34483F]">No orders yet</p>
            <p class="mt-1 text-xs text-[#87958E]">Orders from your buyers will appear here.</p>
        </div>
    @endif
</section>

<section class="dashboard-top-products" aria-label="Top products">
    <header><h3>Top Products</h3><p>Ranked by units in completed Seller orders</p></header>
    <ul>
        @forelse($topProducts as $product)
            <li><strong>{{ $product['name'] }}</strong><span>{{ $product['units'] }} sold &middot; &#8369;{{ number_format($product['raw_minor'] / 100, 2) }} gross</span></li>
        @empty
            <li>No completed product sales yet.</li>
        @endforelse
    </ul>
</section>
</div>
@endsection
