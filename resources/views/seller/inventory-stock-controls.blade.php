<form data-inventory-stock data-variant="{{ $resolvedVariantId }}" method="POST" action="{{ route('seller.inventory.set', $resolvedVariantId) }}" class="flex items-center justify-center gap-1">
    @csrf @method('PUT')
    <input type="hidden" name="expected_stock" value="{{ $stock }}">
    <button type="button" data-stock-step="-1" @disabled(!$databaseInventory || $stock <= 0) aria-label="Decrease editable stock for {{ $product['name'] }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#DDE6E1] bg-white text-[#68776F]">&minus;</button>
    <input type="number" name="stock" value="{{ $stock }}" min="0" max="{{ \App\Services\SellerInventoryService::MAX_STOCK }}" step="1" required @disabled(!$databaseInventory) aria-label="Stock for {{ $product['name'] }}" class="h-9 w-20 rounded-lg border border-[#DDE6E1] bg-white px-2 text-center text-xs font-semibold text-[#24312C]">
    <button type="submit" data-stock-save @disabled(!$databaseInventory) aria-label="Save stock for {{ $product['name'] }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#DDE6E1] bg-white text-[#1F6F5B]">&#10003;</button>
    <button type="button" data-stock-step="1" @disabled(!$databaseInventory || $stock >= \App\Services\SellerInventoryService::MAX_STOCK) aria-label="Increase editable stock for {{ $product['name'] }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-[#DDE6E1] bg-white text-[#68776F]">+</button>
    <span data-stock-feedback role="status" aria-live="polite" class="w-full text-center text-[10px] text-[#68776F]"></span>
    @if(isset($product['product_stock']))
        <span class="w-full text-center text-[9px] text-[#68776F]">
            Product total: {{ $product['product_stock'] }} active units
            @if(($product['status'] ?? '') === 'inactive')
                <span class="block">Inactive listing/variation</span>
            @endif
        </span>
    @endif
</form>
