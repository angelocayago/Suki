@extends('layouts.seller')
@section('title', 'Orders')
@section('page-title', 'Orders')
@section('content')
@include('seller.partials.orders-style')
@php
    // The existing legacy URL also uses the canonical listing; never session orders.
    if (!isset($paginator)) {
        $seller = auth()->user()->sellers()->where('status', 'approved')->firstOrFail();
        extract(app(\App\Support\SellerOrders::class)->listing($seller, request()));
    }
    $listCriteria = array_filter($criteria, fn ($value) => $value !== null && $value !== '');
@endphp
<div class="seller-orders">
    <header class="orders-intro">
        <h2>Orders</h2><p class="orders-muted">Review customer orders and prepare them for fulfillment.</p>
    </header>
    @if(session('success'))<div role="status" class="orders-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="orders-alert orders-alert-error">{{ $errors->first() }}</div>@endif
    <section class="orders-summary" aria-label="Matching order summary">
        @foreach(['total'=>'Matching orders', 'pending'=>'To confirm', 'processing'=>'Processing', 'ready'=>'Ready for pickup', 'completed'=>'Completed', 'attention'=>'Cancelled / Failed / RTS'] as $key => $label)
            <div><p>{{ $label }}</p><strong>{{ number_format((int) $summary->$key) }}</strong></div>
        @endforeach
    </section>
    <section class="orders-panel" aria-label="Order filters">
        <form method="GET" action="{{ route('seller.orders') }}" class="orders-panel-body">
            <div class="orders-primary-filters">
                <label for="orders-search">Search order number or Seller order ID
                    <input id="orders-search" name="search" type="search" maxlength="100" value="{{ $criteria['search'] ?? '' }}" placeholder="Order number or ID">
                </label>
                <label for="orders-status">Order status
                    <select id="orders-status" name="status"><option value="">All statuses</option>
                        @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($criteria['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
            <details class="orders-advanced">
                <summary class="orders-button">More Filters</summary>
                <div class="orders-advanced-fields">
                    <label for="orders-from">From date<input id="orders-from" name="from" type="date" value="{{ $criteria['from'] ?? '' }}"></label>
                    <label for="orders-to">To date<input id="orders-to" name="to" type="date" value="{{ $criteria['to'] ?? '' }}"></label>
                    <label for="orders-shipment">Shipment status<select id="orders-shipment" name="shipment_status"><option value="">All shipment statuses</option>
                        <option value="NO_SHIPMENT" @selected(($criteria['shipment_status'] ?? '') === 'NO_SHIPMENT')>No shipment yet</option>
                        @foreach($shipmentStatuses as $value => $label)<option value="{{ $value }}" @selected(($criteria['shipment_status'] ?? '') === $value)>{{ $label }}</option>@endforeach
                    </select></label>
                    <label for="orders-sort">Sort<select id="orders-sort" name="sort"><option value="newest" @selected(($criteria['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($criteria['sort'] ?? '') === 'oldest')>Oldest first</option></select></label>
                    <p class="orders-muted">Date placed uses Asia/Manila. The end date is inclusive.</p>
                </div>
            </details>
            <div class="orders-actions"><button class="orders-button orders-button-primary" type="submit">Apply Filters</button><a href="{{ route('seller.orders') }}" class="orders-button">Clear Filters</a></div>
            <div class="orders-active" aria-label="Active filters">
                @foreach(['search'=>'Search', 'status'=>'Status', 'from'=>'From', 'to'=>'To', 'shipment_status'=>'Shipment', 'sort'=>'Sort'] as $key => $label)
                    @if(filled($criteria[$key] ?? null) && !($key === 'sort' && $criteria[$key] === 'newest'))
                        <span>{{ $label }}: {{ match($key) {
                            'status' => $statuses[$criteria[$key]],
                            'shipment_status' => $shipmentStatuses[$criteria[$key]] ?? 'No shipment yet',
                            'sort' => $criteria[$key] === 'oldest' ? 'Oldest first' : 'Newest first',
                            default => $criteria[$key],
                        } }}</span>
                    @endif
                @endforeach
            </div>
        </form>
    </section>
    <section class="orders-panel">
        <div class="orders-panel-heading"><h3>Customer Orders</h3><p class="orders-muted">{{ $paginator->total() }} matching Seller orders. Summary cards cover all matching pages.</p></div>
        @if($paginator->count())
            <div class="orders-table-wrap">
                <table><thead><tr>@foreach(['Order / Date','Buyer / Items','Amount / Payment','Fulfillment','Actions'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody>@foreach($paginator as $order)
                    <tr>
                        <td data-label="Order / Date"><strong>{{ $order['reference'] }}</strong><p class="orders-muted">Seller order #{{ $order['id'] }}</p><p class="orders-muted">{{ \App\Support\ManilaTime::format($order['date']) ?? 'Date not recorded' }} PHT</p></td>
                        <td data-label="Buyer / Items"><strong>{{ $order['recipient'] }}</strong>@include('seller.partials.order-items')</td>
                        <td data-label="Amount / Payment"><strong>&#8369;{{ number_format($order['total_minor'] / 100, 2) }}</strong><p class="orders-muted">{{ strtoupper($order['payment_method']) }} &middot; {{ $order['payment_status'] }}</p></td>
                        <td data-label="Fulfillment"><span class="orders-badge" data-status="{{ $order['status'] }}">{{ $order['status_label'] }}</span><p class="orders-muted">{{ $order['shipment_status'] }}</p><p class="orders-muted">{{ $order['tracking'] ?: 'Tracking not assigned' }}</p></td>
                        <td data-label="Actions"><a class="orders-button" href="{{ route('seller.orders.show', ['sellerOrder'=>$order['id'], 'list'=>$listCriteria]) }}">View Details<span class="sr-only"> {{ $order['reference'] }}</span></a>@include('seller.partials.order-actions')</td>
                    </tr>
                @endforeach</tbody></table>
            </div>
        @else
            <div class="orders-empty"><h3>No matching orders</h3><p class="orders-muted">Orders appear here when buyers order from your shop. Try clearing your filters.</p><a href="{{ route('seller.orders') }}" class="orders-button">Clear Filters</a></div>
        @endif
        <div class="orders-panel-body">{{ $paginator->links() }}</div>
    </section>
</div>
@endsection
