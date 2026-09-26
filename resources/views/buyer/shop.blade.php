@extends('layouts.app')

@section('content')

@php
 

    $categories = [
        'All Products',
        'Fashion',
        'Beauty',
        'Home',
        'Electronics',
        'Sports',
        'Food',
        'Pets',
        'Toys',
        'Automotive',
    ];

    /*
    |--------------------------------------------------------------------------
    | CURRENT BUYER WISHLIST
    |--------------------------------------------------------------------------
    |
    | Kinukuha natin ang product_slug ng wishlist items ng currently
    | authenticated buyer para malaman kung filled green o gray
    | ang bawat heart button.
    |
    */

    $wishlistSlugs = [];

    if (
        auth()->check() &&
        auth()->user()->role === 'buyer'
    ) {
        $wishlistSlugs = auth()
            ->user()
            ->wishlistItems()
            ->pluck('product_slug')
            ->toArray();
    }
@endphp


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


{{-- ========================================================= --}}
{{-- CATEGORY QUICK FILTER --}}
{{-- ========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-7">

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


    <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">

        @foreach($categories as $category)

            <a
                href="{{ $category === 'All Products'
    ? route('buyer.shop')
    : route('buyer.shop', ['category' => $category])
}}"
                class="shrink-0 px-4 py-2.5 rounded-lg border
{{ request('category') === $category || (!request('category') && $category === 'All Products')
    ? 'bg-[#1F6F5B] text-white border-[#1F6F5B]'
    : 'bg-white text-gray-600 border-gray-200 hover:border-[#1F6F5B] hover:text-[#1F6F5B]'
}}
                }}
                text-sm font-medium transition"
            >
                {{ $category }}
            </a>

        @endforeach

    </div>

</section>


{{-- ========================================================= --}}
{{-- SHOP CONTENT --}}
{{-- ========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="grid lg:grid-cols-[235px_1fr] gap-7">


        {{-- ================================================= --}}
        {{-- SIDEBAR --}}
        {{-- ================================================= --}}

        <aside class="hidden lg:block">

            <div class="bg-white border border-gray-200 rounded-2xl sticky top-28 overflow-hidden">


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

                        <button
                            type="button"
                            class="text-xs font-medium text-[#1F6F5B] hover:underline"
                        >
                            Reset
                        </button>

                    </div>

                </div>


                {{-- CATEGORY --}}
                <div class="px-5 py-5 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900 mb-4">
                        Categories
                    </h3>

                    <div class="space-y-3">

                        @foreach($categories as $category)

                            @if($category !== 'All Products')

                                <label class="flex items-center justify-between gap-3 text-sm text-gray-600 cursor-pointer group">

                                    <div class="flex items-center gap-3">

                                        <input
                                            type="checkbox"
                                            class="w-4 h-4 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                                        >

                                        <span class="group-hover:text-[#1F6F5B] transition">
                                            {{ $category }}
                                        </span>

                                    </div>

                                </label>

                            @endif

                        @endforeach

                    </div>

                </div>


                {{-- PRICE --}}
              <form
    method="GET"
    action="{{ route('buyer.shop') }}"
    class="px-5 py-5 border-b border-gray-100"
>

    <h3 class="text-sm font-semibold text-gray-900 mb-4">
        Price Range
    </h3>


    <div class="flex items-center gap-2">

        <input
            type="number"
            name="min_price"
            value="{{ request('min_price') }}"
            placeholder="Min"
            class="w-full px-3 py-2.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-[#1F6F5B]"
        >


        <span class="text-gray-300">
            —
        </span>


        <input
            type="number"
            name="max_price"
            value="{{ request('max_price') }}"
            placeholder="Max"
            class="w-full px-3 py-2.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-[#1F6F5B]"
        >

    </div>


    <button
        type="submit"
        class="w-full mt-3 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-xs font-semibold hover:bg-[#155244] transition"
    >
        Apply Price
    </button>


</form>


                {{-- RATING --}}
                <div class="px-5 py-5">

                    <h3 class="text-sm font-semibold text-gray-900 mb-4">
                        Customer Rating
                    </h3>

                    <div class="space-y-3">

                        @foreach([
                            '5 stars',
                            '4 stars & up',
                            '3 stars & up'
                        ] as $rating)

                            <label class="flex items-center gap-3 text-sm text-gray-600 cursor-pointer">

                                <input
                                    type="radio"
                                    name="rating"
                                    class="w-4 h-4 border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                                >

                                <div class="flex items-center gap-1">

                                    <i
                                        data-lucide="star"
                                        class="w-3.5 h-3.5 text-[#F59E0B] fill-[#F59E0B]"
                                    ></i>

                                    <span>
                                        {{ $rating }}
                                    </span>

                                </div>

                            </label>

                        @endforeach

                    </div>

                </div>

            </div>

        </aside>


        {{-- ================================================= --}}
        {{-- PRODUCTS AREA --}}
        {{-- ================================================= --}}

        <div>


            {{-- TOOLBAR --}}
            <div class="bg-white border border-gray-200 rounded-xl px-4 py-3 mb-5">

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                    <div>

                        <p class="text-sm text-gray-700">

                            <span class="font-semibold">
                                {{ count($products) }}
                            </span>

                            products available

                        </p>

                    </div>


                    <div class="flex items-center gap-2">

                        <button
                            type="button"
                            class="lg:hidden inline-flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:border-[#1F6F5B] hover:text-[#1F6F5B] transition"
                        >
                            <i
                                data-lucide="sliders-horizontal"
                                class="w-4 h-4"
                            ></i>

                            Filters
                        </button>


                        <div class="flex items-center gap-2">

                            <span class="hidden sm:block text-xs text-gray-400">
                                Sort by
                            </span>

                           <form
    method="GET"
    action="{{ route('buyer.shop') }}"
>

    <select
        name="sort"
        onchange="this.form.submit()"
        class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:outline-none"
    >

        <option value="">
            Recommended
        </option>


        <option
            value="best_selling"
            {{ request('sort') === 'best_selling' ? 'selected' : '' }}
        >
            Best Selling
        </option>


        <option
            value="price_low"
            {{ request('sort') === 'price_low' ? 'selected' : '' }}
        >
            Price: Low to High
        </option>


        <option
            value="price_high"
            {{ request('sort') === 'price_high' ? 'selected' : '' }}
        >
            Price: High to Low
        </option>

    </select>

</form>

                        </div>

                    </div>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- PRODUCT GRID --}}
            {{-- ================================================= --}}

            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">

@foreach($products as $slug => $product)

                    @php
                       $isWishlisted = in_array(
    $slug,
    $wishlistSlugs,
    true
);
                    @endphp


                    <article
                        class="group relative bg-white rounded-xl overflow-hidden border border-gray-200 hover:border-[#D5EAE2] hover:shadow-lg hover:-translate-y-0.5 transition duration-200"
                    >


                        {{-- PRODUCT IMAGE --}}
                        <div class="relative aspect-square overflow-hidden bg-gray-100">

                            <a
                                href="{{ route('buyer.product', ['slug' => $slug]) }}"
                                class="block w-full h-full"
                            >

                                <img
                                    src="{{ $product['image'] }}"
                                    alt="{{ $product['name'] }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                                >

                            </a>


                            {{-- SALE BADGE --}}
                            <span
                                class="absolute top-3 left-3 z-10 bg-[#F59E0B] text-white text-[10px] font-bold px-2 py-1 rounded-md shadow-sm"
                            >
                                SALE
                            </span>


                            {{-- ================================================= --}}
{{-- WISHLIST TOGGLE --}}
{{-- ================================================= --}}

<form
    action="{{ route('buyer.wishlist.toggle', ['slug' => $slug]) }}"
    method="POST"
    class="absolute top-3 right-3 z-20"
>
    @csrf

    <button
        type="submit"
        class="w-9 h-9 rounded-full border flex items-center justify-center shadow-sm transition-all duration-200"
        style="
            @if($isWishlisted)
                background-color: #1F6F5B;
                border-color: #1F6F5B;
                color: #ffffff;
            @else
                background-color: #ffffff;
                border-color: #e5e7eb;
                color: #6b7280;
            @endif
        "
        title="{{ $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist' }}"
        aria-label="{{ $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist' }}"
    >
        <i
            data-lucide="heart"
            class="w-4 h-4"
            style="
                @if($isWishlisted)
                    fill: #ffffff;
                    stroke: #ffffff;
                @else
                    fill: none;
                    stroke: #6b7280;
                @endif
            "
        ></i>
    </button>
</form>
                        </div>


                        {{-- PRODUCT DETAILS --}}
                        <a
                            href="{{ route('buyer.product', ['slug' => $slug]) }}"
                            class="block p-3.5 sm:p-4"
                        >


                            {{-- CATEGORY --}}
                            <p class="text-[11px] font-medium text-[#1F6F5B] mb-1">
                                {{ $product['category'] }}
                            </p>


                            {{-- NAME --}}
                            <h3
                                class="text-sm font-medium text-gray-800 leading-5 line-clamp-2 min-h-[40px] group-hover:text-[#1F6F5B] transition"
                            >
                                {{ $product['name'] }}
                            </h3>


                            {{-- PRICE --}}
                            <div class="flex items-center gap-2 mt-3">

                                <span class="text-lg font-bold text-[#1F6F5B]">
                                    ₱{{ $product['price'] }}
                                </span>

                                <span class="text-xs text-gray-400 line-through">
                                    ₱{{ $product['old_price'] }}
                                </span>

                            </div>


                            {{-- RATING + SOLD --}}
                            <div class="flex items-center gap-2 mt-2">

                                <div class="flex items-center gap-1">

                                    <i
                                        data-lucide="star"
                                        class="w-3.5 h-3.5 fill-[#F59E0B] text-[#F59E0B]"
                                    ></i>

                                    <span class="text-xs font-medium text-gray-600">
                                        {{ $product['rating'] }}
                                    </span>

                                </div>

                                <span class="text-xs text-gray-400">
                                    {{ $product['sold'] }} sold
                                </span>

                            </div>


                            {{-- SHIPPING --}}
                            <div class="flex items-center gap-1 mt-2">

                                <i
                                    data-lucide="truck"
                                    class="w-3.5 h-3.5 text-[#1F6F5B]"
                                ></i>

                                <span class="text-[11px] text-gray-400">
                                    Free shipping available
                                </span>

                            </div>

                        </a>

                    </article>

                @endforeach

            </div>


            {{-- ================================================= --}}
            {{-- PAGINATION --}}
            {{-- ================================================= --}}

            <div class="flex items-center justify-center gap-2 mt-10">

                <button
                    type="button"
                    class="w-9 h-9 rounded-lg border border-gray-200 flex items-center justify-center text-gray-400 hover:border-[#1F6F5B] hover:text-[#1F6F5B] transition"
                >
                    <i
                        data-lucide="chevron-left"
                        class="w-4 h-4"
                    ></i>
                </button>

                <button
                    type="button"
                    class="w-9 h-9 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold"
                >
                    1
                </button>

                <button
                    type="button"
                    class="w-9 h-9 rounded-lg border border-gray-200 text-sm text-gray-600 hover:border-[#1F6F5B] hover:text-[#1F6F5B] transition"
                >
                    2
                </button>

                <button
                    type="button"
                    class="w-9 h-9 rounded-lg border border-gray-200 text-sm text-gray-600 hover:border-[#1F6F5B] hover:text-[#1F6F5B] transition"
                >
                    3
                </button>

                <span class="px-1 text-gray-400">
                    ...
                </span>

                <button
                    type="button"
                    class="w-9 h-9 rounded-lg border border-gray-200 flex items-center justify-center text-gray-600 hover:border-[#1F6F5B] hover:text-[#1F6F5B] transition"
                >
                    <i
                        data-lucide="chevron-right"
                        class="w-4 h-4"
                    ></i>
                </button>

            </div>

        </div>

    </div>

</section>

@endsection