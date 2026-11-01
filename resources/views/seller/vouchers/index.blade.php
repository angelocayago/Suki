@extends('layouts.seller')
@section('title', 'Shop Vouchers')
@section('page-title', 'Vouchers')
@section('content')
@include('seller.vouchers.partials.style')
<div class="seller-vouchers">
    <header class="sv-heading"><div><h2>Shop Vouchers</h2><p>Create discounts for eligible merchandise in your shop.</p></div><a class="sv-primary" href="{{ route('seller.vouchers.create') }}">Create Shop Voucher</a></header>
    @include('seller.vouchers.partials.feedback')
    <details class="sv-card sv-filters"><summary>Filters @if(count($criteria))<span class="sv-badge">{{ count($criteria) }}</span>@endif</summary>
        <form method="GET" action="{{ route('seller.vouchers.index') }}"><label>Voucher search<input type="search" name="search" maxlength="64" value="{{ $criteria['search'] ?? '' }}" placeholder="Search voucher code"></label><label>Status<select name="status"><option value="">All statuses</option>@foreach(\App\Support\SellerVouchers::statuses() as $value=>$label)<option value="{{ $value }}" @selected(($criteria['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label><button type="submit" class="sv-primary">Apply Filters</button><a href="{{ route('seller.vouchers.index') }}">Clear Filters</a></form>
    </details>
    @if(count($criteria))<p class="sv-applied">{{ $vouchers->total() }} voucher(s) match your filters. <a href="{{ route('seller.vouchers.index') }}">Clear All</a></p>@endif
    <section class="sv-card sv-list">
        <header><h3>Your Vouchers</h3><span>{{ $vouchers->total() }} voucher(s)</span></header>
        @if($vouchers->isEmpty())<div class="sv-empty"><strong>{{ count($criteria) ? 'No vouchers match your filters.' : 'No shop vouchers yet.' }}</strong><p>@if(count($criteria))<a href="{{ route('seller.vouchers.index') }}">Clear Filters</a>@else<a href="{{ route('seller.vouchers.create') }}">Create your first voucher</a>@endif</p></div>
        @else<div class="sv-table-scroll"><table><thead><tr><th>Voucher Code</th><th>Discount</th><th>Usage</th><th>Validity (Manila)</th><th>Actions</th></tr></thead><tbody>
            @foreach($vouchers as $voucher)
                @php($state = \App\Support\SellerVouchers::status($voucher))
                <tr><td><strong class="sv-code">{{ $voucher->code }}</strong><span class="sv-status sv-status-{{ $state }}">{{ \App\Support\SellerVouchers::statuses()[$state] }}</span></td>
                    <td><strong>{{ $voucher->discount_type === 'PERCENTAGE' ? \App\Support\SellerVouchers::decimal($voucher->discount_bps).'%' : 'PHP '.\App\Support\SellerVouchers::decimal($voucher->discount_minor) }}</strong><small>{{ $voucher->discount_type === 'PERCENTAGE' ? 'Percentage ? no maximum cap' : 'Fixed amount' }}</small></td>
                    <td>{{ $voucher->used_count }} used<small>{{ $voucher->usage_limit === null ? 'Unlimited total uses' : 'Total limit: '.$voucher->usage_limit }}</small></td>
                    <td><small>Starts: {{ $voucher->starts_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A') ?? 'Any time' }}</small><small>Expires: {{ $voucher->expires_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A') ?? 'No expiry' }}</small></td>
                    <td><div class="sv-row-actions"><a href="{{ route('seller.vouchers.edit',$voucher) }}">Edit</a>@unless($voucher->archived_at)<form method="POST" action="{{ route('seller.vouchers.archive',$voucher) }}" data-voucher-archive>@csrf @method('DELETE')<button type="submit" class="sv-archive">Archive</button></form>@endunless</div></td>
                </tr>
            @endforeach
        </tbody></table></div><div class="sv-pagination">{{ $vouchers->links() }}</div>@endif
    </section>
    <p class="sv-note">Shop vouchers apply only to this shop's eligible merchandise. Minimum purchase amounts, per-buyer usage limits, names, descriptions and percentage caps are not currently available. Archived vouchers cannot be redeemed.</p>
</div>
@include('seller.vouchers.partials.script')
@endsection
