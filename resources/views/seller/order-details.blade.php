@extends('layouts.seller')
@section('title', 'Order Details')
@section('page-title', 'Order Details')
@section('content')
@php
    $date = $segment->transaction_at ? \Carbon\CarbonImmutable::parse($segment->transaction_at, config('app.timezone'))->setTimezone(config('seller_inventory.business_timezone')) : null;
    $fulfillment = $segment->effective_status !== null && $segment->effective_status !== '' ? ucwords(str_replace('_', ' ', $segment->effective_status)) : 'Not recorded';
@endphp
<nav aria-label="Breadcrumb" class="mb-3 flex flex-wrap items-center gap-2 text-xs text-[#728078]">
    <a href="{{ route('seller.dashboard') }}" class="hover:text-[#1F6F5B]">Dashboard</a><span aria-hidden="true">/</span>
    <a href="{{ $backUrl }}" class="hover:text-[#1F6F5B]">Orders</a><span aria-hidden="true">/</span><span aria-current="page">Order Details</span>
</nav>
<header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <h2 class="break-words text-2xl font-semibold tracking-[-0.04em] text-[#24312C]">{{ $segment->display_reference ?? 'Not recorded' }}</h2>
        <p class="mt-2 text-xs text-[#728078]">Date Placed: @if($date)<time datetime="{{ $date->toIso8601String() }}">{{ $date->format('M j, Y · g:i A') }} PHT</time>@else Not recorded @endif</p>
        <span class="mt-3 inline-block rounded-full border border-[#DDE6E1] bg-[#EEF5F1] px-3 py-1 text-xs text-[#1F6F5B]">{{ $fulfillment }}</span>
    </div>
    <a href="{{ $backUrl }}" class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-xl border border-[#DDE6E1] bg-white px-4 py-2.5 text-xs font-semibold text-[#1F6F5B] hover:bg-[#EEF5F1] focus-visible:outline-2 focus-visible:outline-[#1F6F5B]"><i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>Back to Orders</a>
</header>
<div class="grid min-w-0 gap-5 lg:grid-cols-3">
    <div class="min-w-0 space-y-5 lg:col-span-2">
        <section class="rounded-2xl border border-[#E1E8E4] bg-white p-5" aria-labelledby="order-overview-heading">
            <h3 id="order-overview-heading" class="text-sm font-semibold text-[#24312C]">Order Overview</h3>
            <dl class="mt-4 grid gap-4 text-xs sm:grid-cols-2">
                @foreach(['Order number' => $segment->order_number, 'Seller segment' => $segment->seller_order_id !== null ? '#'.$segment->seller_order_id : 'Legacy order', 'Seller-owned item lines' => $segment->item_count, 'Seller-owned units' => $segment->units] as $label => $value)
                    <div class="min-w-0"><dt class="text-[#728078]">{{ $label }}</dt><dd class="mt-1 break-words font-medium text-[#24312C]">{{ $value ?? 'Not recorded' }}</dd></div>
                @endforeach
            </dl>
        </section>
        <section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white" aria-labelledby="purchased-products-heading">
            <h3 id="purchased-products-heading" class="border-b border-[#EDF1EF] p-5 text-sm font-semibold text-[#24312C]">Purchased Products</h3>
            <ul class="divide-y divide-[#EDF1EF]">
                @foreach($purchasedItems as $item)
                    <li data-purchased-item="{{ $item->id }}" class="flex min-w-0 gap-3 p-4 sm:p-5">
                        @if($item->image_url)
                            <img src="{{ $item->image_url }}" alt="{{ $item->name ?? 'Purchased product' }}" loading="lazy" class="h-16 w-16 shrink-0 rounded-xl border border-[#EDF1EF] object-cover">
                        @else
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-[#F3F7F5] text-[#728078]" role="img" aria-label="Image unavailable"><i data-lucide="image" class="h-6 w-6" aria-hidden="true"></i></div>
                        @endif
                        <div class="min-w-0 flex-1 break-words text-xs text-[#52635B]">
                            <h4 class="font-semibold text-[#24312C]">{{ $item->name !== null && $item->name !== '' ? $item->name : 'Not recorded' }}</h4>
                            @if($item->variant_name)<p class="mt-1">{{ $item->variant_name }}</p>@endif
                            @if(is_array($item->options))
                                @foreach(\Illuminate\Support\Arr::dot($item->options) as $option => $value)<p class="mt-1">{{ $option }}: {{ is_scalar($value) ? $value : '' }}</p>@endforeach
                            @elseif($item->options)<p class="mt-1">{{ $item->options }}</p>@endif
                            <p class="mt-1 text-[10px] text-[#728078]">SKU at purchase: Not recorded</p>
                            <dl class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <div><dt class="text-[#728078]">Quantity</dt><dd>{{ $item->quantity }}</dd></div>
                                <div><dt class="text-[#728078]">Saved unit price</dt><dd>₱{{ number_format($item->unit_price, 2) }}</dd></div>
                                <div><dt class="text-[#728078]">Gross line amount</dt><dd class="font-semibold text-[#173F35]">₱{{ number_format($item->gross_amount, 2) }}</dd></div>
                            </dl>
                            @if(abs((float)$item->recorded_line_total - (float)$item->gross_amount) > 0.005)<p class="mt-2 text-[10px] text-[#728078]">Recorded line total: ₱{{ number_format($item->recorded_line_total, 2) }}. Gross uses the saved unit price × quantity.</p>@endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
    <aside class="min-w-0 space-y-5">
        <section class="rounded-2xl border border-[#E1E8E4] bg-white p-5" aria-labelledby="seller-gross-heading">
            <h3 id="seller-gross-heading" class="text-sm font-semibold text-[#24312C]">Seller Item Gross</h3>
            <p class="mt-3 text-2xl font-semibold text-[#173F35]">₱{{ number_format($segment->gross_amount, 2) }}</p>
            <p class="mt-3 text-xs leading-5 text-[#728078]">Gross value of saved items attributed to your shop. Shipping, unallocated discounts, commissions and refunds are excluded. This does not establish payment settlement or a payout.</p>
        </section>
        <section class="rounded-2xl border border-[#E1E8E4] bg-white p-5" aria-labelledby="fulfillment-heading">
            <h3 id="fulfillment-heading" class="text-sm font-semibold text-[#24312C]">Fulfillment Status</h3>
            <p class="mt-3 break-words text-sm text-[#52635B]">{{ $fulfillment }}</p>
            <p class="mt-1 text-xs text-[#728078]">Source: {{ $segment->seller_order_id !== null ? 'Seller order' : 'Parent order' }}</p>
            @if($segment->seller_order_id !== null)<p class="mt-3 break-words text-xs text-[#52635B]">Parent order status: {{ $segment->parent_status !== '' ? $segment->parent_status : 'Not recorded' }}</p>@endif
            <p class="mt-3 text-xs leading-5 text-[#728078]">Saved status only. Order processing actions and a verified fulfillment timeline are not available here.</p>
        </section>
        <section class="rounded-2xl border border-[#E1E8E4] bg-white p-5" aria-labelledby="payment-heading">
            <h3 id="payment-heading" class="text-sm font-semibold text-[#24312C]">Payment Information</h3>
            <dl class="mt-3 space-y-3 text-xs text-[#52635B]">
                <div><dt class="text-[#728078]">Saved payment method</dt><dd class="mt-1 break-words">{{ $segment->payment_method !== null && $segment->payment_method !== '' ? $segment->payment_method : 'Not recorded' }}</dd></div>
                <div><dt class="text-[#728078]">Recorded parent-order payment status</dt><dd class="mt-1 break-words">{{ $segment->payment_status !== null && $segment->payment_status !== '' ? $segment->payment_status : 'Not recorded' }}</dd></div>
            </dl>
            <p class="mt-3 text-xs leading-5 text-[#728078]">These order fields do not verify seller payment receipt. Delivered/completed fulfillment does not confirm collection, COD remittance or settlement.</p>
        </section>
        <section class="rounded-2xl border border-[#E1E8E4] bg-white p-5" aria-labelledby="recipient-heading">
            <h3 id="recipient-heading" class="text-sm font-semibold text-[#24312C]">Recipient</h3>
            <p class="mt-3 break-words text-xs text-[#52635B]">{{ $segment->recipient_name !== null && $segment->recipient_name !== '' ? $segment->recipient_name : 'Not recorded' }}</p>
            <p class="mt-3 text-xs leading-5 text-[#728078]">Contact details, delivery addresses and instructions are withheld while seller fulfillment actions are unavailable.</p>
        </section>
    </aside>
</div>
@endsection
