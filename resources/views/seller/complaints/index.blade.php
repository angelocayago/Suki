@extends('layouts.seller')
@section('title', 'Complaints')
@section('page-title', 'Complaints')
@section('content')
@include('seller.returns.style')
@include('seller.complaints.style')
@php($filtered = $criteria['search'] !== '' || $criteria['status'] !== 'all' || $criteria['date'] !== 'any')
<div class="seller-returns seller-complaints">
    <header class="rr-heading"><div><span class="rr-eyebrow">Marketplace support</span><h1>Complaints</h1><p>Track complaints you submitted for your store and orders.</p></div></header>
    <section class="rr-summary" aria-label="All your authorized complaints">
        @foreach($summary as $label => $count)<div class="rr-card"><span class="rr-eyebrow">{{ $label }}</span><strong>{{ number_format($count) }}</strong><small>All your accessible complaints</small></div>@endforeach
    </section>
    <section class="rr-card rr-list" aria-labelledby="complaints-title">
        <header class="rr-section-heading"><div><h2 id="complaints-title">Store and order complaints</h2><p>Complaint access follows the existing complainant permissions. Admin handles review and decisions.</p></div><span class="rr-count">{{ number_format($complaints->total()) }} {{ $filtered ? 'matching' : 'total' }}</span></header>
        <form class="rr-filters" method="GET" action="{{ route('seller.complaints.index') }}">
            <div class="rr-search"><label for="complaint-search">Search complaints</label><div><input id="complaint-search" type="search" name="search" maxlength="100" value="{{ $criteria['search'] }}" placeholder="Complaint or order reference"><button class="rr-button rr-secondary" type="submit">Search</button></div></div>
            <details class="rr-filter-panel"><summary>Filters @if($criteria['status'] !== 'all' || $criteria['date'] !== 'any')<span class="rr-dot" aria-label="Filters active"></span>@endif</summary><div class="rr-filter-options">
                <fieldset><legend>Complaint status</legend>@foreach(['all' => 'All statuses'] + $statuses as $value => $label)<label class="rr-radio"><input type="radio" name="status" value="{{ $value }}" @checked($criteria['status'] === $value)>{{ $label }}</label>@endforeach</fieldset>
                <fieldset><legend>Submission date</legend>@foreach($dates as $value => $label)<label class="rr-radio"><input type="radio" name="date" value="{{ $value }}" @checked((string)$criteria['date'] === (string)$value)>{{ $label }}</label>@endforeach<small>Calendar days in Asia/Manila.</small></fieldset>
            </div><footer class="rr-actions"><a href="{{ route('seller.complaints.index') }}">Clear All</a><button class="rr-button" type="submit">Apply Filters</button></footer></details>
        </form>
        @if($errors->any())<div class="rr-feedback" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if($filtered)<div class="rr-active" aria-label="Active filters">@if($criteria['search'] !== '')<span>Search: {{ $criteria['search'] }}</span>@endif @if($criteria['status'] !== 'all')<span>{{ $statuses[$criteria['status']] }}</span>@endif @if($criteria['date'] !== 'any')<span>{{ $dates[$criteria['date']] }}</span>@endif<a href="{{ route('seller.complaints.index') }}">Clear All</a></div>@endif
        <div class="rr-table-wrap"><table class="rr-table"><caption class="sr-only">Authorized complaints, newest first</caption><thead><tr><th>Complaint / Subject</th><th>Order</th><th>Complainant</th><th>Category</th><th>Submitted</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
            @forelse($complaints as $complaint)<tr>
                <td><strong>{{ $complaint->reference }}</strong><small>{{ $complaint->subject }}</small></td><td>{{ $complaint->order?->order_number ?? 'Not linked to an order' }}</td>
                <td>{{ $complaint->complainant?->full_name ?: ($complaint->complainant?->name ?? 'Complainant unavailable') }}<small>{{ str($complaint->complainant_role)->lower()->headline() }}</small></td>
                <td>{{ str($complaint->category->value)->lower()->replace('_', ' ')->headline() }}</td>
                <td><time datetime="{{ $complaint->created_at->toIso8601String() }}">{{ $complaint->created_at->timezone('Asia/Manila')->format('M j, Y') }}</time><small>{{ $complaint->created_at->timezone('Asia/Manila')->format('g:i A') }} PHT</small></td>
                <td><span class="rr-badge cmp-{{ strtolower($complaint->status->value) }}">{{ $statuses[$complaint->status->value] }}</span></td>
                <td><a class="rr-detail-link" href="{{ route('seller.complaints.show', $complaint) }}" aria-label="View complaint {{ $complaint->reference }}">View details &rarr;</a></td>
            </tr>@empty<tr><td colspan="7"><div class="rr-empty"><h3>{{ $filtered ? 'No matching complaints' : 'No complaints related to your store yet.' }}</h3><p>{{ $filtered ? 'Try another search or clear your filters.' : 'Complaints you submit for your store or orders will appear here.' }}</p>@if($filtered)<a class="rr-button rr-secondary" href="{{ route('seller.complaints.index') }}">Clear All</a>@endif</div></td></tr>@endforelse
        </tbody></table></div><footer class="rr-list-footer"><span>{{ number_format($complaints->total()) }} {{ $filtered ? 'matching complaints' : 'complaints' }}</span>{{ $complaints->links() }}</footer>
    </section>
</div>
@endsection
