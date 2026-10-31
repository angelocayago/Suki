@extends('layouts.seller')
@section('title', 'Products')
@section('page-title', 'Products')
@section('content')
@include('seller.partials.product-listing-style')
@php
    $stockOptions = ['in_stock'=>'In stock', 'low_stock'=>'Has low-stock variants', 'out_of_stock'=>'All active variants out of stock', 'no_active_variants'=>'No active variants'];
@endphp
<div class="seller-products" id="sellerProductsListing">
    <header class="products-header">
        <div><nav class="products-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('seller.dashboard') }}">Dashboard</a><span aria-hidden="true">&rsaquo;</span><span>Products</span></nav><h2>Product Management</h2><p class="products-subtitle">Manage your product listings, pricing, availability, and inventory from one workspace.</p></div>
        <a href="{{ route('seller.products.create') }}" class="products-button products-button-primary"><i data-lucide="plus" aria-hidden="true"></i>Add Product</a>
    </header>
    @if(session('success'))<div role="status" class="products-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="products-alert">{{ $errors->first() }}</div>@endif
    <section class="products-summary" aria-label="Matching product summary">
        @foreach([
            ['total','Total products','All matching listings','package','mint'],
            ['active','Active','Active matching listings','circle-check','green'],
            ['inactive','Inactive','Inactive matching listings','eye-off','neutral'],
            ['restock','Stock attention',$summary['out_of_stock'].' out of stock','triangle-alert','amber'],
        ] as [$key,$label,$description,$icon,$tone])
            <div><div class="products-metric-main"><div><p>{{ $label }}</p><strong>{{ number_format($summary[$key]) }}</strong></div><span class="products-metric-icon" data-tone="{{ $tone }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span></div><p class="products-metric-footer">{{ $description }}</p></div>
        @endforeach
    </section>
    <section class="products-panel">
        <div class="products-panel-heading"><div><h3>Product Listings</h3><p class="products-muted">Search, filter, and manage products in your store.</p></div><a href="{{ route('seller.inventory') }}" class="products-button"><i data-lucide="boxes" aria-hidden="true"></i>Manage Inventory</a></div>
        <div class="products-filter-area" aria-label="Product filters">
            <form action="{{ route('seller.products') }}" method="GET" class="products-filter-form">
                <div class="products-primary-filters">
                    <label for="products-search"><span class="products-sr-only">Search by product name or SKU</span><input id="products-search" type="search" name="search" maxlength="100" value="{{ $criteria['search'] ?? '' }}" placeholder="Search product name or SKU..."></label>
                    <label for="products-status"><span class="products-sr-only">Product activity</span><select id="products-status" name="status"><option value="">All products</option><option value="active" @selected(($criteria['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($criteria['status'] ?? '') === 'inactive')>Inactive</option></select></label>
                    <button id="products-filter-toggle" type="button" class="products-button" aria-expanded="false" aria-controls="products-advanced-panel"><i data-lucide="sliders-horizontal" aria-hidden="true"></i><span data-filter-label>More Filters</span><i class="products-filter-caret" data-lucide="chevron-down" aria-hidden="true"></i></button>
                    <button type="submit" class="products-button products-button-primary">Apply Filters</button><a href="{{ route('seller.products') }}" class="products-clear">Clear Filters</a>
                </div>
                <div id="products-advanced-panel" class="products-advanced-fields" hidden>
                    @if($filterCategories->count() > 1)
                        <label for="products-category">Existing product category<select id="products-category" name="category"><option value="">All categories</option>@foreach($filterCategories as $category)<option value="{{ $category->id }}" @selected((string)($criteria['category'] ?? '') === (string)$category->id)>{{ $category->display_name }}</option>@endforeach</select></label>
                    @elseif(filled($criteria['category'] ?? null))
                        <input type="hidden" name="category" value="{{ $criteria['category'] }}">
                    @endif
                    <label for="products-stock">Stock availability<select id="products-stock" name="stock"><option value="">All stock levels</option>@foreach($stockOptions as $value=>$label)<option value="{{ $value }}" @selected(($criteria['stock'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label for="products-sort">Sort<select id="products-sort" name="sort"><option value="newest" @selected(($criteria['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($criteria['sort'] ?? '') === 'oldest')>Oldest first</option></select></label>
                </div>
                <div class="products-active" aria-label="Active filters">
                    @foreach(['search'=>'Search', 'status'=>'Activity', 'category'=>'Category', 'stock'=>'Stock', 'sort'=>'Sort'] as $key=>$label)
                        @if(filled($criteria[$key] ?? null) && !($key === 'sort' && $criteria[$key] === 'newest'))
                            <span>{{ $label }}: {{ match($key) {
                                'category' => $filterCategories->firstWhere('id',$criteria[$key])?->name,
                                'stock' => $stockOptions[$criteria[$key]],
                                'sort' => 'Oldest first',
                                'status' => ucfirst($criteria[$key]),
                                default => $criteria[$key],
                            } }}</span>
                        @endif
                    @endforeach
                </div>
            </form>
        </div>
        <div class="products-list-context"><span>@if($paginator->count())Showing {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} of {{ $paginator->total() }} matching products @else 0 matching products on this page @endif</span><span>Registered business category: <strong>{{ $registeredCategory }}</strong></span></div>
        <div class="products-selection-bar"><span data-selected-count aria-live="polite">0 selected on this page</span><button type="button" data-clear-selection disabled>Clear Selection</button><span>Current page only &middot; Selection only; no bulk actions</span></div>
        @if($paginator->count())
            <div class="products-table-wrap"><table><thead><tr><th class="products-select-cell"><input type="checkbox" data-select-page aria-label="Select all products on this page"></th>@foreach(['Product', 'Category', 'Price', 'Stock', 'Aggregate stock status', 'Activity / Moderation', 'Last updated', 'Actions'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @foreach($paginator as $product)
                <tr data-product-row="{{ $product['id'] }}">
                    <td class="products-select-cell"><input type="checkbox" data-product-checkbox value="{{ $product['id'] }}" aria-label="Select {{ $product['name'] }}"></td>
                    <td data-label="Product"><div class="products-identity">
                        <div class="products-thumbnail">@if($product['image'])<img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false">@endif<span @if($product['image']) hidden @endif aria-label="No product image"><i data-lucide="package" aria-hidden="true"></i></span></div>
                        <div><strong>{{ $product['name'] }}</strong><p class="products-sku">SKU: {{ $product['variants']->pluck('sku')->filter()->take(2)->implode(', ') ?: 'Not recorded' }}</p>
                            @if($product['variants']->isNotEmpty())
                                <details class="products-variant-details"><summary>Variant stock &middot; {{ $product['low_variants'] + $product['empty_variants'] }} stock alerts</summary><ul class="products-variant-list">@foreach($product['variants'] as $variant)<li><strong>{{ $variant['sku'] ?: 'SKU not recorded' }}</strong> &middot; {{ $variant['name'] }}<br>{{ $variant['active'] ? 'Active' : 'Inactive' }} &middot; {{ $variant['stock'] }} units &middot; &#8369;{{ number_format($variant['price_minor'] / 100,2) }}</li>@endforeach</ul></details>
                            @endif
                        </div>
                    </div></td>
                    <td data-label="Category">@if($product['category_parent']){{ $product['category_parent'] }}<p class="products-muted">{{ $product['category'] }}</p>@else{{ $product['category'] }}@endif</td>
                    <td data-label="Price" class="products-price">@if($product['price_min'] === null)<span class="products-muted">No active price</span>@else&#8369;{{ number_format($product['price_min'] / 100,2) }}@if($product['price_max'] !== $product['price_min']) &ndash; &#8369;{{ number_format($product['price_max'] / 100,2) }}@endif @endif</td>
                    <td data-label="Stock">{{ number_format($product['stock']) }}</td>
                    <td data-label="Aggregate stock status"><span class="products-badge" data-state="{{ $product['stock_status'] }}">{{ $product['stock_status'] }}</span></td>
                    <td data-label="Activity / Moderation"><span class="products-badge" data-state="{{ $product['activity'] }}">{{ $product['activity'] }}</span><p><span class="products-review" data-state="{{ $product['moderation'] }}">Review: {{ $product['moderation'] }}</span></p>@if($product['moderation_reason'])<p class="products-muted">{{ $product['moderation_reason'] }}</p>@endif</td>
                    <td data-label="Last updated" class="products-updated">@if($product['updated_at'])<time datetime="{{ $product['updated_at']->toIso8601String() }}">{{ $product['updated_at']->format('M j, Y') }}<span>{{ $product['updated_at']->format('g:i A') }} PHT</span></time>@else Not recorded @endif</td>
                    <td data-label="Actions"><div class="products-actions"><a href="{{ route('seller.products.edit',$product['id']) }}" class="products-icon-button" title="Edit Product" aria-label="Edit {{ $product['name'] }}"><i data-lucide="pencil" aria-hidden="true"></i></a><form action="{{ route('seller.products.archive',$product['id']) }}" method="POST" onsubmit="return confirm('Archive this product?')">@csrf @method('DELETE')<button type="submit" class="products-icon-button" title="Archive Product" aria-label="Archive {{ $product['name'] }}"><i data-lucide="archive" aria-hidden="true"></i></button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
        @else
            <div class="products-empty"><i data-lucide="package-search" aria-hidden="true"></i><h3>No matching products</h3><p class="products-muted">Add a product using the button above, or clear filters to see your listings.</p><a href="{{ route('seller.products') }}" class="products-clear">Clear Filters</a></div>
        @endif
        <div class="products-list-footer"><p>All four summary cards reflect all matching records across pages. Stock and price summaries use active variants.</p>{{ $paginator->links() }}</div>
    </section>
</div>
@endsection
@push('scripts')
<script>
function initSellerProductListing() {
    const root = document.getElementById('sellerProductsListing');
    if (!root || root.dataset.initialized) return;
    const toggle = root.querySelector('#products-filter-toggle');
    const panel = root.querySelector('#products-advanced-panel');
    if (!toggle || !panel) return;
    root.dataset.initialized = 'true';
    const label = toggle.querySelector('[data-filter-label]');
    function setFiltersOpen(open, restoreFocus = false) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        label.textContent = open ? 'Hide Filters' : 'More Filters';
        if (restoreFocus) toggle.focus();
    }
    toggle.addEventListener('click', () => setFiltersOpen(panel.hidden));
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); setFiltersOpen(false, true); }
    });
    setFiltersOpen(false);
    const boxes = [...root.querySelectorAll('[data-product-checkbox]')];
    const headers = [...root.querySelectorAll('[data-select-page]')];
    const rows = [...root.querySelectorAll('[data-product-row]')];
    const count = root.querySelector('[data-selected-count]');
    const clear = root.querySelector('[data-clear-selection]');
    function renderSelection() {
        const selected = boxes.filter(box => box.checked);
        headers.forEach(header => {
            header.checked = boxes.length > 0 && selected.length === boxes.length;
            header.indeterminate = selected.length > 0 && selected.length < boxes.length;
            header.disabled = boxes.length === 0;
        });
        rows.forEach(row => { row.dataset.selected = String(selected.some(box => box.value === row.dataset.productRow)); });
        count.textContent = selected.length + ' selected on this page';
        clear.disabled = selected.length === 0;
    }
    function resetSelection() { boxes.forEach(box => { box.checked = false; }); renderSelection(); }
    boxes.forEach(box => box.addEventListener('change', renderSelection));
    headers.forEach(header => header.addEventListener('change', () => {
        boxes.forEach(box => { box.checked = header.checked; }); renderSelection();
    }));
    clear.addEventListener('click', resetSelection);
    window.addEventListener('pageshow', resetSelection);
    resetSelection();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSellerProductListing, { once: true });
else initSellerProductListing();
</script>
@endpush
