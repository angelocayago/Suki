@if($breadcrumb)
<nav aria-label="Product category breadcrumb" class="catalog-breadcrumb"><a href="{{ route('buyer.shop') }}" data-catalog-reset>Shop</a>@foreach($breadcrumb as $crumb)<span aria-hidden="true">&rsaquo;</span><a href="{{ route('buyer.shop', array_merge($query, ['categories' => [$crumb->id]])) }}" data-catalog-filter-link>{{ $crumb->name }}</a>@endforeach</nav>
@endif
@if($filterChips)
<div class="catalog-active-filters" aria-label="Active filters">
    @foreach($filterChips as $chip)<a href="{{ $chip['url'] }}" data-catalog-filter-link aria-label="Remove {{ $chip['label'] }}">{{ $chip['label'] }} <span aria-hidden="true">&times;</span></a>@endforeach
    <a href="{{ route('buyer.shop') }}" data-catalog-reset class="catalog-clear-all">Clear all</a>
</div>
@endif
