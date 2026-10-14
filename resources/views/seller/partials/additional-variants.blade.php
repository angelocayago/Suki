@php($variantRows = old('variants', $variants ?? []))

<section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
    <div class="flex items-center justify-between gap-4 border-b border-[#EDF1EF] px-5 py-4">
        <div>
            <h3 class="text-sm font-semibold text-[#24312C]">Additional variants</h3>
            <p class="mt-0.5 text-[11px] text-[#7C8983]">Add separate SKUs, prices, and stock levels for other sellable variants.</p>
        </div>
        <button type="button" id="addProductVariant" class="inline-flex h-9 items-center gap-2 rounded-xl border border-[#DDE6E1] bg-white px-3 text-[10px] font-semibold text-[#52635B] hover:bg-[#F5F8F6]">
            <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add variant
        </button>
    </div>
    <div id="productVariantRows" class="space-y-3 p-5">
        @foreach($variantRows as $index => $variant)
            <div class="product-variant-row grid gap-3 rounded-xl border border-[#E1E8E4] p-3 md:grid-cols-5">
                @if(filled($variant['id'] ?? null))
                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}">
                @endif
                <input required name="variants[{{ $index }}][name]" value="{{ $variant['name'] ?? '' }}" placeholder="Variant name" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs">
                <input name="variants[{{ $index }}][sku]" value="{{ $variant['sku'] ?? '' }}" placeholder="SKU (optional)" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs">
                <input required type="number" min="0" step="0.01" name="variants[{{ $index }}][price]" value="{{ $variant['price'] ?? '' }}" placeholder="Price" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs">
                <input required type="number" min="0" name="variants[{{ $index }}][stock]" value="{{ $variant['stock'] ?? 0 }}" placeholder="Stock" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs">
                <div class="flex gap-2">
                    <select name="variants[{{ $index }}][status]" class="h-10 min-w-0 flex-1 rounded-xl border border-[#DDE6E1] px-2 text-xs">
                        <option value="active" @selected(($variant['status'] ?? 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(($variant['status'] ?? 'active') === 'inactive')>Inactive</option>
                    </select>
                    @unless(filled($variant['id'] ?? null))
                        <button type="button" class="remove-product-variant h-10 rounded-xl border border-red-200 px-3 text-xs text-red-600">Remove</button>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('productVariantRows');
    const add = document.getElementById('addProductVariant');
    if (!rows || !add) return;
    let index = rows.querySelectorAll('.product-variant-row').length;
    add.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'product-variant-row grid gap-3 rounded-xl border border-[#E1E8E4] p-3 md:grid-cols-5';
        row.innerHTML = `<input required name="variants[${index}][name]" placeholder="Variant name" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs"><input name="variants[${index}][sku]" placeholder="SKU (optional)" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs"><input required type="number" min="0" step="0.01" name="variants[${index}][price]" placeholder="Price" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs"><input required type="number" min="0" name="variants[${index}][stock]" value="0" placeholder="Stock" class="h-10 rounded-xl border border-[#DDE6E1] px-3 text-xs"><div class="flex gap-2"><select name="variants[${index}][status]" class="h-10 min-w-0 flex-1 rounded-xl border border-[#DDE6E1] px-2 text-xs"><option value="active">Active</option><option value="inactive">Inactive</option></select><button type="button" class="remove-product-variant h-10 rounded-xl border border-red-200 px-3 text-xs text-red-600">Remove</button></div>`;
        rows.appendChild(row);
        index++;
    });
    rows.addEventListener('click', event => {
        const button = event.target.closest('.remove-product-variant');
        if (button) button.closest('.product-variant-row').remove();
    });
});
</script>
@endpush
