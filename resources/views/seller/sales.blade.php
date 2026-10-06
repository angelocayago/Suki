@extends('layouts.seller')

@section('title', 'Sales')
@section('page-title', 'Sales')

@section('content')

@php
    $orders = collect($orders ?? session('orders', []));

    $completedStatuses = ['delivered', 'completed'];

    $completedOrders = $orders->filter(function ($order) use ($completedStatuses) {
        return in_array($order['status'] ?? '', $completedStatuses, true);
    });

    $completedSales = $completedOrders->sum(function ($order) {
        return (float) ($order['total'] ?? $order['grand_total'] ?? 0);
    });

    $pendingSales = $orders
        ->reject(function ($order) use ($completedStatuses) {
            return in_array($order['status'] ?? '', $completedStatuses, true)
                || in_array($order['status'] ?? '', ['cancelled', 'returned', 'delivery_failed'], true);
        })
        ->sum(function ($order) {
            return (float) ($order['total'] ?? $order['grand_total'] ?? 0);
        });

    $cancelledOrders = $orders->filter(function ($order) {
        return in_array($order['status'] ?? '', ['cancelled', 'returned', 'delivery_failed'], true);
    });

    $averageOrderValue = $completedOrders->count() > 0
        ? $completedSales / $completedOrders->count()
        : 0;

    $recentSales = $completedOrders
        ->sortByDesc(function ($order) {
            return $order['created_at'] ?? '';
        })
        ->take(10);
@endphp

<div class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <div class="mb-2 flex items-center gap-2 text-[11px] font-medium text-[#8A9791]">
            <a href="{{ route('seller.dashboard') }}" class="transition hover:text-[#1F6F5B]">
                Dashboard
            </a>
            <i data-lucide="chevron-right" class="h-3 w-3"></i>
            <span class="text-[#52635B]">Sales</span>
        </div>

        <h2 class="text-2xl font-semibold tracking-[-0.04em] text-[#24312C] sm:text-[28px]">
            Sales
        </h2>

        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-[#728078]">
            Track completed sales, pending revenue, and recent transactions.
        </p>
    </div>

    <a
        href="{{ route('seller.reports') }}"
        class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-xl border border-[#DDE6E1] bg-white px-4 text-xs font-semibold text-[#52635B] transition hover:border-[#BFD2C9] hover:bg-[#F5F8F6] hover:text-[#173F35] lg:self-auto"
    >
        <i data-lucide="chart-no-axes-combined" class="h-4 w-4"></i>
        View Reports
    </a>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Completed Sales</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            ₱{{ number_format($completedSales, 2) }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            {{ $completedOrders->count() }} completed order(s)
        </p>
    </div>

    <div class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Pending Sales</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            ₱{{ number_format($pendingSales, 2) }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            Orders still in fulfillment
        </p>
    </div>

    <div class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Average Order Value</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            ₱{{ number_format($averageOrderValue, 2) }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            Based on completed orders
        </p>
    </div>

    <div class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Cancelled / Failed</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            {{ $cancelledOrders->count() }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            Cancelled, returned, or failed delivery
        </p>
    </div>
</div>

<section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
    <div class="flex items-center justify-between gap-4 border-b border-[#EDF1EF] px-5 py-4">
        <div>
            <h3 class="text-sm font-semibold text-[#24312C]">Recent Sales</h3>
            <p class="mt-0.5 text-[11px] text-[#7B8982]">Your latest completed transactions.</p>
        </div>
        <span class="rounded-full bg-[#EEF5F1] px-3 py-1 text-[10px] font-semibold text-[#1F6F5B]">
            {{ $completedOrders->count() }} completed
        </span>
    </div>

    @if($recentSales->isEmpty())
        <div class="px-5 py-14 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EEF5F1] text-[#1F6F5B]">
                <i data-lucide="receipt-text" class="h-5 w-5"></i>
            </div>
            <h4 class="mt-4 text-sm font-semibold text-[#24312C]">No completed sales yet</h4>
            <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-[#7B8982]">
                Completed orders will appear here once customers finish their purchases.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left">
                <thead class="border-b border-[#EDF1EF] bg-[#FAFCFB]">
                    <tr class="text-[10px] font-semibold uppercase tracking-[0.08em] text-[#839189]">
                        <th class="px-5 py-3">Order</th>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Items</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EEF2F0]">
                    @foreach($recentSales as $order)
                        @php
                            $status = $order['status'] ?? 'completed';
                            $statusLabel = $status === 'delivered' ? 'Delivered' : 'Completed';
                            $orderNumber = $order['order_number'] ?? $order['id'] ?? 'Order';
                            $itemsCount = collect($order['items'] ?? [])->sum(function ($item) {
                                return (int) ($item['quantity'] ?? 0);
                            });
                        @endphp
                        <tr class="text-xs text-[#52635B]">
                            <td class="px-5 py-4 font-semibold text-[#24312C]">
                                {{ $orderNumber }}
                            </td>
                            <td class="px-5 py-4">
                                {{ !empty($order['created_at']) ? \Illuminate\Support\Carbon::parse($order['created_at'])->format('M d, Y') : '—' }}
                            </td>
                            <td class="px-5 py-4">{{ $itemsCount }} item(s)</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-[#24312C]">
                                ₱{{ number_format((float) ($order['total'] ?? $order['grand_total'] ?? 0), 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@endsection
