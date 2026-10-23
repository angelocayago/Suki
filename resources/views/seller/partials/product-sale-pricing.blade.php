@php
    $saleEnabled = session()->hasOldInput()
        ? (bool) old('on_sale', false)
        : (bool) ($product['on_sale'] ?? false);
@endphp

            <section data-simple-sale class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">

                <div class="flex items-center justify-between gap-4 border-b border-[#EDF1EF] px-5 py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-[#24312C]">Sale pricing</h3>
                        <p class="mt-0.5 text-[11px] text-[#7C8983]">
                            Show a reduced price while keeping the regular price on record.
                        </p>
                    </div>

                    <input type="hidden" name="on_sale" value="0">
                    <label for="on_sale" class="inline-flex cursor-pointer items-center gap-2 text-xs font-medium text-[#52635B]">
                        <input
                            type="checkbox"
                            id="on_sale"
                            name="on_sale"
                            value="1"
                            @checked($saleEnabled)
                            class="h-4 w-4 rounded border-[#C8D6CF] text-[#1F6F5B] focus:ring-[#DDF3EC]"
                        >
                        Offer a sale price
                    </label>
                </div>

                <div id="salePriceFields" class="p-5">
                    <label for="sale_price" class="mb-2 block text-xs font-semibold text-[#34483F]">
                        Sale price <span class="text-red-500">*</span>
                    </label>
                    <div class="relative sm:max-w-sm">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-medium text-[#728078]">₱</span>
                        <input
                            type="number"
                            id="sale_price"
                            name="sale_price"
                            value="{{ old('sale_price', $product['sale_price'] ?? '') }}"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            class="h-11 w-full rounded-xl border border-[#DDE6E1] bg-white pl-9 pr-4 text-sm text-[#34483F] placeholder:text-[#9AA69F] focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#DDF3EC]/70"
                        >
                    </div>
                    @error('sale_price')
                        <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 text-[10px] leading-4 text-[#8C9992]">
                        The sale price must be lower than the regular price above.
                    </p>
                </div>

            </section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const saleToggle =
        document.getElementById('on_sale');

    const salePriceFields =
        document.getElementById('salePriceFields');

    const salePriceInput =
        document.getElementById('sale_price');

    function updateSaleFields() {
        const enabled = Boolean(saleToggle?.checked && !saleToggle.disabled);

        salePriceFields?.classList.toggle('hidden', !enabled);

        if (salePriceInput) {
            salePriceInput.required = enabled;
            salePriceInput.disabled = !enabled;
        }
    }

    saleToggle?.addEventListener('change', updateSaleFields);
    updateSaleFields();

});
</script>
@endpush
