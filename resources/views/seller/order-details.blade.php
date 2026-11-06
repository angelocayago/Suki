@extends('layouts.seller')
@section('title', 'Order Details')
@section('page-title', 'Order Details')
@section('content')
@include('seller.partials.orders-style')
<div class="seller-orders">
    <header class="orders-intro">
        <a href="{{ $backUrl }}" class="orders-button">&larr; Back to Orders</a>
        <h2>{{ $order['reference'] }}</h2>
        <p class="orders-muted">Seller order #{{ $order['id'] }} &middot; {{ \App\Support\ManilaTime::format($order['date']) ?? 'Date not recorded' }} PHT</p>
        <p><span class="orders-badge" data-status="{{ $order['status'] }}">{{ $order['status_label'] }}</span></p>
    </header>
    @if(session('success'))<div role="status" class="orders-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="orders-alert orders-alert-error">{{ $errors->first() }}</div>@endif
    <div class="orders-details-grid">
        <div>
            <section class="orders-panel">
                <div class="orders-panel-heading"><h3>Purchased Items</h3></div>
                <div class="orders-panel-body">
                    @include('seller.partials.order-items')
                    <div class="orders-totals">
                        <div><span>Recorded merchandise subtotal</span><strong>&#8369;{{ number_format($order['subtotal_minor'] / 100, 2) }}</strong></div>
                        <div><span>Shipping fee</span><span>&#8369;{{ number_format($order['shipping_minor'] / 100, 2) }}</span></div>
                        <div><span>Recorded voucher discounts</span><span>&minus;&#8369;{{ number_format($order['discount_minor'] / 100, 2) }}</span></div>
                        <div><strong>Seller order amount</strong><strong>&#8369;{{ number_format($order['total_minor'] / 100, 2) }}</strong></div>
                    </div>
                    <p class="orders-muted">Amounts use this Seller order's saved snapshots and voucher redemptions.</p>
                </div>
            </section>
            <section class="orders-panel">
                <div class="orders-panel-heading"><h3>Fulfillment History</h3></div>
                <div class="orders-panel-body">
                    <ol class="orders-history">
                        @forelse($order['events'] as $event)
                            <li><strong>{{ ucwords(str_replace('_', ' ', strtolower($event->status))) }}</strong>
                                <time>{{ $event->occurred_at?->copy()->setTimezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Date not recorded' }} PHT</time>
                                @if($event->note)<p>{{ $event->note }}</p>@endif
                            </li>
                        @empty<li class="orders-muted">No fulfillment events recorded yet.</li>@endforelse
                    </ol>
                </div>
            </section>
        </div>
        <div>
            <section class="orders-panel">
                <div class="orders-panel-heading"><h3>Delivery &amp; Payment</h3></div>
                <div class="orders-panel-body"><dl>
                    <div><dt>Recipient</dt><dd>{{ $order['recipient'] }}</dd></div>
                    <div><dt>Contact</dt><dd>{{ $order['phone'] ?: 'Not recorded' }}</dd></div>
                    <div><dt>Delivery address</dt><dd>{{ $order['address'] ?: 'Not recorded' }}</dd></div>
                    <div><dt>Payment method</dt><dd>{{ strtoupper($order['payment_method']) }}</dd></div>
                    <div><dt>Recorded payment status</dt><dd>{{ ucwords(str_replace('_', ' ', $order['payment_status'])) }}</dd></div>
                    <div><dt>Tracking code</dt><dd>{{ $order['tracking'] ?: 'Not assigned' }}</dd></div>
                    <div><dt>Shipment status</dt><dd>{{ $order['shipment_status'] }}</dd></div>
                </dl>
                @if($order['shipment_id'])<p><a class="orders-button" href="{{ route('shipments.waybill', $order['shipment_id']) }}">View Waybill</a></p>@endif
                <p class="orders-muted">Payment status is recorded on the parent checkout order; it is not a Seller wallet balance.</p>
                </div>
            </section>
            <section class="orders-panel">
                <div class="orders-panel-heading"><h3>Order Actions</h3></div>
                <div class="orders-panel-body">
                    @include('seller.partials.order-actions')
                    @unless($order['next_action'])<p class="orders-muted">No Seller fulfillment transition is available at this stage.</p>@endunless
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
