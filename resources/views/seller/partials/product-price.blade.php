@if(!empty($product['is_variable']))
    @if($product['price'] === null)
        No active variations
    @else
        ₱{{ number_format($product['price'], 2) }}@if($product['price_max'] > $product['price']) – ₱{{ number_format($product['price_max'], 2) }}@endif
    @endif
@else
    @if(($showSale ?? true) && !empty($product['on_sale']))
        <span class="mr-1 text-[10px] font-medium text-[#89968F] line-through">₱{{ number_format((float) ($product['regular_price'] ?? 0), 2) }}</span>
    @endif
    ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
@endif
