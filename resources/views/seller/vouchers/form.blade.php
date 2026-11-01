@extends('layouts.seller')
@section('title', $voucher ? 'Edit Shop Voucher' : 'Create Shop Voucher')
@section('page-title', 'Vouchers')
@section('content')
@include('seller.vouchers.partials.style')
@php
    $editing = $voucher !== null;
    $type = old('discount_type', $voucher?->discount_type ?? 'FIXED');
    $value = $voucher ? \App\Support\SellerVouchers::decimal($voucher->discount_type === 'PERCENTAGE' ? $voucher->discount_bps : $voucher->discount_minor) : '';
@endphp
<div class="seller-vouchers sv-form-page">
    <a class="sv-back" href="{{ route('seller.vouchers.index') }}">&larr; Back to Vouchers</a>
    <header class="sv-heading"><div><h2>{{ $editing ? 'Edit Shop Voucher' : 'Create Shop Voucher' }}</h2></div></header>
    @include('seller.vouchers.partials.feedback')
    @if($voucher?->archived_at)<p class="sv-notice">This voucher is archived.</p>@endif
    <form id="shopVoucherForm" method="POST" action="{{ $editing ? route('seller.vouchers.update',$voucher) : route('seller.vouchers.store') }}">
        @csrf
        @if($editing) @method('PATCH') @else <input type="hidden" name="creation_token" value="{{ $creationToken }}"> @endif
        <section class="sv-card" aria-labelledby="svBasicHeading">
            <h3 id="svBasicHeading">Basic Information</h3>
            <label for="voucherCode">Voucher Code</label>
            <div class="sv-code-control"><input id="voucherCode" name="code" maxlength="64" value="{{ old('code',$voucher?->code) }}" placeholder="LEAVE EMPTY TO AUTO-GENERATE" @required($editing) aria-describedby="code-error voucherCodeStatus" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}"><button id="generateVoucherCode" type="button" data-url="{{ route('seller.vouchers.generate') }}">Generate Code</button></div>
            <p class="sv-error" id="code-error">@error('code'){{ $message }}@enderror</p>
            <p id="voucherCodeStatus" class="sv-note" role="status" aria-live="polite"></p>
        </section>
        <section class="sv-card" aria-labelledby="svDiscountHeading">
            <h3 id="svDiscountHeading">Discount Settings</h3>
            <div class="sv-grid">
                <div><label for="voucherDiscountType">Discount Type</label><select id="voucherDiscountType" name="discount_type" required><option value="FIXED" @selected($type === 'FIXED')>Fixed Amount (PHP)</option><option value="PERCENTAGE" @selected($type === 'PERCENTAGE')>Percentage (%)</option></select><p class="sv-error">@error('discount_type'){{ $message }}@enderror</p></div>
                <div><label for="voucherDiscountValue" id="voucherDiscountLabel">Discount Value {{ $type === 'PERCENTAGE' ? '(%)' : '(PHP)' }}</label><input id="voucherDiscountValue" type="number" name="discount_value" min="0.01" max="{{ $type === 'PERCENTAGE' ? '100' : '21474836.47' }}" step="0.01" required value="{{ old('discount_value',$value) }}" aria-describedby="discount_value-error" aria-invalid="{{ $errors->has('discount_value') ? 'true' : 'false' }}"><p class="sv-error" id="discount_value-error">@error('discount_value'){{ $message }}@enderror</p></div>
            </div>
        </section>
        <section class="sv-card" aria-labelledby="svUsageHeading">
            <h3 id="svUsageHeading">Usage &amp; Validity</h3>
            <label for="voucherUsageLimit">Total Quantity <span class="sv-muted">(leave empty for unlimited)</span></label><input id="voucherUsageLimit" name="usage_limit" type="number" min="1" max="2147483647" step="1" value="{{ old('usage_limit',$voucher?->usage_limit) }}"><p class="sv-error">@error('usage_limit'){{ $message }}@enderror</p>
            @if($editing)<p class="sv-note">{{ $voucher->used_count }} used</p>@endif
            <div class="sv-grid">
                <div><label for="voucherStartsAt">Start Date / Time <span class="sv-muted">(optional)</span></label><input id="voucherStartsAt" name="starts_at" type="datetime-local" step="1" value="{{ old('starts_at',$voucher?->starts_at?->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:s')) }}"><p class="sv-error">@error('starts_at'){{ $message }}@enderror</p></div>
                <div><label for="voucherExpiresAt">Expiration Date / Time <span class="sv-muted">(optional)</span></label><input id="voucherExpiresAt" name="expires_at" type="datetime-local" step="1" value="{{ old('expires_at',$voucher?->expires_at?->setTimezone('Asia/Manila')->format('Y-m-d\TH:i:s')) }}"><p class="sv-error">@error('expires_at'){{ $message }}@enderror</p></div>
            </div>
            <input type="hidden" name="is_active" value="0">
            <label class="sv-checkbox"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$voucher?->is_active ?? false)) @disabled($voucher?->archived_at)>{{ $editing ? 'Voucher enabled' : 'Activate voucher immediately' }}</label>
        </section>
        <footer class="sv-form-actions"><button type="submit" id="saveShopVoucher" class="sv-primary">{{ $editing ? 'Save Changes' : 'Create Voucher' }}</button><a href="{{ route('seller.vouchers.index') }}">Cancel</a></footer>
    </form>
</div>
@include('seller.vouchers.partials.script')
@endsection
