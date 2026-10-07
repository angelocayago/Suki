@extends('layouts.seller')

@section('title', 'Orders')
@section('page-title', 'Orders')

@section('content')
@php
    $allOrders = collect($orders ?? []);

    $statusLabels = [
        'placed' => 'Placed',
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

    $placedCount = $allOrders->filter(fn ($order) => strtolower($order['status'] ?? 'placed') === 'placed')->count();
    $processingCount = $allOrders->filter(fn ($order) => in_array(strtolower($order['status'] ?? ''), ['confirmed', 'preparing'], true))->count();
    $readyCount = $allOrders->filter(fn ($order) => strtolower($order['status'] ?? '') === 'ready_for_pickup')->count();
    $completedCount = $allOrders->filter(fn ($order) => in_array(strtolower($order['status'] ?? ''), ['delivered', 'completed'], true))->count();
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

<section class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#1F6F5B]">Seller Centre</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-[-0.04em] text-[#24312C] sm:text-[28px]">Orders</h2>
        <p class="mt-1 text-sm text-[#728078]">Review order items, customer details, and fulfillment status.</p>
    </div>
    <div class="flex flex-wrap gap-2 text-[10px] font-medium">
        <span class="rounded-full bg-white px-3 py-1.5 text-[#52635B] ring-1 ring-[#E1E8E4]">{{ $allOrders->count() }} total</span>
        <span class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-700">{{ $placedCount }} placed</span>
        <span class="rounded-full bg-violet-50 px-3 py-1.5 text-violet-700">{{ $processingCount }} processing</span>
        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-indigo-700">{{ $readyCount }} ready</span>
        <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700">{{ $completedCount }} completed</span>
    </div>
</section>

<section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white shadow-[0_1px_3px_rgba(23,63,53,0.04)]">
    <div class="flex flex-col gap-4 border-b border-[#EDF1EF] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div>
            <h3 class="text-sm font-semibold text-[#24312C]">Customer Orders</h3>
            <p class="mt-0.5 text-[11px] text-[#7C8983]">Update an order or open its details.</p>
        </div>
        <div class="grid gap-2 sm:grid-cols-[minmax(220px,1fr)_180px_auto]">
            <label class="relative">
                <span class="sr-only">Search orders</span>
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#91A099]"></i>
                <input id="orderSearch" type="search" placeholder="Search order, customer, or item" class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white pl-9 pr-3 text-xs text-[#34483F] placeholder:text-[#9AA69F] focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#DDF3EC]/70">
            </label>
            <label>
                <span class="sr-only">Filter by status</span>
                <select id="statusFilter" class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs font-medium text-[#52635B] focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#DDF3EC]/70">
                    <option value="all">All statuses</option>
                    @foreach($statusLabels as $status => $label)
                        <option value="{{ $status }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button id="clearOrderFilters" type="button" class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-[#DDE6E1] bg-white px-3 text-[11px] font-semibold text-[#68776F] transition hover:bg-[#F3F7F5] hover:text-[#173F35]">
                <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Clear
            </button>
        </div>
    </div>

    @if($allOrders->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px]">
                <thead class="bg-[#F7F9F8]">
                    <tr class="border-b border-[#EDF1EF] text-left text-[9px] font-semibold uppercase tracking-[0.08em] text-[#728078]">
                        <th class="px-4 py-3">Order #</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F0F3F1]">
                    @foreach($allOrders as $orderKey => $order)
                        @php
                            $orderId = $order['id'] ?? $orderKey;
                            $status = strtolower($order['status'] ?? 'placed');
                            $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                            $statusClass = $statusClasses[$status] ?? 'border-gray-200 bg-gray-100 text-gray-600';
                            $address = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : [];
                            $recipient = $address['name'] ?? null;
                            $phone = $address['phone'] ?? null;
                            $items = collect($order['items'] ?? [])->values();
                            $searchItems = $items->map(function ($item) {
                                return ($item['name'] ?? '') . ' ' . ($item['variation'] ?? '');
                            })->implode(' ');
                            $searchValue = strtolower($orderId . ' ' . ($recipient ?? '') . ' ' . ($phone ?? '') . ' ' . $searchItems);
                            $modalId = 'order-details-' . md5((string) $orderId . '-' . $loop->index);
                            $nextStatus = match ($status) {
                                'placed' => 'confirmed',
                                'confirmed' => 'preparing',
                                'preparing' => 'ready_for_pickup',
                                default => null,
                            };
                            $nextActionLabel = match ($status) {
                                'placed' => 'Confirm',
                                'confirmed' => 'Prepare',
                                'preparing' => 'Mark ready',
                                default => null,
                            };
                            $timeline = [
                                'placed' => ['label' => 'Placed', 'date' => $order['created_at'] ?? null],
                                'confirmed' => ['label' => 'Confirmed', 'date' => $order['confirmed_at'] ?? null],
                                'preparing' => ['label' => 'Preparing', 'date' => $order['preparing_at'] ?? null],
                                'ready_for_pickup' => ['label' => 'Ready for pickup', 'date' => $order['ready_for_pickup_at'] ?? null],
                                'picked_up' => ['label' => 'Picked up', 'date' => $order['picked_up_at'] ?? null],
                                'at_sorting_center' => ['label' => 'At sorting center', 'date' => $order['at_sorting_center_at'] ?? null],
                                'sorted' => ['label' => 'Sorted', 'date' => $order['sorted_at'] ?? null],
                                'assigned_to_rider' => ['label' => 'Assigned to rider', 'date' => $order['assigned_to_rider_at'] ?? null],
                                'out_for_delivery' => ['label' => 'Out for delivery', 'date' => $order['out_for_delivery_at'] ?? null],
                                'delivered' => ['label' => 'Delivered', 'date' => $order['delivered_at'] ?? null],
                                'completed' => ['label' => 'Completed', 'date' => $order['completed_at'] ?? null],
                                'delivery_failed' => ['label' => 'Delivery failed', 'date' => $order['delivery_failed_at'] ?? null],
                                'returned' => ['label' => 'Returned', 'date' => $order['returned_at'] ?? null],
                                'cancelled' => ['label' => 'Cancelled', 'date' => $order['cancelled_at'] ?? null],
                            ];
                            $recordedTimeline = collect($timeline)->filter(fn ($entry) => filled($entry['date']));
                            $addressParts = collect([
                                $address['house_number'] ?? null,
                                $address['street'] ?? null,
                                $address['barangay'] ?? null,
                                $address['municipality'] ?? null,
                                $address['province'] ?? null,
                                $address['postal_code'] ?? null,
                            ])->filter(fn ($part) => filled($part));
                        @endphp
                        <tr class="order-row text-xs transition hover:bg-[#FAFCFB]" data-search="{{ $searchValue }}" data-status="{{ $status }}">
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <p class="font-semibold text-[#34483F]">#{{ $orderId }}</p>
                                <p class="mt-1 text-[10px] text-[#87958E]">
                                    @if(!empty($order['created_at']))
                                        {{ \Carbon\Carbon::parse($order['created_at'])->format('M d, Y · h:i A') }}
                                    @else
                                        —
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="font-medium text-[#34483F]">{{ $recipient ?: '—' }}</p>
                                @if($phone)
                                    <p class="mt-1 text-[10px] text-[#87958E]">{{ $phone }}</p>
                                @endif
                            </td>
                            <td class="max-w-[280px] px-4 py-3.5">
                                <div class="space-y-1.5">
                                    @forelse($items->take(2) as $item)
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#E4EAE6] bg-[#F1F4F2]">
                                                @if(!empty($item['image']))
                                                    <img src="{{ $item['image'] }}" alt="{{ $item['name'] ?? 'Product' }}" class="h-full w-full object-cover">
                                                @else
                                                    <i data-lucide="package" class="h-3.5 w-3.5 text-[#89968F]"></i>
                                                @endif
                                            </span>
                                            <span class="min-w-0 truncate text-[#52635B]">
                                                {{ $item['name'] ?? '—' }}
                                                <span class="text-[#87958E]">×{{ $item['quantity'] ?? '—' }}</span>
                                                @if(!empty($item['variation']))
                                                    <span class="text-[#87958E]">({{ $item['variation'] }})</span>
                                                @endif
                                            </span>
                                        </div>
                                    @empty
                                        <span class="text-[#87958E]">—</span>
                                    @endforelse
                                    @if($items->count() > 2)
                                        <p class="pl-10 text-[10px] font-medium text-[#1F6F5B]">+{{ $items->count() - 2 }} more item(s)</p>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-[#34483F]">
                                @if(isset($order['total']))
                                    ₱{{ number_format((float) $order['total'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-1 text-[10px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2">
                                    <button type="button" data-order-dialog="{{ $modalId }}" aria-label="View order {{ $orderId }} details" title="View order details" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#DDE6E1] bg-white text-[#1F6F5B] transition hover:border-[#1F6F5B] hover:bg-[#EEF5F1] focus:outline-none focus:ring-2 focus:ring-[#DDF3EC]">
                                        <i data-lucide="eye" class="h-4 w-4"></i>
                                    </button>
                                    @if($nextStatus)
                                        <form action="{{ route('order.status.update', $orderId) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $nextStatus }}">
                                            <button type="submit" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-[#173F35] px-3 text-[10px] font-semibold text-white transition hover:bg-[#1F6F5B]">
                                                @if($status === 'placed')
                                                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                                @elseif($status === 'confirmed')
                                                    <i data-lucide="package-open" class="h-3.5 w-3.5"></i>
                                                @else
                                                    <i data-lucide="package-check" class="h-3.5 w-3.5"></i>
                                                @endif
                                                {{ $nextActionLabel }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <dialog id="{{ $modalId }}" class="order-details-dialog m-auto max-h-[90vh] w-[min(960px,calc(100%-2rem))] overflow-y-auto rounded-2xl border border-[#DDE6E1] bg-[#F8FAF8] p-0 text-[#24312C] shadow-2xl backdrop:bg-[#102C25]/55 backdrop:backdrop-blur-sm">
                            <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-[#E1E8E4] bg-white px-5 py-4 sm:px-6">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#1F6F5B]">Order details</p>
                                    <h3 class="mt-1 truncate text-lg font-semibold text-[#24312C]">#{{ $orderId }}</h3>
                                </div>
                                <button type="button" data-close-dialog aria-label="Close order details" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#DDE6E1] text-[#68776F] transition hover:bg-[#F3F7F5] hover:text-[#173F35]">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                </button>
                            </div>

                            <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,1fr)]">
                                <div class="space-y-4">
                                    <section class="rounded-xl border border-[#E1E8E4] bg-white p-4 sm:p-5">
                                        <div class="mb-4 flex items-center gap-2">
                                            <i data-lucide="user-round" class="h-4 w-4 text-[#1F6F5B]"></i>
                                            <h4 class="text-sm font-semibold text-[#24312C]">Customer Information</h4>
                                        </div>
                                        <dl class="grid gap-3 text-xs sm:grid-cols-2">
                                            <div>
                                                <dt class="text-[10px] font-medium uppercase tracking-wide text-[#87958E]">Name</dt>
                                                <dd class="mt-1 font-medium text-[#34483F]">{{ $recipient ?: '—' }}</dd>
                                            </div>
                                            @if($phone)
                                                <div>
                                                    <dt class="text-[10px] font-medium uppercase tracking-wide text-[#87958E]">Phone</dt>
                                                    <dd class="mt-1 font-medium text-[#34483F]">{{ $phone }}</dd>
                                                </div>
                                            @endif
                                            @if($addressParts->isNotEmpty())
                                                <div class="sm:col-span-2">
                                                    <dt class="text-[10px] font-medium uppercase tracking-wide text-[#87958E]">Shipping Address</dt>
                                                    <dd class="mt-1 leading-5 text-[#52635B]">{{ $addressParts->implode(', ') }}</dd>
                                                </div>
                                            @endif
                                            @if(!empty($address['label']))
                                                <div>
                                                    <dt class="text-[10px] font-medium uppercase tracking-wide text-[#87958E]">Address Label</dt>
                                                    <dd class="mt-1 text-[#52635B]">{{ $address['label'] }}</dd>
                                                </div>
                                            @endif
                                            @if(!empty($order['created_at']))
                                                <div>
                                                    <dt class="text-[10px] font-medium uppercase tracking-wide text-[#87958E]">Order Date</dt>
                                                    <dd class="mt-1 text-[#52635B]">{{ \Carbon\Carbon::parse($order['created_at'])->format('M d, Y · h:i A') }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                    </section>

                                    <section class="overflow-hidden rounded-xl border border-[#E1E8E4] bg-white">
                                        <div class="border-b border-[#EDF1EF] px-4 py-3.5 sm:px-5">
                                            <h4 class="text-sm font-semibold text-[#24312C]">Order Items</h4>
                                        </div>
                                        @if($items->isNotEmpty())
                                            <div class="divide-y divide-[#EDF1EF]">
                                                @foreach($items as $item)
                                                    <div class="flex items-center gap-3 px-4 py-3 sm:px-5">
                                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#E4EAE6] bg-[#F1F4F2]">
                                                            @if(!empty($item['image']))
                                                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] ?? 'Product' }}" class="h-full w-full object-cover">
                                                            @else
                                                                <i data-lucide="package" class="h-4 w-4 text-[#89968F]"></i>
                                                            @endif
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-xs font-semibold text-[#34483F]">{{ $item['name'] ?? '—' }}</p>
                                                            @if(!empty($item['variation']))
                                                                <p class="mt-0.5 text-[10px] text-[#87958E]">{{ $item['variation'] }}</p>
                                                            @endif
                                                        </div>
                                                        <div class="shrink-0 text-right text-[10px] text-[#68776F]">
                                                            @if(isset($item['quantity']))
                                                                <p>Qty {{ $item['quantity'] }}</p>
                                                            @endif
                                                            @if(isset($item['price']))
                                                                <p class="mt-0.5">₱{{ number_format((float) $item['price'], 2) }}</p>
                                                            @endif
                                                            @if(isset($item['line_total']))
                                                                <p class="mt-0.5 font-semibold text-[#34483F]">₱{{ number_format((float) $item['line_total'], 2) }}</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="px-5 py-6 text-xs text-[#87958E]">No item details available.</p>
                                        @endif
                                    </section>

                                    @if($recordedTimeline->isNotEmpty())
                                        <section class="rounded-xl border border-[#E1E8E4] bg-white p-4 sm:p-5">
                                            <h4 class="text-sm font-semibold text-[#24312C]">Recorded Status Updates</h4>
                                            <ol class="mt-4 space-y-3">
                                                @foreach($recordedTimeline as $entry)
                                                    <li class="flex items-start gap-3">
                                                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#1F6F5B] ring-4 ring-[#DDF3EC]"></span>
                                                        <div>
                                                            <p class="text-xs font-medium text-[#34483F]">{{ $entry['label'] }}</p>
                                                            <p class="mt-0.5 text-[10px] text-[#87958E]">{{ \Carbon\Carbon::parse($entry['date'])->format('M d, Y · h:i A') }}</p>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </section>
                                    @endif
                                </div>

                                <aside class="space-y-4">
                                    <section class="rounded-xl border border-[#E1E8E4] bg-white p-4 sm:p-5">
                                        <h4 class="text-sm font-semibold text-[#24312C]">Order Summary</h4>
                                        <dl class="mt-4 space-y-3 text-xs">
                                            @if(isset($order['subtotal']))
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Subtotal</dt><dd class="font-medium text-[#34483F]">₱{{ number_format((float) $order['subtotal'], 2) }}</dd></div>
                                            @endif
                                            @if(isset($order['discount']) && (float) $order['discount'] != 0)
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Discount</dt><dd class="font-medium text-[#34483F]">−₱{{ number_format((float) $order['discount'], 2) }}</dd></div>
                                            @endif
                                            @if(isset($order['shipping']))
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Shipping</dt><dd class="font-medium text-[#34483F]">₱{{ number_format((float) $order['shipping'], 2) }}</dd></div>
                                            @endif
                                            @if(isset($order['total']))
                                                <div class="flex justify-between gap-3 border-t border-[#EDF1EF] pt-3"><dt class="font-semibold text-[#34483F]">Total</dt><dd class="font-semibold text-[#173F35]">₱{{ number_format((float) $order['total'], 2) }}</dd></div>
                                            @endif
                                        </dl>
                                    </section>

                                    <section class="rounded-xl border border-[#E1E8E4] bg-white p-4 sm:p-5">
                                        <h4 class="text-sm font-semibold text-[#24312C]">Fulfillment</h4>
                                        <dl class="mt-4 space-y-3 text-xs">
                                            <div class="flex items-center justify-between gap-3">
                                                <dt class="text-[#728078]">Order Status</dt>
                                                <dd><span class="inline-flex rounded-full border px-2 py-1 text-[10px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span></dd>
                                            </div>
                                            @if(!empty($order['shipping_method']))
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Shipping Method</dt><dd class="text-right font-medium text-[#34483F]">{{ $order['shipping_method'] }}</dd></div>
                                            @endif
                                            @if(!empty($order['payment_method']))
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Payment Method</dt><dd class="text-right font-medium text-[#34483F]">{{ ucfirst(str_replace('_', ' ', $order['payment_method'])) }}</dd></div>
                                            @endif
                                            @if(!empty($order['payment_status']))
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Payment Status</dt><dd class="text-right font-medium text-[#34483F]">{{ ucfirst(str_replace('_', ' ', $order['payment_status'])) }}</dd></div>
                                            @endif
                                            @if(!empty($order['rider_name']))
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Assigned Rider</dt><dd class="text-right font-medium text-[#34483F]">{{ $order['rider_name'] }}</dd></div>
                                            @endif
                                            @if(!empty($order['area']) || !empty($order['assigned_area']))
                                                <div class="flex justify-between gap-3"><dt class="text-[#728078]">Delivery Area</dt><dd class="text-right font-medium text-[#34483F]">{{ $order['assigned_area'] ?? $order['area'] }}</dd></div>
                                            @endif
                                            @if(!empty($order['cancel_reason']))
                                                <div><dt class="text-[#728078]">Cancellation Reason</dt><dd class="mt-1 text-[#34483F]">{{ $order['cancel_reason'] }}</dd></div>
                                            @endif
                                            @if(!empty($order['delivery_failure_reason']))
                                                <div><dt class="text-[#728078]">Delivery Failure Reason</dt><dd class="mt-1 text-[#34483F]">{{ $order['delivery_failure_reason'] }}</dd></div>
                                            @endif
                                        </dl>
                                    </section>
                                </aside>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#E1E8E4] bg-white px-4 py-3 sm:px-5">
                                <p class="text-[10px] text-[#87958E]">Only information recorded for this order is shown.</p>
                                <div class="flex gap-2">
                                    <button type="button" data-close-dialog class="h-9 rounded-lg border border-[#DDE6E1] px-3 text-[11px] font-semibold text-[#52635B] transition hover:bg-[#F3F7F5]">Close</button>
                                    @if($nextStatus)
                                        <form action="{{ route('order.status.update', $orderId) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $nextStatus }}">
                                            <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#173F35] px-3 text-[11px] font-semibold text-white transition hover:bg-[#1F6F5B]">{{ $nextActionLabel }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </dialog>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div id="noOrdersFound" class="hidden px-6 py-12 text-center">
            <i data-lucide="search-x" class="mx-auto h-6 w-6 text-[#91A099]"></i>
            <p class="mt-3 text-sm font-semibold text-[#34483F]">No matching orders</p>
            <p class="mt-1 text-xs text-[#87958E]">Try another search or status filter.</p>
        </div>
    @else
        <div class="px-6 py-14 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EEF5F1] text-[#1F6F5B]"><i data-lucide="shopping-bag" class="h-5 w-5"></i></span>
            <p class="mt-4 text-sm font-semibold text-[#34483F]">No orders yet</p>
            <p class="mt-1 text-xs text-[#87958E]">Customer orders will appear here when they’re placed.</p>
        </div>
    @endif
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('orderSearch');
    const statusFilter = document.getElementById('statusFilter');
    const clearButton = document.getElementById('clearOrderFilters');
    const rows = Array.from(document.querySelectorAll('.order-row'));
    const noResults = document.getElementById('noOrdersFound');

    function filterOrders() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        const status = statusFilter?.value || 'all';
        let visibleCount = 0;

        rows.forEach(function (row) {
            const matchesQuery = (row.dataset.search || '').includes(query);
            const matchesStatus = status === 'all' || row.dataset.status === status;
            const isVisible = matchesQuery && matchesStatus;

            row.classList.toggle('hidden', !isVisible);
            visibleCount += isVisible ? 1 : 0;
        });

        noResults?.classList.toggle('hidden', visibleCount > 0);
    }

    searchInput?.addEventListener('input', filterOrders);
    statusFilter?.addEventListener('change', filterOrders);
    clearButton?.addEventListener('click', function () {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = 'all';
        filterOrders();
    });

    document.querySelectorAll('[data-order-dialog]').forEach(function (button) {
        button.addEventListener('click', function () {
            const dialog = document.getElementById(button.dataset.orderDialog);
            if (dialog instanceof HTMLDialogElement) dialog.showModal();
        });
    });

    document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
        button.addEventListener('click', function () {
            const dialog = button.closest('dialog');
            if (dialog instanceof HTMLDialogElement) dialog.close();
        });
    });

    document.querySelectorAll('.order-details-dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) dialog.close();
        });
    });

    if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
        lucide.createIcons();
    }
});
</script>
@endpush
@endsection
