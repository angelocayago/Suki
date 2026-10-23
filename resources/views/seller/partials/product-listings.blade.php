@php
    $filterChips = \App\Support\SellerFilterChips::catalogue(request()->query(), $mainCategory, $filterCategories, $filterVariants, route('seller.products'));
    $filtersOpen = count($filterChips) > 0 || $errors->any();
    $filterId = 'product-advanced-filters';
@endphp
<section data-product-listing data-seller-filters class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EDF1EF] px-5 py-5">
        <div><h3 class="text-sm font-semibold text-[#24312C]">Product Listings</h3><p class="mt-0.5 text-[11px] text-[#7C8983]">Search, filter, and manage products in your store.</p></div>
        <div class="flex flex-wrap items-center gap-2">
            @include('seller.partials.filter-trigger')
            <a href="{{ route('seller.inventory') }}" class="inline-flex h-9 items-center gap-2 rounded-xl border border-[#DDE6E1] bg-white px-3.5 text-[11px] font-semibold text-[#52635B] transition hover:bg-[#F8FAF8] hover:text-[#173F35]"><i data-lucide="boxes" class="h-4 w-4" aria-hidden="true"></i>Manage Inventory</a>
        </div>
    </div>
    @include('seller.partials.advanced-filter-panel')
    <noscript><style>[data-product-selection],[data-selection-cell]{display:none!important}</style></noscript>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EDF1EF] px-4 py-3">
        @include('seller.partials.active-filter-chips')
        <p data-matching-count class="text-[11px] text-[#68776F]">@if($paginator->count())Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of @endif{{ $paginator->total() }} matching products</p>
    </div>
    <div data-product-selection class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-[#EDF1EF] px-4 py-3 text-[11px] text-[#68776F]">
        <label class="inline-flex items-center gap-2 md:hidden"><input type="checkbox" data-select-page aria-label="Select all products on this page" class="h-4 w-4 accent-[#1F6F5B]" @disabled(!$products)>Select page</label>
        <span data-selected-count role="status" aria-live="polite">0 selected on this page</span>
        <button type="button" data-clear-selection disabled class="font-semibold text-[#1F6F5B] hover:underline disabled:opacity-40">Clear Selection</button>
        <span>Current page only · Selection only; no bulk actions</span>
    </div>
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[1080px] text-left text-xs">
            <caption class="sr-only">Saved products matching the applied filters. Select All selects only this page.</caption>
            <thead class="border-b border-[#EDF1EF] bg-[#F7F9F8] text-[10px] text-[#68776F]"><tr>
                <th data-selection-cell class="w-10 px-4 py-3"><input type="checkbox" data-select-page aria-label="Select all products on this page" class="h-4 w-4 accent-[#1F6F5B]" @disabled(!$products)></th>
                @foreach(['Product', 'Category', 'Price', 'Stock', 'Aggregate Stock Status', 'Listing Status', 'Last Updated', 'Actions'] as $column)<th scope="col" class="px-3 py-3 font-semibold {{ $column === 'Actions' ? 'text-right' : '' }}">{{ $column }}</th>@endforeach
            </tr></thead>
            <tbody>
                @forelse($products as $product)
                    @php($updated = \Carbon\CarbonImmutable::parse($product['updated_at'], config('app.timezone'))->setTimezone(config('seller_inventory.business_timezone')))
                    <tr data-product-row="{{ $product['id'] }}" class="border-b border-[#EDF1EF] align-top last:border-0 hover:bg-[#FBFCFB] data-[selected=true]:bg-[#EEF5F1]">
                        <td data-selection-cell class="px-4 py-5"><input type="checkbox" data-product-checkbox value="{{ $product['id'] }}" aria-label="Select {{ $product['name'] ?? 'Unnamed Product' }}" class="h-4 w-4 accent-[#1F6F5B]"></td>
                        <td class="min-w-[240px] max-w-[330px] px-3 py-4"><div class="flex items-start gap-3"><div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-[#E5EBE7] bg-[#F1F5F3]">@include('seller.partials.product-thumbnail')</div><div class="min-w-0"><p class="break-words font-semibold text-[#34483F]">{{ $product['name'] ?? 'Unnamed Product' }}</p><p class="mt-1 break-all text-[10px] text-[#89968F]">SKU: {{ $product['sku'] ?? 'Unavailable' }}</p>@include('seller.partials.stock-alerts', ['variantOnly' => true])</div></div></td>
                        <td class="max-w-[180px] px-3 py-4 text-[11px] text-[#65746D]">{{ $product['category'] }}@if($product['subcategory'])<span class="mt-1 block text-[10px] text-[#89968F]">{{ $product['subcategory'] }}</span>@endif</td>
                        <td class="whitespace-nowrap px-3 py-4 font-semibold text-[#24312C]">@include('seller.partials.product-listing-price')</td>
                        <td class="px-3 py-4 font-semibold tabular-nums text-[#34483F]">{{ number_format($product['stock']) }}</td>
                        <td class="px-3 py-4">@include('seller.partials.stock-status-badge')</td>
                        <td class="px-3 py-4">@include('seller.partials.listing-status-badge')</td>
                        <td class="whitespace-nowrap px-3 py-4 text-[10px] text-[#728078]"><time datetime="{{ $updated->toIso8601String() }}" title="Asia/Manila">{{ $updated->format('M j, Y') }}<span class="mt-1 block">{{ $updated->format('g:i A') }} PHT</span></time></td>
                        <td class="px-3 py-4 text-right"><a href="{{ route('seller.products.edit', $product['id']) }}" aria-label="Edit {{ $product['name'] ?? 'Unnamed Product' }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-[#DDE6E1] text-[#52635B] hover:bg-[#EEF5F1]"><i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i></a></td>
                    </tr>
                @empty<tr><td colspan="9" class="px-5 py-14 text-center">@include('seller.partials.product-listing-empty')</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="divide-y divide-[#EDF1EF] md:hidden">
        @forelse($products as $product)
            <article data-product-row="{{ $product['id'] }}" class="p-4 data-[selected=true]:bg-[#EEF5F1]">
                <div class="flex items-start gap-3"><span data-selection-cell><input type="checkbox" data-product-checkbox value="{{ $product['id'] }}" aria-label="Select {{ $product['name'] ?? 'Unnamed Product' }}" class="mt-2 h-4 w-4 accent-[#1F6F5B]"></span><div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-[#E5EBE7] bg-[#F1F5F3]">@include('seller.partials.product-thumbnail')</div><div class="min-w-0 flex-1"><h4 class="break-words text-xs font-semibold text-[#34483F]">{{ $product['name'] ?? 'Unnamed Product' }}</h4><p class="mt-1 break-all text-[10px] text-[#89968F]">SKU: {{ $product['sku'] ?? 'Unavailable' }}</p><p class="mt-1 text-[10px] text-[#89968F]">{{ $product['category'] }} · {{ $product['subcategory'] }}</p></div></div>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><p class="text-sm font-semibold text-[#24312C]">@include('seller.partials.product-listing-price')</p><p class="text-xs text-[#68776F]">{{ number_format($product['stock']) }} sellable units</p></div>
                <div class="mt-3 flex flex-wrap gap-2">@include('seller.partials.stock-status-badge') @include('seller.partials.listing-status-badge')</div>
                @include('seller.partials.stock-alerts', ['variantOnly' => true])
                @php($updated = \Carbon\CarbonImmutable::parse($product['updated_at'], config('app.timezone'))->setTimezone(config('seller_inventory.business_timezone')))
                <div class="mt-4 flex items-center justify-between gap-3"><p class="text-[10px] text-[#89968F]">Updated <time datetime="{{ $updated->toIso8601String() }}">{{ $updated->format('M j, Y g:i A') }} PHT</time></p><a href="{{ route('seller.products.edit', $product['id']) }}" class="rounded-lg border border-[#DDE6E1] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">Edit</a></div>
            </article>
        @empty<div class="px-5 py-12 text-center">@include('seller.partials.product-listing-empty')</div>@endforelse
    </div>
    <div class="border-t border-[#EDF1EF] p-4"><p class="mb-3 text-[10px] text-[#7B8982]">All four summary cards reflect all matching records across pages.</p>{{ $paginator->links() }}</div>
</section>
