@extends('layouts.seller')
@section('title', 'Wallet & Payouts')
@section('page-title', 'Wallet & Payouts')

@section('content')
@php
    $money = fn (int $minor) => \App\Support\SellerWalletPage::money($minor);
    $available = (int) $wallet['available_minor'];
@endphp
@include('seller.wallet.style')
<div class="seller-wallet">
    <header class="wallet-heading"><p class="wallet-eyebrow">Finance</p><h1>Wallet &amp; Payouts</h1><p>Manage your wallet balance and payout requests.</p></header>
    @if(session('success'))<div class="wallet-success" role="status">{{ session('success') }}</div>@endif
    <div class="wallet-summary">
        @foreach([
            'available_minor'=>['Available Balance', 'Balance available for payout requests.'],
            'held_minor'=>['Held Balance', 'Funds awaiting release from wallet holds.'],
            'reserved_minor'=>['Reserved Balance', 'Funds reserved for pending or approved payouts.'],
            'paid_out_minor'=>['Paid Out', 'Payouts recorded as paid.'],
        ] as $key=>[$label, $description])
            <section class="wallet-card wallet-metric" aria-label="{{ $label }}"><h2>{{ $label }}</h2><p class="wallet-value">&#8369;{{ $money((int) $wallet[$key]) }}</p><p>{{ $description }}</p></section>
        @endforeach
    </div>
    <section class="wallet-card wallet-request" aria-labelledby="walletRequestHeading">
        <div><h2 id="walletRequestHeading">Request Payout</h2><p>Available balance: <strong>&#8369;{{ $money($available) }}</strong></p><p>Requests are reviewed before payment.</p></div>
        <form id="walletPayoutForm" method="POST" action="{{ route('seller.payouts.store') }}" data-available-minor="{{ $available }}">
            @csrf
            <div class="wallet-amount-field"><label for="walletPayoutAmount">Payout amount (PHP) <span aria-hidden="true">*</span></label>
                <input id="walletPayoutAmount" name="amount" type="text" inputmode="decimal" pattern="[0-9]+([.][0-9]{1,2})?" required value="{{ old('amount') }}" placeholder="0.00" aria-describedby="walletAmountHelp{{ $errors->has('amount') ? ' walletAmountError' : '' }}" @if($errors->has('amount')) aria-invalid="true" @endif>
                <p id="walletAmountHelp">Enter a positive amount with up to two decimal places.</p>
                @error('amount')<p id="walletAmountError" class="wallet-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button id="walletPayoutSubmit" class="wallet-primary" type="submit" @disabled($available < 1)>Request Payout</button>
            @if($available < 1)<p class="wallet-no-funds">No balance available for a payout request.</p>@endif
        </form>
    </section>
    <section class="wallet-card wallet-history" aria-labelledby="walletHistoryHeading">
        <div class="wallet-history-heading"><h2 id="walletHistoryHeading">Payout History</h2><p>{{ $payouts->total() }} {{ $criteria ? 'matching requests' : 'requests' }} &middot; Newest first</p></div>
        <details class="wallet-filters">
            <summary>Filters @if(count($criteria))<span class="wallet-filter-count">{{ count($criteria) }} active</span>@endif</summary>
            <form method="GET" action="{{ route('seller.wallet') }}">
                <div><label for="walletFilterStatus">Payout status</label><select id="walletFilterStatus" name="status"><option value="all">All Statuses</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(old('status', $criteria['status'] ?? 'all') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label for="walletFilterDate">Request date</label><select id="walletFilterDate" name="date">@foreach($dates as $value=>$label)<option value="{{ $value }}" @selected(old('date', $criteria['date'] ?? 'any') === $value)>{{ $label }}</option>@endforeach</select><p>Calendar days in Asia/Manila.</p></div>
                <div class="wallet-filter-actions"><a class="wallet-secondary" href="{{ route('seller.wallet') }}">Clear All</a><button class="wallet-primary" type="submit">Apply Filters</button></div>
            </form>
        </details>
        @foreach(['status', 'date', 'page'] as $field)
            @error($field)<p class="wallet-error wallet-filter-error" role="alert">{{ $message }}</p>@enderror
        @endforeach
        @if($criteria)<div class="wallet-active-filters" aria-label="Active payout history filters">
            @if(isset($criteria['status']))<span>Status: {{ $statuses[$criteria['status']] }}</span>@endif
            @if(isset($criteria['date']))<span>{{ $dates[$criteria['date']] }}</span>@endif
            <a href="{{ route('seller.wallet') }}">Clear All</a>
        </div>@endif
        @if($payouts->isEmpty())
            <div class="wallet-empty"><h3>{{ $criteria ? 'No payout requests match your filters.' : 'No payout requests yet.' }}</h3><p>{{ $criteria ? 'Try another status or date period.' : 'Your payout requests will appear here.' }}</p>@if($criteria)<a href="{{ route('seller.wallet') }}">Clear All</a>@endif</div>
        @else
            <div class="wallet-table-scroll" role="region" aria-label="Payout history table" tabindex="0">
                <table><thead><tr><th scope="col">Request</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Processed</th><th scope="col">Details</th></tr></thead><tbody>
                @foreach($payouts as $payout)
                    <tr><td><strong>#{{ $payout->id }}</strong><time datetime="{{ $payout->created_at?->toIso8601String() }}">{{ $payout->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') ?? '—' }}</time></td>
                        <td class="wallet-table-amount">&#8369;{{ $money($payout->amount_minor) }}</td>
                        <td><span class="wallet-badge wallet-status-{{ strtolower($payout->status) }}">{{ $statuses[$payout->status] ?? $payout->status }}</span></td>
                        <td>@if($payout->status === 'PAID' && $payout->paid_at)<span>Paid</span><time datetime="{{ $payout->paid_at->toIso8601String() }}">{{ $payout->paid_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</time>@elseif($payout->reviewed_at)<span>Reviewed</span><time datetime="{{ $payout->reviewed_at->toIso8601String() }}">{{ $payout->reviewed_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</time>@else<span class="wallet-muted">—</span>@endif</td>
                        <td class="wallet-history-detail">@if($payout->status === 'REJECTED' && filled($payout->reason)){{ $payout->reason }}@elseif($payout->status === 'PAID' && filled($payout->reference))<span class="wallet-muted">Reference</span> {{ $payout->reference }}@else<span class="wallet-muted">—</span>@endif</td>
                    </tr>
                @endforeach
                </tbody></table>
            </div>
            <footer class="wallet-pagination"><p>Showing {{ $payouts->firstItem() }}–{{ $payouts->lastItem() }} of {{ $payouts->total() }} &middot; Times in Asia/Manila</p>{{ $payouts->links() }}</footer>
        @endif
    </section>
</div>
<dialog id="walletPayoutDialog" class="wallet-payout-dialog" aria-labelledby="walletPayoutTitle" aria-describedby="walletPayoutDescription">
    <h2 id="walletPayoutTitle">Confirm payout request</h2>
    <p id="walletPayoutDescription">Request <strong id="walletConfirmAmount"></strong> from your available balance? This sends a request for review; it does not complete payment.</p>
    <div><button type="button" id="walletPayoutCancel" autofocus>Cancel</button><button type="button" id="walletPayoutConfirm">Confirm Request</button></div>
</dialog>
@include('seller.wallet.script')
@endsection
