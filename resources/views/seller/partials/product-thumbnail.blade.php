<span class="flex h-full w-full items-center justify-center" role="img" aria-label="{{ $product['name'] ?? 'Product' }}">
    <span @class(['hidden' => !empty($product['image'])]) data-image-placeholder aria-label="No image">
        <i data-lucide="package" class="h-5 w-5 text-[#85958D]"></i>
    </span>
    @if(!empty($product['image']))
        <img src="{{ $product['image'] }}" alt="{{ $product['name'] ?? 'Product' }}"
             class="h-full w-full object-cover"
             onerror="this.hidden = true; this.previousElementSibling.classList.remove('hidden');">
    @endif
</span>
