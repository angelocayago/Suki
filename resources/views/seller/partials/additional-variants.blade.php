<div class="spf-variant-heading"><div><strong>Color and size variants</strong><p>Each row is a separate combination with its own price and quantity.</p></div><button type="button" id="addProductVariant">Add variant</button></div>
<div id="productVariantRows">
    @foreach($variantRows as $index => $variant)
        @include('seller.partials.product-variant-row')
    @endforeach
</div>
<p>Existing variants are retained. To stop offering one, set it to Inactive.</p>
<template id="productVariantTemplate">
    @include('seller.partials.product-variant-row', ['index'=>'__INDEX__', 'variant'=>[]])
</template>
