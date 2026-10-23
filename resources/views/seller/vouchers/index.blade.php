@extends('layouts.seller')
@section('title', 'Vouchers')
@section('page-title', 'Voucher Management')
@section('content')
@php
    $filterId = 'vouchers-advanced-filters';
    $filtersOpen = count($filterChips) > 0 || $errors->any();
@endphp
<div data-seller-filters>
    <nav aria-label="Breadcrumb" class="mb-3 text-xs text-[#728078]"><a href="{{ route('seller.dashboard') }}">Dashboard</a> / Vouchers</nav>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl font-semibold text-[#24312C]">Voucher Management</h2><div class="flex gap-3">@include('seller.partials.filter-trigger')<a href="{{ route('seller.vouchers.create') }}" class="rounded-xl bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Create Voucher</a></div></div>
    @include('seller.vouchers.notice')
    <section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
        @include('seller.partials.advanced-filter-panel', ['filterForm' => 'seller.vouchers.filters'])
        @include('seller.partials.active-filter-chips')
        <p class="border-b border-[#EDF1EF] px-5 py-3 text-xs text-[#728078]">@if($paginator->count())Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} matching vouchers @else {{ $paginator->total() }} matching vouchers. No vouchers on this page. @endif</p>
        <div class="overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-xs text-[#52635B]">
            <thead class="bg-[#F6F8F7] text-[10px] uppercase"><tr>@foreach(['Voucher / Code', 'Discount', 'Minimum Spend', 'Schedule (PHT)', 'Recorded Usage / Limits', 'Availability', 'Actions'] as $heading)<th scope="col" class="p-4">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-[#EDF1EF]">@forelse($paginator as $voucher)<tr class="align-top">
                <td class="p-4"><p class="font-semibold">{{ $voucher->name }}</p><p class="mt-1 font-mono">{{ $voucher->code }}</p></td>
                <td class="p-4">{{ $voucher->discount_type === 'percentage' ? number_format($voucher->discount_bps / 100, 2).'%' : '₱'.number_format($voucher->discount_minor / 100, 2) }}@if($voucher->cap_minor !== null)<p>Cap ₱{{ number_format($voucher->cap_minor / 100, 2) }}</p>@endif</td>
                <td class="p-4">₱{{ number_format($voucher->minimum_minor / 100, 2) }}</td>
                <td class="p-4">{{ $voucher->starts_at->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}<br>to {{ $voucher->ends_at->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                <td class="p-4">{{ $voucher->used_count }} recorded / {{ $voucher->total_limit ?? 'No total limit' }}<p>{{ $voucher->per_buyer_limit }} per buyer</p><p class="mt-1 text-[10px]">Redemption not connected</p></td>
                <td class="p-4"><span class="rounded-full bg-[#EEF5F1] px-2 py-1 text-[#1F6F5B]">{{ \App\Support\SellerVouchers::STATUSES[$voucher->availability] }}</span></td>
                <td class="p-4">@include('seller.vouchers.actions')</td>
            </tr>@empty<tr><td colspan="7" class="p-10 text-center">No matching vouchers. Create a voucher or reset your filters.</td></tr>@endforelse</tbody>
        </table></div>
        <div class="border-t border-[#EDF1EF] p-4">{{ $paginator->links() }}</div>
    </section>
</div>
@endsection
