@extends('layouts.seller')
@section('title', 'Voucher')
@section('page-title', 'Voucher Management')
@section('content')
<a href="{{ route('seller.vouchers.index') }}" class="text-xs font-semibold text-[#1F6F5B]">← Back to Vouchers</a>
<h2 class="mb-5 mt-3 text-2xl font-semibold text-[#24312C]">{{ $voucher->exists ? 'Edit Voucher' : 'Create Voucher' }}</h2>
@include('seller.vouchers.notice')
<form method="POST" action="{{ $voucher->exists ? route('seller.vouchers.update', $voucher->id) : route('seller.vouchers.store') }}" class="grid gap-4 rounded-2xl border border-[#E1E8E4] bg-white p-5 sm:grid-cols-2">
    @csrf @if($voucher->exists) @method('PUT') @endif
    <label class="text-xs font-semibold">Voucher name<input name="name" maxlength="100" required value="{{ old('name', $voucher->name) }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2"></label>
    <label class="text-xs font-semibold">Description (optional)<textarea name="description" maxlength="2000" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2">{{ old('description', $voucher->description) }}</textarea></label>
    @if($voucher->termsLocked())
        <p class="text-sm text-[#728078] sm:col-span-2">Recorded usage has locked this voucher's terms. Only its name and description can be edited; use Pause or Archive to withdraw the offer.</p>
    @else
        @php
            $inputs = ['code' => ['Voucher code (3–32 letters, digits, _ or -)', 'text', $voucher->code],
                'discount_value' => ['Discount value (% or PHP)', 'number', $voucher->discount_type === 'fixed' ? $voucher->discount_minor / 100 : ($voucher->discount_bps ?? 1000) / 100],
                'minimum_spend' => ['Minimum eligible seller subtotal (PHP)', 'number', ($voucher->minimum_minor ?? 0) / 100],
                'maximum_discount' => ['Percentage cap (PHP, optional; blank for fixed)', 'number', $voucher->cap_minor !== null ? $voucher->cap_minor / 100 : ''],
                'starts_at' => ['Starts (Asia/Manila)', 'datetime-local', $voucher->starts_at?->setTimezone('Asia/Manila')->format('Y-m-d\TH:i') ?? now('Asia/Manila')->format('Y-m-d\TH:i')],
                'ends_at' => ['Ends (exclusive, Asia/Manila)', 'datetime-local', $voucher->ends_at?->setTimezone('Asia/Manila')->format('Y-m-d\TH:i') ?? now('Asia/Manila')->addWeek()->format('Y-m-d\TH:i')],
                'total_limit' => ['Total usage limit (blank = no total limit)', 'number', $voucher->total_limit], 'per_buyer_limit' => ['Per-buyer usage limit', 'number', $voucher->per_buyer_limit ?? 1]];
        @endphp
        @foreach($inputs as $name => [$label, $type, $value])<label class="text-xs font-semibold">{{ $label }}<input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" @if($type === 'number') min="{{ in_array($name, ['total_limit','per_buyer_limit']) ? 1 : 0 }}" step="{{ in_array($name, ['total_limit','per_buyer_limit']) ? 1 : '0.01' }}" @endif @if($name === 'code' && $voucher->exists) readonly @endif class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2"></label>@endforeach
        @foreach(['discount_type' => ['Discount type', ['percentage' => 'Percentage', 'fixed' => 'Fixed Amount']], 'product_scope' => ['Eligible products', ['all' => 'All eligible Seller products', 'selected' => 'Selected Seller products']], 'status' => ['Configuration', ['paused' => 'Paused', 'active' => 'Active / Scheduled']]] as $name => [$label, $choices])
            <label class="text-xs font-semibold">{{ $label }}<select name="{{ $name }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2">@foreach($choices as $value => $text)<option value="{{ $value }}" @selected(old($name, $voucher->$name ?? array_key_first($choices)) === $value)>{{ $text }}</option>@endforeach</select></label>
        @endforeach
        <fieldset class="max-h-64 overflow-y-auto rounded-xl border border-[#DDE6E1] p-3 sm:col-span-2"><legend class="px-2 text-xs font-semibold">Specific products (used only for Selected Seller products)</legend>
            @forelse($products as $product)<label class="mb-2 flex gap-2 text-xs"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, is_array(old('product_ids', $selected)) ? old('product_ids', $selected) : []))><span>{{ $product->name }}@if(!$product->is_active || $product->trashed()) — unavailable @endif</span></label>@empty<p class="text-xs text-[#728078]">No eligible products. Add an active product and active variant before activating a voucher.</p>@endforelse
        </fieldset>
        <p class="text-xs leading-5 text-[#728078] sm:col-span-2">Codes are globally reserved, including archived offers, and cannot change after creation. Only active, non-deleted products with active variants can qualify. This is product-level eligibility; variant restrictions and sale-price stacking are deferred. Used voucher terms will be locked.</p>
    @endif
    <div class="flex gap-3 sm:col-span-2"><button class="rounded-xl bg-[#1F6F5B] px-5 py-2.5 text-sm font-semibold text-white">Save Voucher</button><a href="{{ route('seller.vouchers.index') }}" class="rounded-xl border border-[#DDE6E1] px-4 py-2.5 text-sm">Cancel</a></div>
</form>
@endsection
