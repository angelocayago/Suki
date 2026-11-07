@php
    $selected = in_array((int) $cartItemId, $selection['ids'], true);
    $options = collect($item['variation'] ?? [])->map(fn($value, $key) => $key.': '.$value)->implode(' / ');
    $showVariant = $item['variant'] && !collect($item['variation'] ?? [])->contains(fn($value) => mb_strtolower(trim((string) $value)) === mb_strtolower(trim($item['variant'])));
    $quantityError = (string) session('cart_error_line') === (string) $cartItemId ? $errors->first() : '';
@endphp
<article id="cart-line-{{ $cartItemId }}" class="cart-row {{ $selected ? 'is-selected' : '' }}" data-cart-row="{{ $cartItemId }}">
    <label class="cart-check-target cart-row-check">
        <input type="checkbox" name="cart_item_ids[]" value="{{ $cartItemId }}" form="cart-selection-form" data-cart-select data-store="{{ $storeKey }}" data-unit-price-minor="{{ $item['price_minor'] }}" data-quantity="{{ $item['quantity'] }}" data-eligible="{{ $item['available'] ? '1' : '0' }}" @checked($selected) @disabled(!$item['available'] && !$selected) aria-describedby="cart-availability-{{ $cartItemId }}">
        <span class="sr-only">Select {{ $item['name'] }}{{ $item['variant'] ? ' — '.$item['variant'] : '' }}</span>
    </label>
    <a href="{{ route('buyer.product', $item['slug']) }}" class="cart-product-image">
        @if($item['image'])<img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" loading="lazy">@else<span>No image</span>@endif
    </a>
    <div class="cart-row-body">
        <div class="cart-row-heading">
            <a href="{{ route('buyer.product', $item['slug']) }}" class="cart-product-name">{{ $item['name'] }}</a>
            <form action="{{ route('buyer.cart.remove', ['cartItem' => $cartItemId]) }}" method="POST">@csrf
                <button type="submit" class="cart-icon-button" aria-label="Remove {{ $item['name'] }}{{ $item['variant'] ? ' — '.$item['variant'] : '' }}"><i data-lucide="trash-2" aria-hidden="true"></i></button>
            </form>
        </div>
        @if($showVariant)<p class="cart-muted">Variant: {{ $item['variant'] }}</p>@endif
        @if($options)<p class="cart-muted">{{ $options }}</p>@endif
        <p class="cart-unit-price">₱{{ number_format($item['price'], 2) }} <span class="cart-muted">each</span></p>
        <p id="cart-availability-{{ $cartItemId }}" class="{{ $item['available'] ? 'cart-muted' : 'cart-warning' }}" data-cart-availability>
            @if($item['available'])
                {{ $item['stock'] }} available
            @else
                Unavailable: {{ $item['availability_issue'] }}
            @endif
        </p>
        <div class="cart-row-actions">
            <div>
                <div class="cart-stepper">
                    <form action="{{ route('buyer.cart.decrease', ['cartItem' => $cartItemId]) }}" method="POST">@csrf<button type="submit" aria-label="Decrease quantity for {{ $item['name'] }}">−</button></form>
                    <form action="{{ route('buyer.cart.update', ['cartItem' => $cartItemId]) }}" method="POST" data-cart-quantity-form>@csrf
                        <input type="text" inputmode="numeric" name="quantity" value="{{ $item['quantity'] }}" data-buyer-quantity data-cart-quantity data-last-valid="{{ $item['quantity'] }}" data-stock="{{ $item['stock'] }}" aria-label="Quantity for {{ $item['name'] }}" aria-invalid="{{ !$item['available'] || $quantityError ? 'true' : 'false' }}" aria-describedby="cart-quantity-error-{{ $cartItemId }}">
                        <button type="submit" class="sr-only">Update quantity</button>
                    </form>
                    <form action="{{ route('buyer.cart.increase', ['cartItem' => $cartItemId]) }}" method="POST">@csrf<button type="submit" data-cart-increase aria-label="Increase quantity for {{ $item['name'] }}" @disabled(!$item['available'] || $item['quantity'] >= $item['stock'])>+</button></form>
                </div>
                <p id="cart-quantity-error-{{ $cartItemId }}" data-quantity-error role="alert" class="cart-warning">{{ $quantityError }}</p>
            </div>
            <form action="{{ route('buyer.wishlist.add', ['slug' => $item['slug']]) }}" method="POST">@csrf<input type="hidden" name="cart_item_id" value="{{ $cartItemId }}"><button type="submit" class="cart-text-button">♡ Move to Wishlist</button></form>
            <p class="cart-line-total"><span class="cart-muted">Item total</span><strong>₱{{ number_format($item['price'] * $item['quantity'], 2) }}</strong></p>
        </div>
    </div>
</article>
