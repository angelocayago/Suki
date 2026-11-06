@php
    $filters = $catalogContext['filters'];
    $selected = $catalogContext['selected'];
    $categoryIndex = $catalogContext['categories']->keyBy('id');
    $categoryLabel = count($selected) === 1 ? $categoryIndex->get($selected[0])?->name : (count($selected) ? count($selected).' categories' : null);
    $breadcrumb = []; $cursor = count($selected) === 1 ? $categoryIndex->get($selected[0]) : null; $visited = [];
    while ($cursor && !isset($visited[$cursor->id])) { $visited[$cursor->id] = true; array_unshift($breadcrumb, $cursor); $cursor = $categoryIndex->get($cursor->parent_id); }
    $query = array_filter($filters, fn ($value) => $value !== null && $value !== ''); unset($query['page'], $query['category']);
    if ($selected) $query['categories'] = $selected; else unset($query['categories']);
    $filterChips = [];
    foreach ($selected as $id) { $removed = $query; $removed['categories'] = array_values(array_diff($selected, [$id])); $filterChips[] = ['label' => $categoryIndex->get($id)?->name ?? 'Category', 'url' => route('buyer.shop', $removed)]; }
    foreach (['q' => 'Search: ', 'seller' => 'Store: ', 'in_stock' => 'In stock', 'rating' => ''] as $key => $prefix) {
        if (empty($filters[$key])) continue;
        $removed = $query; unset($removed[$key]);
        $label = $key === 'rating' ? ((int) $filters[$key] === 5 ? '5 stars' : $filters[$key].' stars & up') : ($key === 'in_stock' ? $prefix : $prefix.$filters[$key]);
        $filterChips[] = ['label' => $label, 'url' => route('buyer.shop', $removed)];
    }
    if (filled($filters['min_price'] ?? null) || filled($filters['max_price'] ?? null)) { $removed = $query; unset($removed['min_price'], $removed['max_price']); $filterChips[] = ['label' => 'PHP '.($filters['min_price'] ?? '0').' - '.($filters['max_price'] ?? 'Any'), 'url' => route('buyer.shop', $removed)]; }
    $ratingLabel = !empty($filters['rating']) ? ((int) $filters['rating'] === 5 ? '5 stars' : $filters['rating'].' stars & up') : null;
@endphp
@include('buyer.partials.catalog-active-filters')
<div class="catalog-results-toolbar bg-white border border-gray-200 rounded-xl px-4 py-3 mb-5">
    <p><strong data-catalog-count>{{ $products->total() }}</strong> products {{ $categoryLabel ? 'in '.$categoryLabel : 'available' }}@if($ratingLabel) with {{ $ratingLabel }}@endif</p>
    <div class="catalog-toolbar-actions">
        <button type="button" data-catalog-mobile-filters aria-controls="catalogSidebar" aria-expanded="false" class="catalog-mobile-only">Filters <span data-catalog-filter-count>{{ count($filterChips) }}</span></button>
        <label>Sort by <select data-catalog-sort aria-label="Sort products">@foreach(['newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'best_selling' => 'Best-selling', 'name' => 'Name'] as $value => $label)<option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>@endforeach</select></label>
    </div>
</div>
<div class="catalog-product-grid grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
    @forelse($products as $product)
        @include('buyer.partials.catalog-product-card')
    @empty
        <div class="catalog-empty col-span-full">
            <i data-lucide="search" class="w-8 h-8" aria-hidden="true"></i>
            <h2>No products found{{ $categoryLabel ? ' in '.$categoryLabel : '' }}{{ $ratingLabel ? ' with '.$ratingLabel : '' }}.</h2>
            <p>No products match these filters. Try removing a filter.</p>
            <a href="{{ route('buyer.shop') }}" data-catalog-reset>Clear filters</a>
        </div>
    @endforelse
</div>
@if($products->hasPages())<div class="mt-10" data-catalog-pagination>{{ $products->links() }}</div>@endif
