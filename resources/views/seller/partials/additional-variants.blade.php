<div class="spf-variant-heading">
    <div>
        <strong>Product Variants</strong>
        <p>Add color and size combinations with their own price and stock.</p>
    </div>
    <button type="button" id="addProductVariant">+ Add Variant</button>
</div>

<div class="spf-variant-chip-panel" aria-label="Variant summary">
    <div class="spf-variant-chip-group">
        <span class="spf-variant-chip-label">Colors</span>
        <div id="variantColorChips" class="spf-variant-chips">
            <span class="spf-variant-empty">No colors yet</span>
        </div>
    </div>

    <div class="spf-variant-chip-group">
        <span class="spf-variant-chip-label">Sizes</span>
        <div id="variantSizeChips" class="spf-variant-chips">
            <span class="spf-variant-empty">No sizes yet</span>
        </div>
    </div>
</div>

<div id="productVariantRows" class="spf-variant-list">
    @foreach($variantRows as $index => $variant)
        @include('seller.partials.product-variant-row')
    @endforeach
</div>

<p>Existing variants are retained. To stop offering one, set it to Inactive.</p>

<template id="productVariantTemplate">
    @include('seller.partials.product-variant-row', ['index'=>'__INDEX__', 'variant'=>[]])
</template>
