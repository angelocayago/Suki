@if(count($filterChips))
    <div class="flex min-w-0 flex-wrap items-center gap-2 px-4 py-3" aria-label="Active filters"><span class="text-[11px] font-medium text-[#68776F]">Active filters:</span>
        @foreach($filterChips as $chip)<a data-filter-chip="{{ $chip['key'] }}" href="{{ $chip['url'] }}" aria-label="Remove filter: {{ $chip['label'] }}" class="inline-flex max-w-full items-center gap-2 rounded-full bg-[#EEF5F1] px-3 py-1.5 text-[11px] font-medium text-[#1F6F5B] hover:bg-[#DDF3EC] focus-visible:outline-2 focus-visible:outline-[#1F6F5B]"><span class="break-words">{{ $chip['label'] }}</span><span aria-hidden="true">×</span></a>@endforeach
    </div>
@endif
