@extends('layouts.app')
@push('head')
<meta name="description" content="{{ Str::limit(strip_tags($seller->description ?: 'Explore products from '.$seller->name.' on SUKI SHOP.'), 155) }}">
<link rel="canonical" href="{{ route('buyer.stores.show', $seller) }}">
<meta property="og:title" content="{{ $seller->name }} | SUKI SHOP">
<meta property="og:description" content="{{ Str::limit(strip_tags($seller->description ?: 'Discover this shop on SUKI SHOP.'), 155) }}">
<meta property="og:url" content="{{ route('buyer.stores.show', $seller) }}">
@if($seller->banner_path)<meta property="og:image" content="{{ asset('storage/'.$seller->banner_path) }}">@endif
@include('buyer.store-style')
@endpush
@section('content')
@php
    $storeUrl = route('buyer.stores.show', $seller);
    $hasFilters = trim($filters['q']) !== '' || $filters['category'] !== '' || $filters['sort'] !== 'newest';
    $imageUrl = fn ($path) => $path ? (Str::startsWith($path, ['https://', 'http://']) ? $path : Storage::disk('public')->url($path)) : null;
@endphp
<div class="storefront" data-storefront>
    @if($shopVouchers->isNotEmpty())<section class="sf-notice" aria-label="Public shop vouchers"><h2>Shop Vouchers</h2>@foreach($shopVouchers as $shopVoucher)<p><strong>{{ $shopVoucher->code }}</strong> ? {{ $shopVoucher->discount_type==='PERCENTAGE' ? \App\Support\SellerVouchers::decimal($shopVoucher->discount_bps).'%' : 'PHP '.number_format($shopVoucher->discount_minor/100,2) }} off ? Min PHP {{ number_format($shopVoucher->minimum_spend_minor/100,2) }}</p>@endforeach<a class="underline" href="{{ route('buyer.vouchers.index') }}">View and claim vouchers</a></section>@endif
    <div class="sf-breadcrumb"><a href="{{ route('buyer.shop') }}">Marketplace</a><span aria-hidden="true">/</span><span>{{ $seller->name }}</span></div>
    @if(session('success'))<p class="sf-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="sf-notice" role="alert">{{ $errors->first() }}</div>@endif
    <section class="sf-hero" aria-labelledby="sfStoreName">
        <div class="sf-banner">@if($seller->banner_path)<img data-sf-image src="{{ asset('storage/'.$seller->banner_path) }}" alt="{{ $seller->name }} banner">@endif</div>
        <div class="sf-shop-content">
            <div class="sf-logo"><span>{{ mb_strtoupper(mb_substr($seller->name,0,1)) }}</span>@if($seller->logo_path)<img data-sf-image src="{{ asset('storage/'.$seller->logo_path) }}" alt="{{ $seller->name }} store logo">@endif</div>
            <div class="sf-shop-main"><p class="sf-eyebrow">{{ $seller->category?->name ?: 'Independent store' }}</p><h1 id="sfStoreName">{{ $seller->name }}</h1><span class="sf-approved">? Approved store</span>@if($seller->description)<p class="sf-intro">{{ Str::limit($seller->description, 230) }}</p>@endif</div>
            <div class="sf-actions">
                @auth @if(auth()->user()->isBuyer())
                <form method="POST" action="{{ $following ? route('buyer.stores.unfollow', $seller) : route('buyer.stores.follow', $seller) }}">@csrf @if($following) @method('DELETE') @endif<button class="sf-button" type="submit">{{ $following ? 'Following ? Unfollow' : '+ Follow store' }}</button></form>
                @endif @endauth
                <button class="sf-button sf-secondary" type="button" data-sf-share data-url="{{ $storeUrl }}" data-title="{{ $seller->name }}" hidden>Share store ?</button>
                <a class="sf-text-link" href="#products">Ask about a product ?</a>
                <span class="sf-feedback" data-sf-share-status role="status" aria-live="polite"></span>
            </div>
        </div>
        <div class="sf-stats"><div><strong>{{ number_format($visibleCount) }}</strong><span>Available {{ Str::plural('product', $visibleCount) }}</span></div><div>@if($seller->visible_reviews_count)<strong>{{ number_format((float)$seller->visible_reviews_avg_rating,1) }} <span aria-label="out of five stars">/ 5</span></strong>@else<strong>No reviews yet</strong>@endif<span>{{ number_format($seller->visible_reviews_count) }} public {{ Str::plural('review', $seller->visible_reviews_count) }}</span></div>@if($seller->created_at)<div><strong>{{ $seller->created_at->format('M Y') }}</strong><span>On SUKI SHOP since</span></div>@endif</div>
    </section>
    <nav class="sf-nav" aria-label="Store sections"><a href="#products" aria-current="location">Products</a><a href="#about">About the store</a><a href="#reviews">Reviews <span>{{ $seller->visible_reviews_count }}</span></a></nav>
    <section id="products" class="sf-section" aria-labelledby="sfProductsTitle">
        <header class="sf-section-title"><div><p class="sf-eyebrow">Explore the collection</p><h2 id="sfProductsTitle">Shop products</h2></div><p>{{ $products->total() }} matching {{ Str::plural('product', $products->total()) }}</p></header>
        <form class="sf-filters" method="GET" action="{{ $storeUrl }}#products">
            <div class="sf-search"><label for="sfSearch">Search this store</label><input id="sfSearch" name="q" maxlength="120" placeholder="What are you looking for?" value="{{ $filters['q'] }}" type="search"></div>
            <div><label for="sfCategory">Category</label><select id="sfCategory" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)$filters['category'] === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div><label for="sfSort">Sort by</label><select id="sfSort" name="sort"><option value="newest" @selected($filters['sort']==='newest')>Newest first</option><option value="price_asc" @selected($filters['sort']==='price_asc')>Price: low to high</option><option value="price_desc" @selected($filters['sort']==='price_desc')>Price: high to low</option></select></div>
            <button class="sf-button" type="submit">Apply filters</button>
            @if($hasFilters)<a class="sf-text-link" href="{{ $storeUrl }}#products">Clear filters</a>@endif
        </form>
        <div class="sf-grid">
        @forelse($products as $product)
            @php $price=$product->variants->min('price_minor'); $maxPrice=$product->variants->max('price_minor'); $image=$imageUrl($product->images->first()?->path); @endphp
            <article class="sf-product">
                <a class="sf-product-link" href="{{ route('buyer.product', $product->slug) }}">
                    <div class="sf-product-image"><span aria-hidden="true">SUKI<br><small>Image unavailable</small></span>@if($image)<img data-sf-image src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" width="400" height="400">@endif</div>
                    <div class="sf-product-info"><p class="sf-product-category">{{ $product->category?->name ?: 'Uncategorized' }}</p><h3>{{ $product->name }}</h3><p class="sf-price">@if($price!==$maxPrice)<small>From </small>@endif?{{ number_format($price/100,2) }}</p><div class="sf-product-meta"><span class="sf-stock">? In stock</span>@if($product->variants->count()>1)<span>{{ $product->variants->count() }} variants</span>@endif</div><span class="sf-view">View product <span aria-hidden="true">?</span></span></div>
                </a>
                @if(auth()->guest() || auth()->user()->isBuyer())<div class="sf-product-chat">@guest<a href="{{ route('login') }}">Sign in to chat with seller</a>@else<form method="POST" action="{{ route('buyer.messages.context.product.store', $product) }}">@csrf<button type="submit" aria-label="Ask seller about {{ $product->name }}">Chat about this product</button></form>@endguest</div>@endif
            </article>
        @empty
            <div class="sf-empty"><span aria-hidden="true">?</span><h3>{{ $visibleCount ? 'No products match your filters.' : 'No published products available.' }}</h3><p>{{ $visibleCount ? 'Try a different search or browse the full collection.' : 'Check back soon for available products from this store.' }}</p>@if($hasFilters)<a class="sf-button" href="{{ $storeUrl }}#products">Clear filters</a>@endif</div>
        @endforelse
        </div>
        <div class="sf-pagination">{{ $products->links() }}</div>
    </section>
    <div class="sf-details">
        <section id="about" class="sf-panel sf-section" aria-labelledby="sfAboutTitle"><p class="sf-eyebrow">Meet the store</p><h2 id="sfAboutTitle">About {{ $seller->name }}</h2><p class="sf-description">{{ $seller->description ?: 'This store has not added a description yet.' }}</p><dl><div><dt>Category</dt><dd>{{ $seller->category?->name ?: 'Not specified' }}</dd></div>@if($seller->created_at)<div><dt>Joined</dt><dd>{{ $seller->created_at->format('F Y') }}</dd></div>@endif<div><dt>Store status</dt><dd>Approved</dd></div></dl></section>
        <section id="reviews" class="sf-panel sf-section" aria-labelledby="sfReviewsTitle"><p class="sf-eyebrow">From the community</p><h2 id="sfReviewsTitle">Store reviews <span>({{ $seller->visible_reviews_count }})</span></h2>
            @forelse($reviews as $review)<article class="sf-review"><header><strong>{{ $review->rating }} / 5 <span aria-hidden="true">?</span></strong>@if($review->created_at)<time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('M j, Y') }}</time>@endif</header><p>{{ $review->body }}</p>@if($review->seller_response)<blockquote><strong>Store reply</strong><p>{{ $review->seller_response }}</p></blockquote>@endif</article>@empty<div class="sf-review-empty"><h3>No reviews yet.</h3><p>Feedback from buyers will appear here.</p></div>@endforelse
            <div class="sf-pagination">{{ $reviews->links() }}</div>
        </section>
    </div>
</div>
@include('buyer.store-script')
@endsection
