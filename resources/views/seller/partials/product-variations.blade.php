@php
    $savedType = $product['product_type'] ?? 'simple';
    $initialType = isset($product) ? $savedType : old('product_type', 'simple');
    $initialRows = old('variant_rows', $product['variant_rows'] ?? []);
    if (is_array($initialRows)) {
        $trustedSkus = collect($product['variant_rows'] ?? [])->pluck('sku', 'id');
        foreach ($initialRows as &$initialRow) {
            if (is_array($initialRow)) {
                unset($initialRow['sku']);
                if (isset($initialRow['id']) && is_scalar($initialRow['id']) && ctype_digit((string) $initialRow['id']) && isset($trustedSkus[$initialRow['id']])) {
                    $initialRow['sku'] = $trustedSkus[$initialRow['id']];
                }
            }
        }
        unset($initialRow);
    }
    $variantEditorState = ['groups' => old('option_groups', $product['option_groups'] ?? []),
        'rows' => $initialRows, 'savedGroups' => $product['option_groups'] ?? []];
@endphp
<section data-variant-editor data-persisted="{{ isset($product) ? '1' : '0' }}" class="min-w-0 space-y-4 overflow-hidden rounded-2xl border border-[#DDE6E1] bg-white p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="{{ isset($product) ? 'text-sm font-semibold text-[#24312C]' : 'text-xs font-bold uppercase tracking-wider text-[#1F6F5B]' }}">Options &amp; Variants</h3>
        @if(!isset($product))<button type="button" data-add-group class="shrink-0 rounded-lg border border-[#1F6F5B]/30 bg-[#EEF5F1] px-3 py-2 text-xs font-bold text-[#1F6F5B] transition hover:bg-[#DDF3EC] disabled:opacity-50">+ Add Option</button>@endif
    </div>
    <input type="hidden" id="productType" name="product_type" value="{{ $initialType }}">
    <p class="text-xs leading-5 text-[#728078]">Optional for new products. Add options such as Size or Color, then generate combinations to set their prices and stock.</p>
    @if(isset($product))
        <p class="text-xs leading-5 text-[#728078]">The saved product type and option labels stay fixed to protect stock and order references. Create a separate listing for a different configuration.</p>
    @endif
    <div data-variable-fields class="space-y-4" @if(isset($product) && $initialType !== 'variable') hidden @endif>
        <p class="text-xs leading-5 text-[#728078]">Up to 3 option groups, 10 values per group and 50 combinations. Enter a price and stock for every combination. Deactivated combinations keep their stock and SKU. Sale pricing is available for simple products only.</p>
        <div data-option-groups class="space-y-3"></div>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-generate class="w-full rounded-xl border border-dashed border-[#1F6F5B]/40 bg-[#EEF5F1] px-4 py-3 text-xs font-bold text-[#1F6F5B] transition hover:bg-[#DDF3EC] disabled:opacity-50">Generate Variant Combinations</button>
        </div>
        <p data-variant-status role="status" aria-live="polite" class="text-xs text-red-600"></p>
        <div data-variant-table hidden class="overflow-x-auto rounded-xl border border-[#DDE6E1]">
            <table class="w-full text-left text-sm">
                <caption class="p-3 text-left text-xs text-[#52635B]">Variant Pricing &amp; Stock — prices and inventory are managed per variation. Sale pricing is available for simple products only.</caption>
                <thead class="bg-[#EEF5F1] text-xs text-[#34483F]"><tr>
                    <th scope="col" class="p-3">Combination</th><th scope="col" class="p-3">Price (₱)</th><th scope="col" class="p-3">Stock</th><th scope="col" class="p-3">SKU</th><th scope="col" class="p-3">Availability</th>
                </tr></thead>
                <tbody data-variant-rows></tbody>
            </table>
        </div>
    </div>
    @if($savedType === 'legacy')
        <p class="text-xs text-[#728078]">This older configuration needs a support review before option changes. Its existing variant records are preserved.</p>
        @foreach($product['variant_rows'] ?? [] as $row)
            <p class="break-words text-xs">{{ $row['sku'] }} · ₱{{ number_format($row['price'], 2) }} · {{ $row['stock'] }} units</p>
        @endforeach
    @endif
    <noscript><p class="text-xs">JavaScript is required to configure variations. Existing simple products can still be saved.</p></noscript>
    <script type="application/json" data-variant-state>@json($variantEditorState)</script>
</section>
