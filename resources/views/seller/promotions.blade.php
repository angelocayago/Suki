@extends('layouts.seller')

@section('title', 'Promotions')
@section('page-title', 'Promotions')

@section('content')

@php
    $promotions = $promotions ?? session('seller_promotions', []);
    $products = $products ?? session('seller_products', []);
@endphp

@if(session('success'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white">
            <i data-lucide="check" class="h-4 w-4 text-emerald-600"></i>
        </div>
        <div>
            <p class="text-xs font-semibold text-emerald-800">Promotion updated</p>
            <p class="mt-0.5 text-xs leading-5 text-emerald-700">{{ session('success') }}</p>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4">
        <p class="text-xs font-semibold text-red-800">Please review the promotion.</p>
        <ul class="mt-2 space-y-1 text-xs leading-5 text-red-700">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-7">
    <div class="mb-2 flex items-center gap-2 text-[11px] font-medium text-[#8A9791]">
        <a href="{{ route('seller.dashboard') }}" class="transition hover:text-[#1F6F5B]">Dashboard</a>
        <i data-lucide="chevron-right" class="h-3 w-3"></i>
        <span class="text-[#52635B]">Promotions</span>
    </div>

    <h2 class="text-2xl font-semibold tracking-[-0.04em] text-[#24312C] sm:text-[28px]">Promotions</h2>
    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-[#728078]">
        Create and manage discounts for your store products.
    </p>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="flex items-center justify-between gap-4 border-b border-[#EDF1EF] px-5 py-4">
            <div>
                <h3 class="text-sm font-semibold text-[#24312C]">Your Promotions</h3>
                <p class="mt-0.5 text-[11px] text-[#7B8982]">Manage the discounts currently configured for your store.</p>
            </div>
            <span class="rounded-full bg-[#EEF5F1] px-3 py-1 text-[10px] font-semibold text-[#1F6F5B]">
                {{ count($promotions) }} promotion(s)
            </span>
        </div>

        @if(empty($promotions))
            <div class="px-5 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EEF5F1] text-[#1F6F5B]">
                    <i data-lucide="badge-percent" class="h-5 w-5"></i>
                </div>
                <h4 class="mt-4 text-sm font-semibold text-[#24312C]">No promotions yet</h4>
                <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-[#7B8982]">
                    Create your first discount using the form on the right.
                </p>
            </div>
        @else
            <div class="divide-y divide-[#EEF2F0]">
                @foreach($promotions as $promotion)
                    @php
                        $productName = 'All products';
                        if (!empty($promotion['product_id']) && isset($products[$promotion['product_id']])) {
                            $productName = $products[$promotion['product_id']]['name'] ?? 'Selected product';
                        }
                    @endphp
                    <div class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-semibold text-[#24312C]">{{ $promotion['name'] }}</h4>
                                <span class="rounded-full px-2.5 py-1 text-[9px] font-semibold {{ ($promotion['status'] ?? 'inactive') === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($promotion['status'] ?? 'inactive') }}
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-[#7B8982]">
                                {{ $productName }} · {{ $promotion['start_date'] }} to {{ $promotion['end_date'] }}
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-lg font-semibold text-[#173F35]">
                                    {{ ($promotion['type'] ?? 'percentage') === 'percentage' ? number_format((float) $promotion['value'], 0) . '%' : '₱' . number_format((float) $promotion['value'], 2) }}
                                </p>
                                <p class="text-[9px] uppercase tracking-[0.08em] text-[#8A9791]">Discount</p>
                            </div>

                            <form action="{{ route('seller.promotions.destroy', $promotion['id']) }}" method="POST" onsubmit="return confirm('Delete this promotion?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-xl border border-[#E1E8E4] text-[#7B8982] transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" title="Delete promotion">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="h-fit overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
        <div class="border-b border-[#EDF1EF] px-5 py-4">
            <h3 class="text-sm font-semibold text-[#24312C]">Create Promotion</h3>
            <p class="mt-0.5 text-[11px] text-[#7B8982]">Set the discount, dates, and product coverage.</p>
        </div>

        <form action="{{ route('seller.promotions.store') }}" method="POST" class="space-y-4 p-5">
            @csrf

            <div>
                <label class="mb-1.5 block text-[11px] font-semibold text-[#52635B]">Promotion Name</label>
                <input name="name" value="{{ old('name') }}" required placeholder="Enter promotion name" class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs text-[#24312C] outline-none transition focus:border-[#1F6F5B]">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-[#52635B]">Discount Type</label>
                    <select name="type" class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs text-[#24312C] outline-none focus:border-[#1F6F5B]">
                        <option value="percentage" @selected(old('type', 'percentage') === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(old('type') === 'fixed')>Fixed amount</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-[#52635B]">Value</label>
                    <input name="value" type="number" step="0.01" min="0.01" value="{{ old('value') }}" required placeholder="Enter discount value" class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs text-[#24312C] outline-none focus:border-[#1F6F5B]">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-[11px] font-semibold text-[#52635B]">Apply To</label>
                <select name="product_id" class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs text-[#24312C] outline-none focus:border-[#1F6F5B]">
                    <option value="">All products</option>
                    @foreach($products as $product)
                        <option value="{{ $product['id'] }}" @selected(old('product_id') === ($product['id'] ?? ''))>
                            {{ $product['name'] ?? 'Unnamed product' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-[#52635B]">Start Date</label>
                    <input name="start_date" type="date" value="{{ old('start_date') }}" required class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs text-[#24312C] outline-none focus:border-[#1F6F5B]">
                </div>
                <div>
                    <label class="mb-1.5 block text-[11px] font-semibold text-[#52635B]">End Date</label>
                    <input name="end_date" type="date" value="{{ old('end_date') }}" required class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs text-[#24312C] outline-none focus:border-[#1F6F5B]">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-[11px] font-semibold text-[#52635B]">Status</label>
                <select name="status" class="h-10 w-full rounded-xl border border-[#DDE6E1] bg-white px-3 text-xs text-[#24312C] outline-none focus:border-[#1F6F5B]">
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                </select>
            </div>

            <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-[#173F35] px-4 text-xs font-semibold text-white transition hover:bg-[#1F6F5B]">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Create Promotion
            </button>
        </form>
    </section>
</div>

@endsection
