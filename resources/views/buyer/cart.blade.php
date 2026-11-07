@extends('layouts.app')

@section('content')
<section class="buyer-cart" data-buyer-cart>
    <header class="cart-header">
        <div><nav aria-label="Breadcrumb" class="cart-muted"><a href="{{ route('buyer.home') }}">Home</a> / Shopping Cart</nav><h1>Shopping Cart</h1><p class="cart-muted">Choose the items you want to check out.</p></div>
        <a href="{{ route('buyer.shop') }}" class="cart-text-button">← Continue Shopping</a>
    </header>
    @if(session('success'))<p role="status" class="cart-notice">{{ session('success') }}</p>@endif
    @if(session('error'))<p role="alert" class="cart-warning">{{ session('error') }}</p>@endif
        @if($reorder = session('reorder_result'))
        <section class="mb-6 rounded-2xl border border-[#1F6F5B]/20 bg-[#EEF8F3] p-5" aria-labelledby="reorder-result-title" role="status">
            <h2 id="reorder-result-title" class="font-semibold text-[#155244]">Buy Again results</h2>
            <p class="mt-2 text-sm text-gray-700">
                @if($reorder['added_units'] > 0)
                    Added {{ $reorder['added_units'] }} unit(s) from {{ $reorder['added_lines'] }} order item(s) to your Cart.
                @else
                    No items were added to your Cart.
                @endif
                {{ collect($reorder['items'])->where('status', 'skipped')->count() }} skipped;
                {{ collect($reorder['items'])->where('status', 'adjusted')->count() }} adjusted due to stock.
                Current prices apply. Availability and vouchers are checked again at Checkout.
            </p>
            <ul class="mt-3 space-y-3 text-sm text-gray-700">
                @foreach($reorder['items'] as $result)
                    <li>
                        <span class="font-semibold">{{ $result['name'] }}</span>
                        @if(!empty($result['options']))
                            <span>({{ collect($result['options'])->map(fn ($value, $name) => $name.': '.$value)->implode(' / ') }})</span>
                        @endif
                        <span class="block">{{ $result['message'] }}</span>
                        @if($result['status'] !== 'added' && $result['url'])
                            <a href="{{ $result['url'] }}" class="mt-1 inline-block font-semibold text-[#155244] underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">View product and select options</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    @if(!count($cart))
        <div class="cart-empty"><h2>Your Cart is empty</h2><p class="cart-muted">Find something you love in the Shop.</p><a href="{{ route('buyer.shop') }}" class="cart-button cart-primary">Continue Shopping</a></div>
    @else
        <form id="cart-selection-form" method="POST" action="{{ route('buyer.cart.selection') }}">
            @csrf
            <input type="hidden" name="draft_revision" value="{{ $selection['revision'] }}" data-draft-revision>
        </form>
        <p data-cart-selection-status role="status" aria-live="polite" class="cart-selection-status"></p>
        <div class="cart-layout">
            <div class="cart-list">
                <div class="cart-selection-toolbar">
                    <label class="cart-check-target cart-enhanced-control" hidden><input type="checkbox" data-cart-all><span>Select all</span></label>
                    <span>{{ count($cart) }} Cart item{{ count($cart) === 1 ? '' : 's' }}</span>
                    <button type="submit" form="cart-selection-form" name="intent" value="update" class="cart-button cart-outline" data-update-selection>Update Selection</button>
                </div>
                <noscript><p class="cart-notice">Tick individual items, then choose Update Selection to refresh totals or Checkout selected items to continue. Save your selection before changing quantities, removing items, moving to Wishlist or applying a voucher.</p></noscript>
                @foreach(collect($cart)->groupBy(fn($item) => $item['seller_id'] ?? 'unavailable', true) as $storeKey => $storeItems)
                    <section class="cart-store" aria-labelledby="cart-store-{{ $storeKey }}">
                        <header class="cart-store-header">
                            <label class="cart-check-target cart-enhanced-control" hidden><input type="checkbox" data-cart-store="{{ $storeKey }}"><span class="sr-only">Select all available items from {{ $storeItems->first()['seller_name'] ?? 'unavailable shop' }}</span></label>
                            <div><h2 id="cart-store-{{ $storeKey }}">@if($storeItems->first()['seller_slug'])<a href="{{ route('buyer.stores.show', $storeItems->first()['seller_slug']) }}">{{ $storeItems->first()['seller_name'] }}</a>@else Unavailable shop @endif</h2><p class="cart-muted"><span data-store-count="{{ $storeKey }}">{{ $storeItems->filter(fn($item) => $item['available'] && in_array($item['id'], $selection['ids'], true))->count() }}</span> eligible items selected</p></div>
                        </header>
                        @foreach($storeItems as $cartItemId => $item)
                            @include('buyer.partials.cart-row')
                        @endforeach
                        <p class="cart-store-delivery cart-muted">₱{{ number_format($selected_summary['shipping_per_seller'], 2) }} delivery per participating seller shipment.</p>
                    </section>
                @endforeach
                <p class="cart-notice">Shop with confidence. Your order is protected through SUKI SHOP’s secure checkout process.</p>
            </div>
            <aside class="cart-summary" aria-label="Order Summary">
                <details open data-cart-summary-details>
                    <summary>Order Summary <span class="cart-muted">Selected items</span></summary>
                    <div data-cart-summary aria-live="polite">@include('buyer.partials.cart-summary')</div>
                </details>
            </aside>
        </div>
        <div class="cart-mobile-bar" data-mobile-bar>
            <label class="cart-check-target cart-enhanced-control" hidden><input type="checkbox" data-cart-all><span>All</span></label>
            <div><span class="cart-muted" data-mobile-count>{{ count($selection['ids']) }} selected</span><strong data-mobile-total>₱{{ number_format($selected_summary['total'], 2) }}</strong></div>
            <button type="submit" form="cart-selection-form" name="intent" value="checkout" class="cart-button cart-primary" data-cart-checkout aria-describedby="cart-checkout-reason" @disabled(!count($selection['ids']) || $selected_summary['has_unavailable_items'] || $selected_summary['voucher_error'])>Checkout</button>
        </div>
    @endif
</section>
@endsection
