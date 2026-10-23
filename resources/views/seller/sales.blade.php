@extends('layouts.seller')

@section('title', 'Sales')
@section('page-title', 'Sales')

@section('content')

@php
    $filterChips = \App\Support\SellerFilterChips::transactions(request()->query(), $products, $mainCategory, $filterCategories, $paymentMethods, route('seller.sales'), true);
    $filtersOpen = count($filterChips) > 0 || $errors->any();
    $filterId = 'sales-advanced-filters';
@endphp
<div data-seller-filters>

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
            Track saved gross sales and pending order item values. Payment settlement and payouts are not verified here.
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
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Gross Completed Sales</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            ₱{{ number_format($grossCompleted, 2) }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            {{ $completedCount }} completed order(s)
        </p>
    </div>

    <div class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Pending Item Value</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            ₱{{ number_format($pendingValue, 2) }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            Gross matching items still in fulfillment
        </p>
    </div>

    <div class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Average Completed Order Value</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            {{ $averageCompleted === null ? 'Unavailable' : '₱'.number_format($averageCompleted, 2) }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            Matching gross completed items per order
        </p>
    </div>

    <div class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <p class="text-[9px] font-semibold uppercase tracking-[0.12em] text-[#839189]">Cancelled / Failed</p>
        <p class="mt-3 text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">
            {{ $cancelledCount }}
        </p>
        <p class="mt-5 border-t border-[#EEF2F0] pt-3 text-[10px] text-[#7B8982]">
            Cancelled, returned, or failed delivery
        </p>
    </div>
</div>

@include('seller.partials.sales-transactions')
</div>
@endsection
