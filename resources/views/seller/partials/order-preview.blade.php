<div class="mt-3 w-72 max-w-full rounded-xl border border-[#E1E8E4] bg-[#F8FAF8] p-3" aria-label="Read-only seller order preview">
    <h4 class="font-semibold text-[#24312C]">{{ $segment->display_reference ?: 'Not recorded' }}</h4>
    <p class="mt-1">Buyer: {{ $segment->recipient_name ?: 'Not recorded' }}</p>
    <p>Date Placed: {{ $date?->format('M j, Y g:i A') ?? 'Not recorded' }}{{ $date ? ' PHT' : '' }}</p>
    <p class="mt-2">{{ $segment->seller_order_id ? 'Seller fulfillment' : 'Order fulfillment' }}: {{ $statusLabel }}</p>
    @if($segment->seller_order_id)<p>Parent order status: {{ $segment->parent_status ?: 'Not recorded' }}</p>@endif
    <p>Saved payment method: {{ $segment->payment_method ?: 'Not recorded' }}</p>
    <ul class="mt-3 space-y-3">
        @foreach($segment->items as $item)
            <li data-order-item="{{ $item->id }}" class="break-words border-t border-[#DDE6E1] pt-2">
                <p class="font-semibold">{{ $item->product_name ?: 'Not recorded' }}</p>
                @if($item->variant_name)<p>{{ $item->variant_name }}</p>@endif
                @if(is_array($item->variation))
                    @foreach(\Illuminate\Support\Arr::dot($item->variation) as $option => $value)<p>{{ $option }}: {{ is_scalar($value) ? $value : '' }}</p>@endforeach
                @elseif($item->variation)<p>{{ $item->variation }}</p>@endif
                <p>{{ $item->quantity }} × ₱{{ number_format($item->unit_price_minor !== null ? $item->unit_price_minor / 100 : $item->unit_price, 2) }} = ₱{{ number_format($item->gross_amount, 2) }}</p>
            </li>
        @endforeach
    </ul>
    <p class="mt-3 font-semibold">Seller Item Gross: ₱{{ number_format($segment->gross_amount, 2) }}</p>
    <p class="mt-2 text-[10px]">Item gross excludes shipping and unallocated discounts. Fulfillment and payment method do not confirm payment receipt.</p>
</div>
