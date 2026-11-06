@php $thumbnailUrl = \App\Support\BuyerPurchases::image($item['image'] ?? null); @endphp
@once
<style>[data-purchase-thumbnail] [hidden]{display:none!important}</style>
@endonce
<div class="relative {{ $thumbnailSize }} shrink-0 overflow-hidden rounded-xl bg-[#EEF4F0]" data-purchase-thumbnail>
    <span class="absolute inset-0 flex flex-col items-center justify-center gap-1 px-1 text-center text-[#617B6D]" role="img" aria-label="Image unavailable for {{ $item['name'] }}" @if($thumbnailUrl) hidden @endif>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/></svg>
        <span class="text-[10px] leading-tight">Image unavailable</span>
    </span>
    @if($thumbnailUrl)
        <img src="{{ $thumbnailUrl }}" alt="{{ $item['name'] }}" width="96" height="96" loading="lazy"
             class="absolute inset-0 h-full w-full object-cover text-[0px]" onerror="this.hidden = true; this.previousElementSibling.hidden = false;">
    @endif
</div>
