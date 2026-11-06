@php
    $isWishlisted = in_array($product['slug'], $wishlistSlugs ?? [], true);
    $inStock = collect($product['variants'])->contains(fn ($variant) => $variant['stock'] > 0);
@endphp
<article class="catalog-product-card group relative bg-white rounded-xl overflow-hidden border border-gray-200 hover:border-[#D5EAE2] hover:shadow-lg hover:-translate-y-0.5 transition duration-200">
    <a href="{{ route('buyer.product', $product['slug']) }}" class="catalog-product-image">
        <img loading="lazy" width="400" height="400" src="{{ $product['image'] }}" alt="{{ $product['name'] }}">
        @if(!$inStock)<span class="catalog-stock-badge">Out of stock</span>@endif
    </a>
    @if(auth()->user()?->isBuyer())
        <form action="{{ route('buyer.wishlist.toggle', $product['slug']) }}" method="POST" class="catalog-wishlist">
            @csrf
            <button type="submit" title="{{ $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist' }}" aria-label="{{ $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist' }}: {{ $product['name'] }}" aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}" class="{{ $isWishlisted ? 'is-saved' : '' }}">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="{{ $isWishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>
            </button>
        </form>
    @elseif(!auth()->check())
        <a href="{{ route('login') }}" class="catalog-wishlist catalog-save-link" aria-label="Sign in to save {{ $product['name'] }} to Wishlist">Save</a>
    @endif
    <a href="{{ route('buyer.product', $product['slug']) }}" class="catalog-product-body">
        <p class="catalog-product-category">{{ $product['category'] }}</p>
        <h3 class="line-clamp-2">{{ $product['name'] }}</h3>
        <p class="catalog-product-price">@if($product['price_from'] ?? false)<span>From </span>@endif&#8369;{{ number_format($product['price'], 2) }}</p>
        <div class="catalog-product-meta">
            @if($product['ratings'])<span><span class="catalog-star" aria-hidden="true">&#9733;</span> {{ number_format($product['rating'], 1) }} ({{ $product['ratings'] }})</span>@else<span class="catalog-unrated">No ratings yet</span>@endif
            <span>{{ $product['sold'] }} sold</span>
        </div>
        <p class="catalog-product-shipping"><i data-lucide="truck" class="w-3.5 h-3.5"></i> &#8369;50 shipping per seller</p>
    </a>
</article>
