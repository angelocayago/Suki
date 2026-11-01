@extends('layouts.seller')
@section('title', 'Sales reports')
@section('page-title', 'Reports')
@section('content')
@php($money = fn ($minor) => \App\Support\SellerReports::money((int) $minor))
@include('seller.partials.reports-style')
<div class="seller-reports">
    <header class="reports-heading">
        <div><nav aria-label="Breadcrumb"><a href="{{ route('seller.dashboard') }}">Dashboard</a> / Reports</nav><h2>Sales Reports</h2><p>Understand your completed sales, product performance, and earnings.</p></div>
        <a class="reports-primary" id="reportsExport" href="{{ route('seller.reports', $period['query'] + ['export'=>1]) }}">Export CSV</a>
    </header>
    @include('seller.partials.reports-filters')
    <p class="reports-period"><strong>{{ $period['label'] }}</strong> &middot; Asia/Manila &middot; Sales use completion dates; other order counts use last status updates.</p>
    <div class="reports-summary">
        @foreach(['Raw Completed Sales'=>['raw_minor','Completed merchandise value'], 'Product Commission'=>['commission_minor','Recorded product commissions'], 'Seller-Funded Discounts'=>['seller_discount_minor','Applied Seller voucher discounts'], 'Net Sales'=>['net_minor','Raw sales less commission and Seller discounts']] as $label=>$metric)
            <article class="reports-card {{ $metric[0] === 'net_minor' ? 'reports-net' : '' }}"><h3>{{ $label }}</h3><strong class="reports-value">&#8369;{{ $money($data['summary'][$metric[0]]) }}</strong><p>{{ $metric[1] }}</p></article>
        @endforeach
    </div>
    <section class="reports-card reports-secondary" aria-label="Period overview">
        <div><h3>Platform-Funded Discounts</h3><strong>&#8369;{{ $money($data['summary']['platform_discount_minor']) }}</strong><p>Informational; does not reduce net sales</p></div>
        <div><h3>Completed Orders</h3><strong>{{ $data['completed_orders'] }}</strong><p>Completed workflow only</p></div>
        <div><h3>Cancelled Orders</h3><strong>{{ $data['cancelled_orders'] }}</strong><p>Cancelled Seller orders</p></div>
        <div><h3>Units Sold</h3><strong>{{ $data['units_sold'] }}</strong><p>From completed order items</p></div>
    </section>
    <div class="reports-charts">
        @include('seller.partials.reports-sales-chart')
        <section class="reports-card">
            <header class="reports-section-heading"><h3>Order Status</h3><p>Completed orders by completion date; other orders by last status update.</p></header>
            @php($maxCount = max(1, ...array_column($data['status_chart'], 'count')))
            @if(array_sum(array_column($data['status_chart'], 'count')) === 0)<p class="reports-empty">No order data available for this period.</p>@endif
            <div class="reports-status-chart" role="img" aria-label="Seller order counts for {{ $period['label'] }}">
                @foreach($data['status_chart'] as $status)
                    <div class="reports-status-row"><span>{{ $status['label'] }}</span><div class="reports-bar-track"><div class="reports-bar reports-bar-{{ $status['key'] }}" style="width:{{ round($status['count'] / $maxCount * 100, 2) }}%"></div></div><strong>{{ $status['count'] }}</strong></div>
                @endforeach
            </div>
            <p class="reports-note">Delivered orders remain pending until completion. Returns and failed deliveries are shown separately.</p>
        </section>
    </div>
    <section class="reports-card reports-products">
        <header class="reports-section-heading"><h3>Best-Selling Products</h3><p>Completed sales ranked by units sold. Historical product names and photos are retained.</p></header>
        @if($data['products']->isEmpty())<p class="reports-empty">No sales data available for this period.</p>
        @else
            <div class="reports-table-scroll"><table><thead><tr><th scope="col">Product</th><th scope="col" class="reports-numeric">Units Sold</th><th scope="col" class="reports-numeric">Raw Completed Sales</th></tr></thead><tbody>
                @foreach($data['products'] as $product)
                    <tr><td><div class="reports-product">@if($image = \App\Support\SellerReports::image($product->image))<img src="{{ $image }}" alt="" loading="lazy">@else<span class="reports-no-image" aria-hidden="true">&mdash;</span>@endif<span>{{ $product->name ?: 'Product name not recorded' }}</span></div></td><td class="reports-numeric">{{ $product->units }}</td><td class="reports-numeric">&#8369;{{ $money($product->raw_minor) }}</td></tr>
                @endforeach
            </tbody></table></div>
            <div class="reports-pagination">{{ $data['products']->links() }}</div>
        @endif
    </section>
    <section class="reports-card">
        <header class="reports-section-heading"><h3>Current Wallet Overview</h3><p>Current account balances across all periods. Report date filters do not change these balances.</p></header>
        <div class="reports-wallet">
            @foreach(['Available'=>'available_minor', 'Held'=>'held_minor', 'Reserved'=>'reserved_minor', 'Paid'=>'paid_out_minor'] as $label=>$key)<div><h4>{{ $label }}</h4><strong>&#8369;{{ $money($data['wallet'][$key]) }}</strong></div>@endforeach
        </div>
        <p class="reports-note">Net sales exclude shipping and do not represent a payout balance. Wallet holds, reservations, paid payouts, and refund adjustments remain governed by the wallet ledger.</p>
    </section>
</div>
@include('seller.partials.reports-script')
@endsection
