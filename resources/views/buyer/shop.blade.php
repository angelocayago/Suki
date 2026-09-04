@extends('layouts.app')

@section('content')

@php
    $products = [
        [
            'name' => 'Minimalist Shoulder Bag',
            'slug' => 'shoulder-bag',
            'price' => '399',
            'old_price' => '599',
            'rating' => '4.9',
            'sold' => '1.2k',
            'category' => 'Fashion',
            'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=700&q=80'
        ],
        [
            'name' => 'Wireless Headphones',
            'slug' => 'wireless-headphones',
            'price' => '899',
            'old_price' => '1,299',
            'rating' => '4.8',
            'sold' => '856',
            'category' => 'Electronics',
            'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=700&q=80'
        ],
        [
            'name' => 'Ceramic Home Set',
            'slug' => 'ceramic-home-set',
            'price' => '549',
            'old_price' => '799',
            'rating' => '4.7',
            'sold' => '642',
            'category' => 'Home',
            'image' => 'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=700&q=80'
        ],
        [
            'name' => 'Everyday Sneakers',
            'slug' => 'everyday-sneakers',
            'price' => '799',
            'old_price' => '1,099',
            'rating' => '4.9',
            'sold' => '2.1k',
            'category' => 'Fashion',
            'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=700&q=80'
        ],
        [
            'name' => 'Skincare Essentials Set',
            'slug' => 'skincare-essentials',
            'price' => '459',
            'old_price' => '699',
            'rating' => '4.8',
            'sold' => '934',
            'category' => 'Beauty',
            'image' => 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=700&q=80'
        ],
        [
            'name' => 'Classic Analog Watch',
            'slug' => 'analog-watch',
            'price' => '699',
            'old_price' => '999',
            'rating' => '4.8',
            'sold' => '721',
            'category' => 'Fashion',
            'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=700&q=80'
        ],
        [
            'name' => 'Portable Bluetooth Speaker',
            'slug' => 'bluetooth-speaker',
            'price' => '649',
            'old_price' => '899',
            'rating' => '4.7',
            'sold' => '534',
            'category' => 'Electronics',
            'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=700&q=80'
        ],
        [
            'name' => 'Modern Table Lamp',
            'slug' => 'table-lamp',
            'price' => '499',
            'old_price' => '799',
            'rating' => '4.6',
            'sold' => '438',
            'category' => 'Home',
            'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=700&q=80'
        ],
    ];

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
        'Automotive'
    ];
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
                    SUKI Marketplace
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
                href="{{ route('buyer.shop') }}"
                class="shrink-0 px-4 py-2.5 rounded-lg border
                {{ $category === 'All Products'
                    ? 'bg-[#1F6F5B] text-white border-[#1F6F5B]'
                    : 'bg-white text-gray-600 border-gray-200 hover:border-[#1F6F5B] hover:text-[#1F6F5B]'
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
                <div class="px-5 py-5 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900 mb-4">
                        Price Range
                    </h3>


                    <div class="flex items-center gap-2">

                        <input
                            type="number"
                            placeholder="Min"
                            class="w-full px-3 py-2.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-[#1F6F5B] focus:ring-1 focus:ring-[#1F6F5B]"
                        >

                        <span class="text-gray-300">
                            —
                        </span>

                        <input
                            type="number"
                            placeholder="Max"
                            class="w-full px-3 py-2.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:border-[#1F6F5B] focus:ring-1 focus:ring-[#1F6F5B]"
                        >

                    </div>


                    <button
                        type="button"
                        class="w-full mt-3 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-xs font-semibold hover:bg-[#155244] transition"
                    >
                        Apply Price
                    </button>

                </div>



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


                    {{-- RESULT COUNT --}}
                    <div>

                        <p class="text-sm text-gray-700">

                            <span class="font-semibold">
                                {{ count($products) }}
                            </span>

                            products available

                        </p>

                    </div>


                    {{-- ACTIONS --}}
                    <div class="flex items-center gap-2">


                        {{-- MOBILE FILTER --}}
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


                        {{-- SORT --}}
                        <div class="flex items-center gap-2">

                            <span class="hidden sm:block text-xs text-gray-400">
                                Sort by
                            </span>

                            <select
                                class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-[#1F6F5B]"
                            >

                                <option>Recommended</option>
                                <option>Newest</option>
                                <option>Best Selling</option>
                                <option>Price: Low to High</option>
                                <option>Price: High to Low</option>

                            </select>

                        </div>

                    </div>

                </div>

            </div>



            {{-- ================================================= --}}
            {{-- PRODUCT GRID --}}
            {{-- ================================================= --}}

            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">


                @foreach($products as $product)

                    <a
                        href="{{ route('buyer.product', ['slug' => $product['slug']]) }}"
                        class="group bg-white rounded-xl overflow-hidden border border-gray-200 hover:border-[#D5EAE2] hover:shadow-lg hover:-translate-y-0.5 transition duration-200"
                    >


                        {{-- PRODUCT IMAGE --}}
                        <div class="relative aspect-square overflow-hidden bg-gray-100">

                            <img
                                src="{{ $product['image'] }}"
                                alt="{{ $product['name'] }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                            >


                            {{-- SALE BADGE --}}
                            <span class="absolute top-3 left-3 bg-[#F59E0B] text-white text-[10px] font-bold px-2 py-1 rounded-md shadow-sm">
                                SALE
                            </span>


                            {{-- WISHLIST --}}
                            <button
                                type="button"
                                onclick="event.preventDefault(); event.stopPropagation();"
                                class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/95 flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-white shadow-sm transition"
                            >

                                <i
                                    data-lucide="heart"
                                    class="w-4 h-4"
                                ></i>

                            </button>

                        </div>



                        {{-- PRODUCT DETAILS --}}
                        <div class="p-3.5 sm:p-4">


                            {{-- CATEGORY --}}
                            <p class="text-[11px] font-medium text-[#1F6F5B] mb-1">
                                {{ $product['category'] }}
                            </p>


                            {{-- NAME --}}
                            <h3 class="text-sm font-medium text-gray-800 leading-5 line-clamp-2 min-h-[40px] group-hover:text-[#1F6F5B] transition">

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

                        </div>

                    </a>

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