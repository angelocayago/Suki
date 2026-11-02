<div class="spf-variant-heading">
    <div>
        <strong>Product Variants</strong>
        <p>Each combination keeps its own price and stock. Saved custom option names are retained.</p>
    </div>
    <button type="button" id="addProductVariant">+ Add Variant</button>
</div>

<div class="spf-variant-chip-panel" id="variantOptionChips" aria-label="Variant summary" aria-live="polite"><span class="spf-variant-empty">No options yet</span></div>

<div id="productVariantRows" class="spf-variant-list">
    @foreach($variantRows as $index => $variant)
        @include('seller.partials.product-variant-row')
    @endforeach
</div>

<p>Existing variants are retained. To stop offering one, set it to Inactive.</p>

<template id="productVariantTemplate">
    @include('seller.partials.product-variant-row', ['index'=>'__INDEX__', 'variant'=>[]])
</template>
