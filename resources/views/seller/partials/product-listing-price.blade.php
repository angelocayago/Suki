@if(empty($product['is_variable']) && $product['price'] === null)
    <span class="text-[11px] font-normal text-[#89968F]">No active price</span>
@else
    @include('seller.partials.product-price')
@endif
