<section id="{{ $filterId }}" data-filter-panel aria-labelledby="{{ $filterId }}-heading" @if(!$filtersOpen) hidden @endif>
    <div class="flex items-start justify-between gap-3 bg-[#FBFCFB] px-4 pt-4">
        <div><h4 id="{{ $filterId }}-heading" class="text-sm font-semibold text-[#24312C]">Advanced Filters</h4><p class="mt-1 text-xs text-[#728078]">Choose criteria, then apply them to your saved records.</p></div>
        <button type="button" data-filter-close aria-label="Close advanced filters" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#52635B] hover:bg-[#EEF5F1] focus-visible:outline-2 focus-visible:outline-[#1F6F5B]"><span aria-hidden="true">✕</span></button>
    </div>
    @include($filterForm ?? 'seller.partials.data-filters', ['compact' => true])
</section>
<noscript><style>#{{ $filterId }}[hidden]{display:block!important}[data-filter-toggle],[data-filter-close]{display:none!important}</style></noscript>
