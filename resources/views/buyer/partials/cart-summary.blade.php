@php
    $summary = $selected_summary;
    $selectedCount = count($selection['ids']);
    $blocked = !$selectedCount || $summary['has_unavailable_items'] || $summary['voucher_error'];
@endphp
<div class="cart-summary-content" data-summary-content>
    <p class="cart-muted" data-selected-count>{{ $selectedCount }} selected item{{ $selectedCount === 1 ? '' : 's' }}</p>
    <dl class="cart-totals">
        <div><dt>Merchandise Subtotal</dt><dd data-selected-subtotal>₱{{ number_format($summary['subtotal'], 2) }}</dd></div>
        <div><dt>Shipping <small data-selected-sellers>({{ $summary['seller_count'] }} seller{{ $summary['seller_count'] === 1 ? '' : 's' }})</small></dt><dd data-selected-shipping>₱{{ number_format($summary['shipping'], 2) }}</dd></div>
        @if($summary['discount'] > 0)
            <div><dt>Voucher discount</dt><dd data-selected-discount>−₱{{ number_format($summary['discount'], 2) }}</dd></div>
        @endif
        <div class="cart-grand-total"><dt>Total</dt><dd data-selected-total>₱{{ number_format($summary['total'], 2) }}</dd></div>
    </dl>
    <p class="cart-muted">₱{{ number_format($summary['shipping_per_seller'], 2) }} delivery per participating seller shipment. Current prices and availability are checked again at Checkout.</p>
    <form method="POST" action="{{ route('buyer.cart.vouchers.apply') }}" class="cart-voucher-form">
        @csrf
        <label class="sr-only" for="cart-voucher">Voucher code</label>
        <input id="cart-voucher" name="voucher_code" value="{{ old('voucher_code') }}" required placeholder="Voucher code" maxlength="64">
        <button class="cart-button cart-primary" type="submit">Apply</button>
    </form>
    @if($summary['voucher_error'])
        <p class="cart-warning" data-voucher-result>Not applied: {{ $summary['voucher_error'] }}</p>
    @endif
    @foreach($summary['voucher_codes'] as $voucherCode)
        @php($applied = collect($summary['applied_vouchers'])->firstWhere('code', $voucherCode))
        <div class="cart-voucher-line">
            <span>Voucher {{ $voucherCode }}
                @if($applied)
                    <span data-voucher-result>· −₱{{ number_format($applied['discount'], 2) }}</span>
                @else
                    <span data-voucher-result>· Not applied</span>
                @endif
            </span>
            <form method="POST" action="{{ route('buyer.cart.vouchers.remove', $voucherCode) }}">@csrf @method('DELETE')<button class="cart-text-button" type="submit">Remove</button></form>
        </div>
    @endforeach
    <p id="cart-checkout-reason" class="cart-warning" data-checkout-reason>
        @if(!$selectedCount)Select at least one item to check out.@elseif($summary['has_unavailable_items']){{ $selected_invalid_message }}@elseif($summary['voucher_error'])Remove or correct the voucher before checkout.@endif
    </p>
    <button type="submit" form="cart-selection-form" name="intent" value="checkout" class="cart-button cart-primary cart-checkout" data-cart-checkout aria-describedby="cart-checkout-reason" @disabled($blocked) aria-disabled="{{ $blocked ? 'true' : 'false' }}">Checkout selected items</button>
    <noscript><button type="submit" form="cart-selection-form" name="intent" value="checkout" class="cart-button cart-primary cart-checkout">Checkout checked items</button></noscript>
    <a class="cart-button cart-outline cart-checkout" href="{{ route('buyer.shop') }}">Continue Shopping</a>
    <p class="cart-muted cart-secure">Secure checkout · Buyer protection</p>
</div>
