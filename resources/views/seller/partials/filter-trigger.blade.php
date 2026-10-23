<button type="button" data-filter-toggle aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}" aria-controls="{{ $filterId }}" class="inline-flex h-9 items-center gap-2 rounded-xl border border-[#BFD2C9] bg-white px-3.5 text-[11px] font-semibold text-[#1F6F5B] transition hover:bg-[#EEF5F1] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">
    <i data-lucide="sliders-horizontal" class="h-4 w-4" aria-hidden="true"></i>Filters
    @if(count($filterChips))<span data-active-filter-count class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#1F6F5B] px-1 text-[10px] text-white">{{ count($filterChips) }}<span class="sr-only"> active criteria</span></span>@endif
    <svg data-filter-chevron class="h-3.5 w-3.5 transition-transform" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5"/></svg>
</button>
