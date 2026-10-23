<div class="mt-1 text-xs text-[#68776F]">
    @unless($variantOnly ?? false)
        {{ ucwords(str_replace('-', ' ', $product['stock_status'])) }} · {{ $product['stock'] }} sellable units
    @endunless
    @if($product['is_variable'])
        <details class="mt-1"><summary class="cursor-pointer text-[#1F6F5B]">Variant stock · {{ $product['low_stock_variants'] }} low-stock alerts</summary>
            @foreach($product['variant_rows'] as $variant)
                <p class="mt-1">{{ $variant['sku'] }} {{ implode(' / ', $variant['options']) }}: {{ $variant['stock'] }} — {{ $variant['is_active'] ? ucwords(str_replace('-', ' ', \App\Support\SellerStock::status($variant['stock'], $product['low_stock_threshold']))) : 'Inactive (excluded)' }}</p>
                @if($variant['is_active'] && request()->routeIs('seller.inventory'))
                    <div class="my-2 flex gap-2">
                        @foreach(['decrease' => '−1', 'increase' => '+1'] as $action => $label)
                            <form method="POST" action="{{ route('seller.inventory.'.$action, $product['id']) }}">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $variant['id'] }}">
                                <button class="rounded border border-[#DDE6E1] px-3 py-1 font-semibold text-[#1F6F5B] disabled:opacity-40" aria-label="{{ ucfirst($action) }} stock for {{ $variant['sku'] }}" @disabled($action === 'decrease' && $variant['stock'] === 0)>{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </details>
    @endif
</div>
