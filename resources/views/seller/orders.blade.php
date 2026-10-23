@extends('layouts.seller')
@section('title', 'Orders')
@section('page-title', 'Orders')
@section('content')
@php
    $filterId = 'orders-advanced-filters';
    $filtersOpen = count($filterChips) > 0 || $errors->any();
    $detailList = \Illuminate\Support\Arr::only($criteria, \App\Support\SellerOrders::LIST_KEYS);
    $detailList['page'] = $paginator->currentPage();
@endphp
<div data-seller-filters>
    <section class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#1F6F5B]">Seller Centre</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-[-0.04em] text-[#24312C] sm:text-[28px]">Orders</h2>
            <p class="mt-1 text-sm text-[#728078]">Review saved seller items and fulfillment status.</p>
        </div>
        @include('seller.partials.filter-trigger')
    </section>
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        @foreach(['total' => 'Total Matching Orders', 'pending' => 'Pending / Placed', 'processing' => 'Processing / Preparing', 'ready' => 'Ready', 'completed' => 'Completed / Delivered', 'cancelled' => 'Cancelled / Failed'] as $key => $label)
            <div class="rounded-2xl border border-[#E1E8E4] bg-white p-4">
                <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">{{ $label }}</p>
                <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">{{ number_format($summary->$key) }}</p>
                <p class="mt-4 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">All matching seller segments</p>
            </div>
        @endforeach
    </div>
    <section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white shadow-[0_1px_3px_rgba(23,63,53,0.04)]">
        <div class="border-b border-[#EDF1EF] px-5 py-4">
            <h3 class="text-sm font-semibold text-[#24312C]">Customer Orders</h3>
            <p class="mt-1 text-xs leading-5 text-[#728078]">Only orders securely linked to your seller account appear here. Preview is read-only; order processing actions await fulfillment integration.</p>
        </div>
        @include('seller.partials.advanced-filter-panel', ['filterForm' => 'seller.partials.orders-filters'])
        @include('seller.partials.active-filter-chips')
        <p class="border-b border-[#EDF1EF] px-5 py-3 text-xs text-[#68776F]" aria-live="polite">
            @if($paginator->count()) Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} matching orders
            @else {{ $paginator->total() }} matching orders. No orders on this page. @endif
        </p>
        @if($paginator->count())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-xs">
                    <thead class="bg-[#F6F8F7] text-[9px] uppercase tracking-wider text-[#728078]"><tr>
                        @foreach(['Order Reference', 'Date Placed', 'Buyer', 'Seller Items', 'Seller Item Gross', 'Fulfillment Status', 'Payment Method', 'Action'] as $heading)<th scope="col" class="px-4 py-3 font-semibold">{{ $heading }}</th>@endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-[#EDF1EF]">
                    @foreach($paginator as $segment)
                        @php
                            $date = $segment->transaction_at ? \Carbon\CarbonImmutable::parse($segment->transaction_at, config('app.timezone'))->setTimezone(config('seller_inventory.business_timezone')) : null;
                            $statusLabel = $statuses[$segment->effective_status] ?? 'Not recorded';
                        @endphp
                        <tr class="align-top text-[#52635B] hover:bg-[#FAFCFB]">
                            <td class="px-4 py-4"><span class="font-semibold text-[#24312C]">{{ $segment->display_reference ?: 'Not recorded' }}</span>@if($segment->seller_order_id)<span class="mt-1 block text-[10px] text-[#728078]">Seller segment #{{ $segment->seller_order_id }}</span>@endif</td>
                            <td class="whitespace-nowrap px-4 py-4">@if($date)<time datetime="{{ $date->toIso8601String() }}">{{ $date->format('M j, Y') }}<span class="block text-[10px]">{{ $date->format('g:i A') }} PHT</span></time>@else Not recorded @endif</td>
                            <td class="px-4 py-4">{{ $segment->recipient_name ?: 'Not recorded' }}</td>
                            <td class="px-4 py-4">{{ $segment->item_count }} line(s) · {{ $segment->units }} unit(s)<ul class="mt-1 max-w-48 space-y-1 break-words text-[10px]">@foreach($segment->items as $item)<li>{{ $item->product_name ?: 'Not recorded' }}</li>@endforeach</ul></td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-[#173F35]">₱{{ number_format($segment->gross_amount, 2) }}</td>
                            <td class="px-4 py-4"><span class="inline-block rounded-full border border-[#DDE6E1] bg-[#EEF5F1] px-2 py-1 text-[10px] text-[#1F6F5B]">{{ $statusLabel }}</span><span class="mt-1 block text-[10px]">{{ $segment->seller_order_id ? 'Seller fulfillment' : 'Order fulfillment' }}</span></td>
                            <td class="px-4 py-4">{{ $segment->payment_method ?: 'Not recorded' }}</td>
                            <td class="px-4 py-4">
                                <a href="{{ route('seller.orders.show', ['order' => $segment->order_id, 'list' => $detailList]) }}" class="mb-2 inline-flex rounded-lg bg-[#1F6F5B] px-3 py-2 font-semibold text-white hover:bg-[#173F35] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">View Details<span class="sr-only"> {{ $segment->display_reference }}</span></a>
                                <details class="min-w-40">
                                    <summary class="cursor-pointer rounded-lg border border-[#DDE6E1] px-3 py-2 font-semibold text-[#1F6F5B] focus-visible:outline-2 focus-visible:outline-[#1F6F5B]">Preview <span class="sr-only">{{ $segment->display_reference }}</span></summary>
                                    @include('seller.partials.order-preview')
                                </details>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-5 py-12 text-center">
                <i data-lucide="package-search" class="mx-auto h-7 w-7 text-[#91A099]"></i>
                <h4 class="mt-3 text-sm font-semibold text-[#24312C]">No attributable seller orders found.</h4>
                <p class="mt-2 text-xs text-[#728078]">Orders appear when their saved records are linked to your seller account and match your filters.</p>
                <a href="{{ route('seller.orders') }}" class="mt-4 inline-block text-xs font-semibold text-[#1F6F5B]">Reset Filters</a>
            </div>
        @endif
        <div class="border-t border-[#EDF1EF] px-5 py-4">{{ $paginator->links() }}</div>
        <p class="border-t border-[#EDF1EF] px-5 py-3 text-[10px] leading-5 text-[#728078]">Counts cover all matching seller segments across pages. Pending: pending/placed. Processing: confirmed/accepted/preparing/packed. Ready: ready for pickup/ready to ship. Completed: delivered/completed. Cancelled / Failed includes returned orders. Other saved statuses remain visible.</p>
    </section>
</div>
@endsection
