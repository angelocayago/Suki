@extends('layouts.seller')
@section('title', 'Returns / Refunds')
@section('page-title', 'Returns / Refunds')
@section('content')
@include('seller.returns.style')
@php($filtered = $criteria['search'] !== '' || $criteria['status'] !== 'all' || $criteria['date'] !== 'any')
<div class="seller-returns">
    <header class="rr-heading"><div><span class="rr-eyebrow">After-sales support</span><h1>Returns / Refunds</h1><p>Review your Buyer requests and Seller decisions. Use Chat to coordinate after approval.</p></div></header>
    <section class="rr-summary" aria-label="All your return and refund requests">
        @foreach($summary as $label => $count)<div class="rr-card"><span class="rr-eyebrow">{{ $label }}</span><strong>{{ number_format($count) }}</strong><small>All your requests</small></div>@endforeach
    </section>
    <section class="rr-card rr-list" aria-labelledby="rr-list-title">
        <header class="rr-section-heading"><div><h2 id="rr-list-title">Buyer requests</h2><p>You decide new Seller-review requests. Historical requests retain their original review steps.</p></div><span class="rr-count">{{ number_format($requests->total()) }} {{ $filtered ? 'matching' : 'total' }}</span></header>
        <form class="rr-filters" method="GET" action="{{ route('seller.return-refunds.index') }}">
            <div class="rr-search"><label for="rr-search">Search requests</label><div><input id="rr-search" type="search" name="search" maxlength="100" value="{{ $criteria['search'] }}" placeholder="Order reference, buyer or product"><button class="rr-button rr-secondary" type="submit">Search</button></div></div>
            <details class="rr-filter-panel">
                <summary>Filters @if($criteria['status'] !== 'all' || $criteria['date'] !== 'any')<span class="rr-dot" aria-label="Filters active"></span>@endif</summary>
                <div class="rr-filter-options">
                    <fieldset><legend>Request status</legend>@foreach(['all' => 'All statuses'] + $statuses as $value => $label)<label class="rr-radio"><input type="radio" name="status" value="{{ $value }}" @checked($criteria['status'] === $value)>{{ $value === 'SUBMITTED' ? 'Submitted requests' : ($value === 'ADMIN_REJECTED' ? 'Rejected (all workflows)' : $label) }}</label>@endforeach</fieldset>
                    <fieldset><legend>Request date</legend>@foreach($dates as $value => $label)<label class="rr-radio"><input type="radio" name="date" value="{{ $value }}" @checked((string)$criteria['date'] === (string)$value)>{{ $label }}</label>@endforeach<small>Calendar days in Asia/Manila.</small></fieldset>
                </div>
                <footer class="rr-actions"><a href="{{ route('seller.return-refunds.index') }}">Clear All</a><button class="rr-button" type="submit">Apply Filters</button></footer>
            </details>
        </form>
        @if($filtered)<div class="rr-active" aria-label="Active filters">
            @if($criteria['search'] !== '')<span>Search: {{ $criteria['search'] }}</span>@endif
            @if($criteria['status'] !== 'all')<span>{{ $criteria['status'] === 'SUBMITTED' ? 'Submitted requests' : ($criteria['status'] === 'ADMIN_REJECTED' ? 'Rejected (all workflows)' : $statuses[$criteria['status']]) }}</span>@endif
            @if($criteria['date'] !== 'any')<span>{{ $dates[$criteria['date']] }}</span>@endif
            <a href="{{ route('seller.return-refunds.index') }}">Clear All</a>
        </div>@endif
        @if($errors->any())<div class="rr-feedback" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <div class="rr-table-wrap"><table class="rr-table"><caption class="sr-only">Your buyer return and refund requests, newest first</caption>
            <thead><tr><th>Buyer / Order</th><th>Product / Request</th><th>Qty</th><th>Requested</th><th>Status</th><th>Your decision / recommendation</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>@forelse($requests as $return)
                <tr><td><strong>{{ $return->buyer?->full_name ?: ($return->buyer?->name ?? 'Buyer') }}</strong><small>{{ $return->order?->order_number ?: ($return->order?->reference ?? '#'.$return->order_id) }}</small><small>RR-{{ $return->id }}</small></td>
                    <td><strong>{{ $return->item?->product_name ?? 'Item unavailable' }}</strong><small>{{ $return->type === 'RETURN' ? 'Return' : ($return->type === 'REFUND' ? 'Refund' : $return->type) }} · {{ $return->buyer_reason }}</small></td>
                    <td>{{ $return->quantity }}<small>{{ $return->evidence_count }} proof image(s)</small></td><td><time datetime="{{ $return->created_at?->toIso8601String() }}">{{ $return->created_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'Unavailable' }}</time><small>{{ $return->created_at?->timezone('Asia/Manila')->format('g:i A') }} PHT</small></td>
                    <td><span class="rr-badge rr-status-{{ strtolower($return->status) }}">{{ $return->isSimplified() ? $return->statusLabel() : ($statuses[$return->status] ?? str_replace('_', ' ', $return->status)) }}</span></td>
                    <td>{{ $return->isSellerReview() ? ($return->decided_at ? $return->statusLabel() : 'Awaiting your decision') : ($return->isAdminReview() ? 'Not required' : ($return->seller_recommendation === 'APPROVE' ? 'Recommend approval' : ($return->seller_recommendation === 'REJECT' ? 'Recommend rejection' : 'Not submitted'))) }}</td>
                    <td><a class="rr-detail-link" href="{{ route('seller.return-refunds.show', $return) }}" aria-label="View request RR-{{ $return->id }}">View details &rarr;</a>@if(($workflows->get($return->id)?->parcel_status)==='SHIPPED' && in_array($return->status,\App\Models\ReturnRefundRequest::ACTIVE_STATUSES,true))<a class="rr-detail-link" href="{{ route('seller.return-refunds.show',$return) }}">Confirm Returned Parcel</a>@endif</td>
                </tr>
            @empty<tr><td colspan="7"><div class="rr-empty"><h3>{{ $filtered ? 'No matching requests' : 'No return or refund requests yet' }}</h3><p>{{ $filtered ? 'Try another search or clear your filters.' : 'Buyer requests for your order items will appear here.' }}</p>@if($filtered)<a class="rr-button rr-secondary" href="{{ route('seller.return-refunds.index') }}">Clear All</a>@endif</div></td></tr>@endforelse</tbody>
        </table></div>
        <footer class="rr-list-footer"><span>@if($requests->total())Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ number_format($requests->total()) }}@else 0 requests @endif</span>{{ $requests->links() }}</footer>
    </section>
</div>
@endsection
