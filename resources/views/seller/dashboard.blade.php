@extends('layouts.seller')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php
    $orders = collect(session('orders', []))->map(function ($order) {
        $order['status'] = strtolower($order['status'] ?? 'placed');
        return $order;
    });
    $products = collect(session('seller_products', []));
    $completedOrders = $orders->whereIn('status', ['delivered', 'completed']);
    $toConfirm = $orders->where('status', 'placed')->count();
    $preparing = $orders->whereIn('status', ['confirmed', 'preparing'])->count();
    $readyForPickup = $orders->where('status', 'ready_for_pickup')->count();
    $cancelledOrders = $orders->where('status', 'cancelled')->count();
    $totalRevenue = $completedOrders->sum(function ($order) {
        return (float) ($order['total'] ?? $order['grand_total'] ?? 0);
    });
    $customerCount = $orders
        ->map(function ($order) {
            return $order['buyer_id'] ?? $order['buyer_name'] ?? $order['customer_name'] ?? null;
        })
        ->filter(function ($customer) {
            return filled($customer);
        })
        ->unique()
        ->count();
    $lowStockList = $products->filter(function ($product) {
        return (int) ($product['stock'] ?? 0) <= 5;
    })->take(5);
    $lowStockProducts = $products->filter(function ($product) {
        return (int) ($product['stock'] ?? 0) <= 5;
    })->count();
    $recentOrders = $orders->sortByDesc(function ($order) {
        return $order['created_at'] ?? '';
    })->take(5);
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
        'returned' => 'Returned',
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
        'returned' => 'border-rose-200 bg-rose-50 text-rose-700',
        'cancelled' => 'border-gray-200 bg-gray-100 text-gray-600',
    ];

    $salesByDay = collect(range(6, 0))->map(function ($daysAgo) use ($completedOrders) {
        $date = \Carbon\Carbon::today()->subDays($daysAgo);
        $revenue = $completedOrders->filter(function ($order) use ($date) {
            return !empty($order['created_at'])
                && \Carbon\Carbon::parse($order['created_at'])->isSameDay($date);
        })->sum(function ($order) {
            return (float) ($order['total'] ?? $order['grand_total'] ?? 0);
        });

        return [
            'label' => $date->format('M d'),
            'revenue' => $revenue,
        ];
    })->values();
    $lastSevenDaysRevenue = $salesByDay->sum('revenue');
    $chartMax = max(1, (float) $salesByDay->max('revenue'));
    $chartPoints = [];
    foreach ($salesByDay as $index => $day) {
        $x = 38 + ($index * 104);
        $y = 164 - (((float) $day['revenue'] / $chartMax) * 126);
        $chartPoints[] = ['x' => $x, 'y' => $y];
    }
    $linePath = implode(' ', array_map(function ($point, $index) {
        return ($index === 0 ? 'M' : 'L') . $point['x'] . ',' . $point['y'];
    }, $chartPoints, array_keys($chartPoints)));
    $areaPath = $linePath . ' L ' . end($chartPoints)['x'] . ',164 L ' . $chartPoints[0]['x'] . ',164 Z';

    $statusSlices = [
        ['label' => 'Completed', 'count' => $completedOrders->count(), 'color' => '#1F6F5B'],
        ['label' => 'In progress', 'count' => $toConfirm + $preparing + $readyForPickup, 'color' => '#75AA98'],
        ['label' => 'Cancelled', 'count' => $cancelledOrders, 'color' => '#E8B86D'],
        ['label' => 'Other', 'count' => max(0, $orders->count() - $completedOrders->count() - $toConfirm - $preparing - $readyForPickup - $cancelledOrders), 'color' => '#DDE6E1'],
    ];
    $statusGradient = [];
    $statusOffset = 0;
    foreach ($statusSlices as $slice) {
        if ($slice['count'] > 0) {
            $nextOffset = $statusOffset + ($slice['count'] / max(1, $orders->count()) * 100);
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

<section aria-label="Store summary" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['label' => 'Total Products', 'value' => $products->count(), 'detail' => 'Manage your listings', 'icon' => 'package', 'href' => route('seller.products')],
        ['label' => 'Total Orders', 'value' => $orders->count(), 'detail' => 'View incoming orders', 'icon' => 'clipboard-list', 'href' => route('seller.orders')],
        ['label' => 'Total Revenue', 'value' => '₱' . number_format($totalRevenue, 2), 'detail' => 'From completed orders', 'icon' => 'wallet-cards', 'href' => route('seller.reports')],
        ['label' => 'Customers', 'value' => $customerCount, 'detail' => 'Unique customers', 'icon' => 'users-round', 'href' => route('seller.orders')],
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

<section class="mb-5 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
    <div class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="flex items-center justify-between gap-3 border-b border-[#EDF1EF] px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-[#24312C]">Revenue Overview</h3>
                <p class="mt-0.5 text-[11px] text-[#7C8983]">Completed sales over the last 7 days</p>
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
            <div class="mt-3 overflow-hidden">
                <svg viewBox="0 0 700 205" role="img" aria-label="Revenue trend over the last seven days" class="h-48 w-full overflow-visible">
                    <defs>
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
            <div class="relative flex h-40 w-40 items-center justify-center rounded-full" style="{{ $statusRingStyle }}">
                <div class="flex h-[112px] w-[112px] flex-col items-center justify-center rounded-full bg-white">
                    <span class="text-3xl font-semibold tracking-tight text-[#24312C]">{{ $orders->count() }}</span>
                    <span class="mt-0.5 text-[10px] text-[#839189]">total orders</span>
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

<section class="mb-5 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
    <div class="rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="border-b border-[#EDF1EF] px-5 py-4">
            <h3 class="text-sm font-semibold text-[#24312C]">Quick Actions</h3>
            <p class="mt-0.5 text-[11px] text-[#7C8983]">Shortcuts for your everyday store tasks</p>
        </div>
        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3">
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
                <p class="mt-0.5 text-[11px] text-[#7C8983]">Products with 5 or fewer items left</p>
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
                            <p class="mt-0.5 text-[10px] text-[#839189]">Restock soon</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold {{ $stock <= 0 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $stock <= 0 ? 'Out of stock' : $stock . ' left' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex items-center gap-3 px-5 py-7">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><i data-lucide="package-check" class="h-4 w-4"></i></span>
                <div>
                    <p class="text-xs font-semibold text-[#34483F]">Stock levels look good</p>
                    <p class="mt-0.5 text-[10px] text-[#839189]">No products currently need restocking.</p>
                </div>
            </div>
        @endif
    </div>
</section>

<section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
    <div class="flex items-center justify-between gap-3 border-b border-[#EDF1EF] px-5 py-4">
        <div>
            <h3 class="text-sm font-semibold text-[#24312C]">Recent Orders</h3>
            <p class="mt-0.5 text-[11px] text-[#7C8983]">The latest orders received by your store</p>
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
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F0F3F1]">
                    @foreach($recentOrders as $orderId => $order)
                        @php
                            $status = $order['status'] ?? 'placed';
                            $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                            $statusClass = $statusClasses[$status] ?? 'border-gray-200 bg-gray-100 text-gray-600';
                            $orderTotal = (float) ($order['total'] ?? $order['grand_total'] ?? 0);
                            $buyerName = $order['buyer_name'] ?? $order['customer_name'] ?? 'Buyer';
                            $orderDate = $order['created_at'] ?? null;
                            $orderItems = $order['items'] ?? [];
                            $firstItem = is_array($orderItems) ? reset($orderItems) : null;
                            $productName = $order['product_name'] ?? (is_array($firstItem) ? ($firstItem['product_name'] ?? $firstItem['name'] ?? null) : null);
                        @endphp
                        <tr class="text-xs transition hover:bg-[#FAFCFB]">
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-[#34483F]">#{{ $order['order_number'] ?? $orderId }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-[#617169]">{{ $buyerName }}</td>
                            <td class="max-w-[180px] truncate px-4 py-3 text-[#617169]">{{ $productName ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex whitespace-nowrap rounded-full border px-2 py-1 text-[9px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-[#34483F]">₱{{ number_format($orderTotal, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-[#7B8982]">
                                @if($orderDate) {{ \Carbon\Carbon::parse($orderDate)->format('M d, Y') }} @else — @endif
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
            <p class="mt-1 text-xs text-[#87958E]">New customer orders will appear here.</p>
        </div>
    @endif
</section>
@endsection
