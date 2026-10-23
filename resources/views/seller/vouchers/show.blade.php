@extends('layouts.seller')
@section('title', 'Voucher Details')
@section('page-title', 'Voucher Management')
@section('content')
<a href="{{ route('seller.vouchers.index') }}" class="text-xs font-semibold text-[#1F6F5B]">← Back to Vouchers</a>
<h2 class="mb-2 mt-3 text-2xl font-semibold text-[#24312C]">{{ $voucher->name }}</h2><p class="mb-5 font-mono text-[#1F6F5B]">{{ $voucher->code }}</p>
@include('seller.vouchers.notice')
<div class="grid gap-5 lg:grid-cols-2">
    <section class="space-y-4 rounded-2xl border border-[#E1E8E4] bg-white p-5 text-sm text-[#52635B]">
        <h3 class="font-semibold text-[#24312C]">Saved Voucher Terms</h3>
        <p>{{ $voucher->description ?? 'No description' }}</p><p>Availability: {{ \App\Support\SellerVouchers::STATUSES[$availability] }}</p><p>Saved configuration: {{ ucfirst($voucher->status) }}</p>
        <p>Discount: {{ $voucher->discount_type === 'percentage' ? number_format($voucher->discount_bps / 100, 2).'%' : '₱'.number_format($voucher->discount_minor / 100, 2) }}</p>
        <p>Minimum eligible seller subtotal: ₱{{ number_format($voucher->minimum_minor / 100, 2) }}</p><p>Percentage cap: {{ $voucher->cap_minor === null ? 'None' : '₱'.number_format($voucher->cap_minor / 100, 2) }}</p>
        <p>Starts: {{ $voucher->starts_at->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} PHT<br>Ends (exclusive): {{ $voucher->ends_at->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</p>
        <p>Usage: {{ $voucher->used_count }} recorded / {{ $voucher->total_limit ?? 'No total limit' }}; {{ $voucher->per_buyer_limit }} per buyer.</p>
        <p>Eligible products: {{ $voucher->product_scope === 'all' ? 'All eligible products in your shop' : 'Selected products' }}</p>
        @if($voucher->product_scope === 'selected')<ul class="list-inside list-disc">@foreach($voucher->products as $product)<li>{{ $product->name }}@if(!$product->is_active || $product->trashed()) — unavailable @endif</li>@endforeach</ul>@endif
        @include('seller.vouchers.actions')
    </section>
    <section class="rounded-2xl border border-[#E1E8E4] bg-white p-5">
        <h3 class="text-sm font-semibold text-[#24312C]">Illustrative Discount Estimate</h3><p class="my-3 text-xs leading-5 text-[#728078]">Enter an eligible seller-owned product subtotal. This arithmetic example does not validate a cart, usage, stacking, availability or payment and is never applied to an order.</p>
        <form method="GET" action="{{ route('seller.vouchers.show', $voucher->id) }}"><label class="text-xs font-semibold">Eligible subtotal (PHP)<input type="number" min="0" max="42949672.95" step="0.01" name="eligible_subtotal" value="{{ number_format($subtotal / 100, 2, '.', '') }}" class="my-2 w-full rounded-lg border border-[#DDE6E1] p-2"></label><button class="rounded-xl border border-[#BFD2C9] px-4 py-2 text-xs font-semibold text-[#1F6F5B]">Calculate Estimate</button></form>
        <p class="mt-5 text-sm text-[#52635B]">Estimated voucher discount</p><p class="mt-2 text-2xl font-semibold text-[#173F35]">₱{{ number_format($estimate / 100, 2) }}</p><p class="mt-3 text-xs text-[#728078]">Uses saved percentage/fixed amount, minimum spend and percentage cap; never exceeds the eligible subtotal. Rounded half-up to the nearest cent.</p>
    </section>
</div>
@endsection
