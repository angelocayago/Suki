@extends('layouts.app')

@section('content')
<div class="catalog-shop">




{{-- ========================================================= --}}
{{-- SHOP HERO --}}
{{-- ========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-7">

    <div class="bg-[#EEF8F3] rounded-2xl px-6 py-7 md:px-9 md:py-9">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">

            <div>

                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="hover:text-[#1F6F5B] transition"
                    >
                        Home
                    </a>

                    <i
                        data-lucide="chevron-right"
                        class="w-4 h-4"
                    ></i>

                    <span class="text-[#1F6F5B] font-medium">
                        Shop
                    </span>

                </div>

                <p class="text-xs font-semibold tracking-wider text-[#1F6F5B] uppercase">
                    SUKI SHOP Marketplace
                </p>

                <h1 class="text-3xl md:text-4xl font-bold text-[#173F35] mt-2">
                    Shop All Products
                </h1>

                <p class="text-sm text-gray-500 mt-2 max-w-xl">
                    Discover products from trusted sellers,
                    compare your options, and find something
                    perfect for your everyday needs.
                </p>

            </div>

            <div class="hidden md:flex w-16 h-16 rounded-2xl bg-white items-center justify-center border border-[#DCEDE6]">

                <i
                    data-lucide="shopping-bag"
                    class="w-7 h-7 text-[#1F6F5B]"
                ></i>

            </div>

        </div>

    </div>

</section>

{{-- Database-backed marketplace controls. All filters are preserved by the paginator. --}}
<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-5">
    <form id="catalogFilters" method="GET" action="{{ route('buyer.shop') }}" class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-6">
        <input type="hidden" name="rating" value="{{ request('rating') }}">
        <label class="md:col-span-2">
            <span class="sr-only">Search products</span>
            <input name="q" value="{{ request('q') }}" maxlength="100" placeholder="Search products" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#1F6F5B] focus:ring-[#1F6F5B]">
        </label>
        <label>
            <span class="sr-only">Category</span>
            <select name="category" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#1F6F5B] focus:ring-[#1F6F5B]">
                @php($categorySummary = count($selectedCategories) > 1 || ($filterOptions['categories']->whereIn('id', $selectedCategories)->whereNotNull('parent_id')->isNotEmpty()))
                <option value="__selected" disabled data-catalog-category-placeholder @selected($categorySummary) @if(!$categorySummary) hidden @endif>{{ count($selectedCategories) }} {{ count($selectedCategories) === 1 ? 'category' : 'categories' }} selected</option>
                <option value="">All categories</option>
                @foreach(($filterOptions['categories'] ?? collect())->whereNull('parent_id') as $filterCategory)
                    <option value="{{ $filterCategory->slug }}" @selected(count($selectedCategories) === 1 && in_array($filterCategory->id, $selectedCategories, true))>{{ $filterCategory->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="sr-only">Store</span>
            <select name="seller" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#1F6F5B] focus:ring-[#1F6F5B]">
                <option value="">All stores</option>
                @foreach(($filterOptions['sellers'] ?? collect()) as $filterSeller)
                    <option value="{{ $filterSeller->slug }}" @selected(request('seller') == $filterSeller->slug)>{{ $filterSeller->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="sr-only">Sort products</span>
            <select name="sort" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#1F6F5B] focus:ring-[#1F6F5B]">
                <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
                <option value="best_selling" @selected(request('sort') === 'best_selling')>Best-selling</option>
                <option value="name" @selected(request('sort') === 'name')>Name</option>
            </select>
        </label>
        <div class="flex gap-2">
            <input name="min_price" aria-label="Minimum price" value="{{ request('min_price') }}" min="0" step="0.01" type="number" placeholder="Min price" class="min-w-0 w-full rounded-lg border-gray-300 text-sm focus:border-[#1F6F5B] focus:ring-[#1F6F5B]">
            <input name="max_price" aria-label="Maximum price" value="{{ request('max_price') }}" min="0" step="0.01" type="number" placeholder="Max price" class="min-w-0 w-full rounded-lg border-gray-300 text-sm focus:border-[#1F6F5B] focus:ring-[#1F6F5B]">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">

            <input type="checkbox" name="in_stock" value="1" @checked(request('in_stock') === '1') class="rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]">
            In stock only
        </label>
        <div class="flex gap-3 md:col-span-6">
            <button class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white hover:bg-[#155244]">Apply filters</button>
            <a href="{{ route('buyer.shop') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" data-catalog-reset>Reset</a>
        </div>
    </form>
</section>
{{-- ========================================================= --}}
{{-- CATEGORY QUICK FILTER --}}
{{-- ========================================================= --}}

<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-7">

    <div class="flex items-center justify-between mb-4">

        <div>

            <h2 class="text-lg font-bold text-gray-900">
                Browse Categories
            </h2>

            <p class="text-xs text-gray-500 mt-1">
                Explore products by category.
            </p>

        </div>

    </div>


    <div class="catalog-category-pills flex gap-3 overflow-x-auto pb-2 scrollbar-hide" data-catalog-pills tabindex="0" aria-label="Browse main categories">

        <a href="{{ route('buyer.shop', request()->except(['page', 'category', 'categories'])) }}" data-catalog-category="" data-category-id="" class="catalog-all-pill shrink-0 px-4 py-2.5 rounded-lg border text-sm font-medium">All</a>
        @foreach(($filterOptions['categories'] ?? collect())->whereNull('parent_id') as $category)

            <a
                href="{{ route('buyer.shop', array_merge(request()->except(['page', 'category', 'categories']), ['categories' => [$category->id]])) }}"
                data-catalog-category="{{ $category->slug }}" data-category-id="{{ $category->id }}" class="shrink-0 px-4 py-2.5 rounded-lg border
                {{ in_array($category->id, $selectedCategories, true) ? 'bg-[#1F6F5B] text-white border-[#1F6F5B]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#1F6F5B] hover:text-[#1F6F5B]' }}
                text-sm font-medium transition"
            >
                {{ $category->name }}
            </a>

        @endforeach

    </div>

</section>


{{-- ========================================================= --}}
{{-- SHOP CONTENT --}}
{{-- ========================================================= --}}

<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 py-8">

    <div class="grid lg:grid-cols-[280px_1fr] items-start gap-7">


        {{-- ================================================= --}}
        {{-- SIDEBAR --}}
        {{-- ================================================= --}}

        <div id="catalogBackdrop" class="catalog-backdrop" hidden></div>
        <aside id="catalogSidebar" class="hidden lg:block" aria-label="Shop filters">

            <div class="catalog-sidebar-card bg-white border border-gray-200 rounded-2xl sticky top-28">


                {{-- FILTER HEADER --}}
                <div class="px-5 py-4 border-b border-gray-200">

                    <div class="flex items-center justify-between">

                        <div class="flex items-center gap-2">

                            <i
                                data-lucide="sliders-horizontal"
                                class="w-4 h-4 text-[#1F6F5B]"
                            ></i>

                            <h2 class="font-semibold text-gray-900">
                                Filters
                            </h2>

                        </div>

                        <a href="{{ route('buyer.shop') }}" data-catalog-reset
                            class="text-xs font-medium text-[#1F6F5B] hover:underline"
                        >
                            Reset
                        </a>
                        <button type="button" data-catalog-close class="catalog-mobile-only" aria-label="Close filters">&times;</button>

                    </div>

                </div>


                {{-- CATEGORY --}}
                <div class="px-5 py-5 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900 mb-4">
                        Categories <span data-catalog-category-count class="catalog-count"></span>
                    </h3>
                    <div class="catalog-category-tools"><button type="button" data-catalog-clear-categories>Clear categories</button></div>
                    @if($filterOptions['categories']->count() > 10)<label class="catalog-category-search"><span class="sr-only">Search categories</span><input type="search" data-catalog-category-search placeholder="Search categories"></label>@endif

                    <div class="catalog-category-list" role="region" aria-label="Filter by category" tabindex="0">
                        @include('buyer.partials.catalog-category-tree', ['nodes' => $categoryGroups->get('', collect())])
                    </div>
                    <button type="button" data-catalog-show-more aria-expanded="false" class="catalog-show-more" hidden>Show more</button>

                </div>


                {{-- PRICE --}}
                <details class="catalog-filter-section" open><summary>Price Range</summary>
                <div class="px-5 py-5 border-b border-gray-100 catalog-price-content">



                    <div class="catalog-price-inputs flex items-center gap-2">
                        <span aria-hidden="true">&#8369;</span>
                        <input
                            type="number"
                            data-catalog-price-input placeholder="Min" min="0" step="0.01" aria-label="Sidebar minimum price" data-catalog-price="min_price" value="{{ request('min_price') }}"
                            class="w-full px-3 py-2.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-[#1F6F5B] focus:ring-1 focus:ring-[#1F6F5B]"
                        >

                        <span class="text-gray-300">
                            —
                        </span>

                        <span aria-hidden="true">&#8369;</span>
                        <input
                            type="number"
                            data-catalog-price-input placeholder="Max" min="0" step="0.01" aria-label="Sidebar maximum price" data-catalog-price="max_price" value="{{ request('max_price') }}"
                            class="w-full px-3 py-2.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-[#1F6F5B] focus:ring-1 focus:ring-[#1F6F5B]"
                        >

                    </div>

                    <button
                        type="button" data-catalog-apply-price
                        class="w-full mt-3 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-xs font-semibold hover:bg-[#155244] transition"
                    >
                        Apply Price
                    </button>

                </div>


                <p id="catalogPriceError" class="catalog-price-error" role="alert"></p>
                </details>
                @include('buyer.partials.catalog-rating-filter')
                <div class="catalog-drawer-footer catalog-mobile-only"><button type="button" data-catalog-drawer-apply>Apply filters</button><a href="{{ route('buyer.shop') }}" data-catalog-reset>Reset</a></div>

            </div>

        </aside>


        <div class="min-w-0">
            @if($errors->any())
                <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            <p id="catalogStatus" role="status" aria-live="polite" class="sr-only"></p>
            <div class="catalog-results-wrap">
            <div id="catalogLoading" class="catalog-loading" hidden aria-hidden="true"><div class="catalog-product-grid">@for($skeleton = 0; $skeleton < 4; $skeleton++)<div class="catalog-skeleton"><div></div><span></span><span></span></div>@endfor</div></div>
            <div id="catalogResults" tabindex="-1" aria-label="Product results" aria-busy="false">
                @include('buyer.partials.catalog-results')
            </div>
            </div>
        </div>
    </div>
</section>
</div>
@endsection

@push('scripts')
    @include('buyer.partials.catalog-script')
@endpush
