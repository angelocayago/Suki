<div class="product-variant-row" data-stock-row>
    @if(filled($variant['id'] ?? null))<input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}">@endif
    <div class="spf-grid">
        @foreach(['Color', 'Size'] as $option)
            <label>{{ $option }}<input name="variants[{{ $index }}][options][{{ $option }}]" maxlength="100" value="{{ data_get($variant, 'options.'.$option) }}"></label>
        @endforeach
        <label>Variant Name *<input name="variants[{{ $index }}][name]" required maxlength="100" value="{{ $variant['name'] ?? '' }}"></label>
        <label>SKU<input name="variants[{{ $index }}][sku]" value="{{ $variant['sku'] ?? '' }}" readonly placeholder="Generated automatically after saving"></label>
        <label>Price (PHP) *<input type="number" name="variants[{{ $index }}][price]" min="0" max="99999999.99" step="0.01" required value="{{ $variant['price'] ?? '' }}"></label>
        <label>Stock Quantity *<input type="number" name="variants[{{ $index }}][stock]" min="0" max="4294967295" step="1" required value="{{ $variant['stock'] ?? 0 }}" data-variant-stock></label>
        <label>Status<select name="variants[{{ $index }}][status]" data-variant-status><option value="active" @selected(($variant['status'] ?? 'active') === 'active')>Active</option><option value="inactive" @selected(($variant['status'] ?? 'active') === 'inactive')>Inactive</option></select></label>
    </div>
    <output data-stock-indicator aria-live="polite"></output>
    @unless(filled($variant['id'] ?? null))<button type="button" class="remove-product-variant">Remove variant</button>@endunless
</div>
