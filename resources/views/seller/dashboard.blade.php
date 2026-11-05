@extends('layouts.seller')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('content')
@php
    if (!isset($salesSeries)) extract(app(\App\Support\SellerDashboard::class)->forUser(auth()->user()));
    $money = fn($minor) => '₱'.\App\Support\SellerReports::money((int)$minor);
    $statusLabels = \App\Support\SellerOrders::statuses();
@endphp
@include('seller.dashboard-partials.style')
<div class="seller-dashboard">
    <header class="sd-intro"><div><span class="sd-eyebrow">Store overview</span><h2>Your business at a glance</h2><p>Products, completed merchandise sales and fulfillment in one workspace.</p></div><span class="sd-timezone">Asia/Manila · All-time KPIs</span></header>
    @if(session('success'))<p class="sd-feedback" role="status">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="sd-feedback sd-error" role="alert">{{ session('error') }}</p>@endif
    <section class="sd-kpis" aria-label="Store summary">
        @foreach([
            ['label'=>'Total Products','value'=>number_format($productCount),'detail'=>'Saved listings in your shop','icon'=>'package','url'=>route('seller.products')],
            ['label'=>'Seller Orders','value'=>number_format($orderCount),'detail'=>$toConfirm.' awaiting confirmation','icon'=>'clipboard-list','url'=>route('seller.orders')],
            ['label'=>'Gross Completed Sales','value'=>$money($completedSales['raw_sales_minor']),'detail'=>'Merchandise before deductions','icon'=>'chart-no-axes-combined','url'=>route('seller.reports')],
            ['label'=>'Customers','value'=>number_format($customerCount),'detail'=>'Distinct saved Buyer identities','icon'=>'users-round','url'=>route('seller.orders')],
        ] as $metric)
            <a class="sd-kpi" href="{{ $metric['url'] }}"><div><span class="sd-kpi-label">{{ $metric['label'] }}</span><i data-lucide="{{ $metric['icon'] }}" aria-hidden="true"></i></div><strong>{{ $metric['value'] }}</strong><p>{{ $metric['detail'] }} <span aria-hidden="true">↗</span></p></a>
        @endforeach
    </section>
    <section class="sd-analytics" aria-label="Sales and order analytics">
        <article class="sd-panel sd-sales" data-sales-dashboard>
            <header class="sd-panel-heading"><div><span class="sd-eyebrow">Completed merchandise</span><h3>Sales Performance</h3><p>Raw completed sales · excludes shipping and deductions</p></div></header>
            <div class="sd-chart-toolbar"><div><strong data-sales-total>{{ $money($salesSeries['7d']['total_minor']) }}</strong><p data-sales-period>{{ $salesSeries['7d']['from'] }} – {{ $salesSeries['7d']['to'] }} · Asia/Manila</p></div><div class="sd-range-controls" role="group" aria-label="Sales reporting range">@foreach(['7d'=>'Last 7 days','30d'=>'Last 30 days','12m'=>'Last 12 months'] as $range=>$label)<button type="button" data-sales-range="{{ $range }}" aria-pressed="{{ $range==='7d'?'true':'false' }}" aria-controls="sellerSalesChart">{{ $label }}</button>@endforeach</div></div>
            @include('seller.dashboard-partials.chart')
            <p class="sd-note">Completion dates use Asia/Manila. All-time cards include canonical completed sales even when a historical completion date is missing.</p>
        </article>
        @include('seller.dashboard-partials.order-status')
    </section>
    <section class="sd-panel sd-finances" aria-label="Completed sales breakdown"><header class="sd-panel-heading"><div><h3>Completed Sales Breakdown</h3><p>All time · merchandise accounting, not available wallet funds</p></div><a href="{{ route('seller.wallet') }}">Wallet &amp; payouts ↗</a></header><div class="sd-financial-grid">
        @foreach([
            ['label'=>'Completed Raw Sales','minor'=>$completedSales['raw_sales_minor'],'note'=>'Saved unit prices × purchased quantities'],
            ['label'=>'Platform Commission','minor'=>$completedSales['commission_minor'],'note'=>'Commission recorded on completed items'],
            ['label'=>'Seller-Funded Discounts','minor'=>$completedSales['seller_funded_discount_minor'],'note'=>'Seller voucher allocations only'],
            ['label'=>'Completed Net Sales','minor'=>$completedSales['net_sales_minor'],'note'=>'Raw − commission − Seller-funded discounts'],
        ] as $metric)<div><p>{{ $metric['label'] }}</p><strong>{{ $money($metric['minor']) }}</strong><small>{{ $metric['note'] }}</small></div>@endforeach
    </div><p class="sd-note">Shipping and COD cash custody are separate. Platform-funded vouchers do not reduce this net merchandise amount. Delivered orders are excluded until completed.</p></section>
    <section class="sd-operations" aria-label="Store operations">
        <article class="sd-panel"><header class="sd-panel-heading"><div><h3>Quick Actions</h3><p>Keep your shop moving</p></div></header><div class="sd-quick-actions">
            @foreach([
                ['url'=>route('seller.products.create'),'icon'=>'plus','label'=>'Add Product','note'=>'Create a listing'],
                ['url'=>route('seller.orders'),'icon'=>'clipboard-list','label'=>'View Orders','note'=>$toConfirm.' awaiting confirmation'],
                ['url'=>route('seller.store.profile'),'icon'=>'store','label'=>'Store Settings','note'=>'Manage your shop profile'],
            ] as $action)<a href="{{ $action['url'] }}"><i data-lucide="{{ $action['icon'] }}" aria-hidden="true"></i><span><strong>{{ $action['label'] }}</strong><small>{{ $action['note'] }}</small></span><span aria-hidden="true">↗</span></a>@endforeach
        </div></article>
        <article class="sd-panel"><header class="sd-panel-heading"><div><h3>Inventory Alerts</h3><p>{{ $lowStockProducts }} products · {{ $lowStockVariants }} active variants at {{ $stockThreshold }} units or fewer</p></div><a href="{{ route('seller.inventory') }}">Manage ↗</a></header><div class="sd-stock-list">
            @forelse($lowStockList as $product)<div class="sd-stock-row"><span class="sd-stock-image"><i data-lucide="package" aria-hidden="true"></i>@if($product['image'])<img src="{{ $product['image'] }}" alt="" loading="lazy" data-dashboard-image>@endif</span><div><a href="{{ route('seller.products.edit',$product['id']) }}">{{ $product['name'] }}</a><p>{{ $product['stock'] }} total active units</p><ul>@foreach($product['variants'] as $variant)<li><span>{{ $variant['name'] ?: 'Variant' }}</span><strong class="sd-stock-badge {{ $variant['stock']<=0?'is-out':'' }}">{{ $variant['stock']<=0?'Out of stock':$variant['stock'].' left' }}</strong></li>@endforeach</ul></div></div>@empty<p class="sd-empty">{{ $productCount ? 'No low-stock alerts. Your active variants are above the restock threshold.' : 'No products yet. Add a listing to start monitoring stock.' }}</p>@endforelse
        </div></article>
    </section>
    <section class="sd-panel" aria-label="Recent Orders"><header class="sd-panel-heading"><div><h3>Recent Orders</h3><p>Your five latest Seller Orders and saved purchase snapshots</p></div><a href="{{ route('seller.orders') }}">View all ↗</a></header>
        @if($recentOrders->isNotEmpty())<div class="sd-table-scroll"><table class="sd-orders"><thead><tr><th scope="col">Order / Product</th><th scope="col">Buyer</th><th scope="col">Status</th><th scope="col">Merchandise Gross</th><th scope="col">Placed (Manila)</th><th scope="col">Action</th></tr></thead><tbody>@foreach($recentOrders as $order)<tr><td><strong>{{ $order['display_reference'] }}</strong><small>{{ $order['product_name'] ?: 'No item name recorded' }}@if($order['item_count']>1) +{{ $order['item_count']-1 }} more item(s)@endif</small>@if($order['tracking_code'])<small>{{ $order['tracking_code'] }}</small>@endif</td><td>{{ $order['recipient_name'] ?: 'Not recorded' }}</td><td><span class="sd-order-status status-{{ $order['status'] }}">{{ $statusLabels[strtoupper($order['status'])] ?? 'Unknown workflow' }}</span></td><td>{{ $money($order['gross_minor']) }}</td><td>{{ $order['created_at']?->setTimezone($businessTimezone)->format('M d, Y · g:i A') ?? 'Not recorded' }}</td><td><a href="{{ route('seller.orders.show',$order['id']) }}" aria-label="View order {{ $order['display_reference'] }}">View ↗</a></td></tr>@endforeach</tbody></table></div>@else<p class="sd-empty">No orders yet. Buyer purchases will appear here.</p>@endif
    </section>
    <section class="sd-panel sd-top-products" aria-label="Top products"><header class="sd-panel-heading"><div><h3>Top Products</h3><p>All time · units from completed Seller Orders</p></div><a href="{{ route('seller.reports') }}">Sales reports ↗</a></header><ol>@forelse($topProducts as $product)<li><strong>{{ $product['name'] ?: 'Historical product' }}</strong><span>{{ number_format($product['units']) }} sold · {{ $money($product['raw_minor']) }} gross</span></li>@empty<li class="sd-empty">No completed product sales yet.</li>@endforelse</ol></section>
</div>
@include('seller.dashboard-partials.script')
@include('seller.dashboard-partials.order-status-script')
@endsection