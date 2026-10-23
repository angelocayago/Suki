@php
    $stockStatus = $product['stock_status'];
    $badge = match($stockStatus) {
        'out-of-stock' => ['Out of Stock', 'border-red-200 bg-red-50 text-red-700'],
        'low-stock' => ['Low Stock', 'border-amber-200 bg-amber-50 text-amber-800'],
        default => ['In Stock', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    };
@endphp
<span data-stock-status="{{ $stockStatus }}" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 text-[10px] font-semibold {{ $badge[1] }}"><span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $badge[0] }}</span>
