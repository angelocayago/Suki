@php
    $variantError = (string) session('cart_variant_line') === (string) $cartItemId ? $errors->first() : '';
    $hasSelectable = collect($choices)->contains(fn ($choice) => !$choice['reason']);
@endphp
<details class="cart-variant-picker" data-cart-variant-picker @if($variantError) open @endif>
    <summary aria-label="Change variant for {{ $item['name'] }}">
        <span class="cart-muted">
            @if($showVariant)Variant: {{ $item['variant'] }}@if($options)<br>@endif @endif
            {{ $options ?: (!$showVariant ? ($item['variant'] ?: 'Variant') : '') }}
        </span>
        <span class="cart-variant-change">Change</span>
    </summary>
    <form method="POST" action="{{ route('buyer.cart.change-variant', ['cartItem' => $cartItemId]) }}" data-cart-variant-form class="cart-variant-panel">
        @csrf
        <input type="hidden" name="draft_revision" value="{{ $selection['revision'] }}" data-variant-revision>
        <fieldset aria-describedby="cart-variant-error-{{ $cartItemId }}" @if($variantError) aria-invalid="true" @endif>
            <legend>Choose variant</legend>
            @foreach($choices as $choice)
                <input type="hidden" name="variant_proof[{{ $choice['id'] }}]" value="{{ $choice['proof'] }}">
                <label class="cart-variant-option">
                    <input type="radio" name="variant_id" value="{{ $choice['id'] }}" required @disabled((bool) $choice['reason']) aria-describedby="cart-variant-option-{{ $cartItemId }}-{{ $choice['id'] }}">
                    <span>{{ $choice['label'] ?: 'Option '.$choice['id'] }} — ₱{{ number_format($choice['price_minor'] / 100, 2) }}
                        <span id="cart-variant-option-{{ $cartItemId }}-{{ $choice['id'] }}" class="{{ $choice['reason'] ? 'cart-warning' : 'cart-muted' }}">{{ $choice['reason'] ?: $choice['stock'].' available' }}</span>
                    </span>
                </label>
            @endforeach
        </fieldset>
        <p id="cart-variant-error-{{ $cartItemId }}" class="cart-warning" role="alert">{{ $variantError }}</p>
        <div class="cart-variant-buttons">
            <a href="{{ route('buyer.cart') }}#cart-line-{{ $cartItemId }}" class="cart-button cart-outline" data-cart-variant-cancel>Cancel</a>
            <button type="submit" class="cart-button cart-primary" @disabled(!$hasSelectable)>Confirm</button>
        </div>
    </form>
</details>
