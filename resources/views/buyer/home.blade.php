@extends('layouts.app')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | CATEGORIES
    |--------------------------------------------------------------------------
    */

    $categories = [
        [
            'name' => 'Fashion',
            'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Beauty',
            'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Home',
            'image' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Electronics',
            'image' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Sports',
            'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Food',
            'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Pets',
            'image' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Toys',
            'image' => 'https://images.unsplash.com/photo-1596461404969-9ae70f2830c1?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Automotive',
            'image' => 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=400&q=80'
        ],
        [
            'name' => 'Others',
            'image' => 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?auto=format&fit=crop&w=400&q=80'
        ],
    ];


    /*
    |--------------------------------------------------------------------------
    | PRODUCTS
    |--------------------------------------------------------------------------
    */

    $products = array_values($products ?? []);
@endphp


{{-- =========================================================
   HERO CAROUSEL
========================================================= --}}

<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 pt-6">

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.8fr)_minmax(260px,1fr)] gap-3">


        {{-- =====================================================
           MAIN HERO CAROUSEL
        ====================================================== --}}

        <div
            id="sukiHeroCarousel"
            class="relative overflow-hidden rounded-2xl bg-[#173F35]"
        >

            <div class="relative h-[280px] sm:h-[340px] md:h-[390px] lg:h-[410px]">


                {{-- =================================================
                   SLIDE 1
                ================================================== --}}

                <a
                    href="{{ route('buyer.shop') }}"
                    class="suki-slide absolute inset-0 block opacity-100 transition-opacity duration-500"
                    data-slide="0"
                >

                    <img
                        src="https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=1600&q=85"
                        alt="SUKI SHOP Shopping"
                        class="absolute inset-0 h-full w-full object-cover"
                    >

                    <div class="absolute inset-0 bg-gradient-to-r from-black/65 via-black/35 to-transparent"></div>

                    <div class="relative z-10 flex h-full items-center px-6 sm:px-9 md:px-12">

                        <div class="max-w-xl text-white">

                            <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/15 px-3.5 py-1.5 backdrop-blur-sm">

                                <span class="h-2 w-2 rounded-full bg-[#F59E0B]"></span>

                                <span class="text-[10px] sm:text-xs font-medium tracking-wide">
                                    YOUR EVERYDAY MARKETPLACE
                                </span>

                            </div>


                            <h1 class="mt-4 text-3xl sm:text-4xl md:text-5xl font-bold leading-tight">

                                Everything you need,
                                <span class="text-[#DDF3EC]">
                                    all in one place.
                                </span>

                            </h1>


                            <p class="mt-3 max-w-md text-xs sm:text-sm md:text-base leading-relaxed text-white/85">

                                Discover products from trusted sellers,
                                enjoy great deals, and choose convenient
                                delivery options with SUKI SHOP.

                            </p>


                            <div class="mt-6 flex flex-wrap gap-2.5">

                                <span
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#1F6F5B] px-5 py-2.5 text-xs sm:text-sm font-semibold text-white"
                                >

                                    Shop Now

                                    <i
                                        data-lucide="arrow-right"
                                        class="h-4 w-4"
                                    ></i>

                                </span>

                            </div>

                        </div>

                    </div>

                </a>


                {{-- =================================================
                   SLIDE 2
                ================================================== --}}

                <a
                    href="{{ route('buyer.shop') }}"
                    class="suki-slide pointer-events-none absolute inset-0 block opacity-0 transition-opacity duration-500"
                    data-slide="1"
                >

                    <img
                        src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1600&q=85"
                        alt="SUKI SHOP product discovery"
                        class="absolute inset-0 h-full w-full object-cover"
                    >

                    <div class="absolute inset-0 bg-gradient-to-r from-black/65 via-black/35 to-transparent"></div>

                    <div class="relative z-10 flex h-full items-center px-6 sm:px-9 md:px-12">

                        <div class="max-w-xl text-white">

                            <div class="inline-flex items-center gap-2 rounded-full bg-[#F59E0B] px-3.5 py-1.5">

                                <i
                                    data-lucide="zap"
                                    class="h-3.5 w-3.5"
                                ></i>

                                <span class="text-[10px] sm:text-xs font-bold tracking-wide">
                                    DISCOVER PRODUCTS
                                </span>

                            </div>


                            <h2 class="mt-4 text-3xl sm:text-4xl md:text-5xl font-bold leading-tight">

                                Products You
                                <span class="text-[#FFF3D6]">
                                    Don't Want to Miss.
                                </span>

                            </h2>


                            <p class="mt-3 max-w-md text-xs sm:text-sm md:text-base leading-relaxed text-white/85">

                                Explore products from approved shops
                                and choose your preferred options.

                            </p>


                            <div class="mt-6 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-xs sm:text-sm font-semibold text-[#1F6F5B]">

                                Browse Products

                                <i
                                    data-lucide="arrow-right"
                                    class="h-4 w-4"
                                ></i>

                            </div>

                        </div>

                    </div>

                </a>


                {{-- =================================================
                   SLIDE 3
                ================================================== --}}

                <a
                    href="{{ route('buyer.shop') }}"
                    class="suki-slide pointer-events-none absolute inset-0 block opacity-0 transition-opacity duration-500"
                    data-slide="2"
                >

                    <img
                        src="https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=1600&q=85"
                        alt="SUKI SHOP New Arrivals"
                        class="absolute inset-0 h-full w-full object-cover"
                    >

                    <div class="absolute inset-0 bg-gradient-to-r from-black/65 via-black/30 to-transparent"></div>

                    <div class="relative z-10 flex h-full items-center px-6 sm:px-9 md:px-12">

                        <div class="max-w-xl text-white">

                            <div class="inline-flex items-center rounded-full border border-white/20 bg-white/15 px-3.5 py-1.5 backdrop-blur-sm">

                                <span class="text-[10px] sm:text-xs font-semibold tracking-wide">
                                    NEW ARRIVALS
                                </span>

                            </div>


                            <h2 class="mt-4 text-3xl sm:text-4xl md:text-5xl font-bold leading-tight">

                                Fresh Styles.
                                <span class="text-[#DDF3EC]">
                                    Just for You.
                                </span>

                            </h2>


                            <p class="mt-3 max-w-md text-xs sm:text-sm md:text-base leading-relaxed text-white/85">

                                Explore the latest products and fresh
                                finds from SUKI SHOP sellers.

                            </p>


                            <div class="mt-6 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-xs sm:text-sm font-semibold text-[#1F6F5B]">

                                Explore Now

                                <i
                                    data-lucide="arrow-right"
                                    class="h-4 w-4"
                                ></i>

                            </div>

                        </div>

                    </div>

                </a>


                {{-- =================================================
                   SLIDE 4
                ================================================== --}}

                <a
                    href="{{ route('buyer.shop') }}"
                    class="suki-slide pointer-events-none absolute inset-0 block opacity-0 transition-opacity duration-500"
                    data-slide="3"
                >

                    <img
                        src="https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=1600&q=85"
                        alt="SUKI SHOP Beauty Essentials"
                        class="absolute inset-0 h-full w-full object-cover"
                    >

                    <div class="absolute inset-0 bg-gradient-to-r from-black/65 via-black/30 to-transparent"></div>

                    <div class="relative z-10 flex h-full items-center px-6 sm:px-9 md:px-12">

                        <div class="max-w-xl text-white">

                            <div class="inline-flex items-center rounded-full border border-white/20 bg-white/15 px-3.5 py-1.5 backdrop-blur-sm">

                                <span class="text-[10px] sm:text-xs font-semibold tracking-wide">
                                    BEAUTY PICKS
                                </span>

                            </div>


                            <h2 class="mt-4 text-3xl sm:text-4xl md:text-5xl font-bold leading-tight">

                                Your Everyday
                                <span class="text-[#DDF3EC]">
                                    Essentials.
                                </span>

                            </h2>


                            <p class="mt-3 max-w-md text-xs sm:text-sm md:text-base leading-relaxed text-white/85">

                                Shop beauty and personal care essentials
                                at prices you'll love.

                            </p>


                            <div class="mt-6 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-xs sm:text-sm font-semibold text-[#1F6F5B]">

                                Shop Beauty

                                <i
                                    data-lucide="arrow-right"
                                    class="h-4 w-4"
                                ></i>

                            </div>

                        </div>

                    </div>

                </a>


                {{-- =================================================
                   SLIDE 5
                ================================================== --}}

                <a
                    href="{{ route('buyer.shop') }}"
                    class="suki-slide pointer-events-none absolute inset-0 block opacity-0 transition-opacity duration-500"
                    data-slide="4"
                >

                    <img
                        src="https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1600&q=85"
                        alt="SUKI SHOP Home Essentials"
                        class="absolute inset-0 h-full w-full object-cover"
                    >

                    <div class="absolute inset-0 bg-gradient-to-r from-black/65 via-black/30 to-transparent"></div>

                    <div class="relative z-10 flex h-full items-center px-6 sm:px-9 md:px-12">

                        <div class="max-w-xl text-white">

                            <div class="inline-flex items-center rounded-full border border-white/20 bg-white/15 px-3.5 py-1.5 backdrop-blur-sm">

                                <span class="text-[10px] sm:text-xs font-semibold tracking-wide">
                                    HOME ESSENTIALS
                                </span>

                            </div>


                            <h2 class="mt-4 text-3xl sm:text-4xl md:text-5xl font-bold leading-tight">

                                Make Your Space
                                <span class="text-[#DDF3EC]">
                                    Feel Like Home.
                                </span>

                            </h2>


                            <p class="mt-3 max-w-md text-xs sm:text-sm md:text-base leading-relaxed text-white/85">

                                Find practical and beautiful pieces
                                for your everyday space.

                            </p>


                            <div class="mt-6 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-xs sm:text-sm font-semibold text-[#1F6F5B]">

                                Shop Home

                                <i
                                    data-lucide="arrow-right"
                                    class="h-4 w-4"
                                ></i>

                            </div>

                        </div>

                    </div>

                </a>

            </div>


            {{-- =================================================
               CAROUSEL DOTS
            ================================================== --}}

            <div class="absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1.5">

                @for($i = 0; $i < 5; $i++)

                    <button
                        type="button"
                        class="suki-dot h-1.5 rounded-full transition-all duration-300 {{ $i === 0 ? 'w-6 bg-white' : 'w-1.5 bg-white/50' }}"
                        data-index="{{ $i }}"
                        aria-label="Go to slide {{ $i + 1 }}"
                    ></button>

                @endfor

            </div>

        </div>


        {{-- =====================================================
           DESKTOP SIDE PROMOTIONS
        ====================================================== --}}

        <div class="hidden lg:grid grid-rows-2 gap-3">


            {{-- PROMO 1 --}}

            <a
                href="{{ route('buyer.shop') }}"
                class="group relative overflow-hidden rounded-2xl bg-[#EEF8F3]"
            >

                <img
                    src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85"
                    alt="Fashion Deals"
                    class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                >

                <div class="absolute inset-0 bg-gradient-to-r from-black/65 to-black/10"></div>

                <div class="relative z-10 flex h-full items-end p-5">

                    <div class="text-white">

                        <p class="text-[10px] font-semibold uppercase tracking-wider text-white/75">
                            Today's Picks
                        </p>

                        <h3 class="mt-1 text-lg font-bold">
                            Step Into Something New
                        </h3>

                        <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold">

                            Shop Now

                            <i
                                data-lucide="arrow-right"
                                class="h-3.5 w-3.5"
                            ></i>

                        </span>

                    </div>

                </div>

            </a>


            {{-- PROMO 2 --}}

            <a
                href="{{ route('buyer.shop') }}"
                class="group relative overflow-hidden rounded-2xl bg-[#DDF3EC]"
            >

                <img
                    src="https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=900&q=85"
                    alt="Beauty Essentials"
                    class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                >

                <div class="absolute inset-0 bg-gradient-to-r from-black/65 to-black/10"></div>

                <div class="relative z-10 flex h-full items-end p-5">

                    <div class="text-white">

                        <p class="text-[10px] font-semibold uppercase tracking-wider text-white/75">
                            Self Care
                        </p>

                        <h3 class="mt-1 text-lg font-bold">
                            Little Things, Big Difference
                        </h3>

                        <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold">

                            Explore

                            <i
                                data-lucide="arrow-right"
                                class="h-3.5 w-3.5"
                            ></i>

                        </span>

                    </div>

                </div>

            </a>

        </div>

    </div>


    {{-- =====================================================
       MOBILE PROMOTIONS
    ====================================================== --}}

    <div class="mt-3 grid grid-cols-2 gap-3 lg:hidden">


        {{-- MOBILE PROMO 1 --}}

        <a
            href="{{ route('buyer.shop') }}"
            class="group relative h-24 sm:h-28 overflow-hidden rounded-xl"
        >

            <img
                src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=700&q=85"
                alt="Fashion Finds"
                class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
            >

            <div class="absolute inset-0 bg-black/45"></div>

            <div class="relative z-10 flex h-full items-end p-3 sm:p-4 text-white">

                <div>

                    <p class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wide text-white/75">
                        Today's Picks
                    </p>

                    <p class="mt-0.5 text-xs sm:text-sm font-bold">
                        Fashion Finds
                    </p>

                </div>

            </div>

        </a>


        {{-- MOBILE PROMO 2 --}}

        <a
            href="{{ route('buyer.shop') }}"
            class="group relative h-24 sm:h-28 overflow-hidden rounded-xl"
        >

            <img
                src="https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=700&q=85"
                alt="Beauty Essentials"
                class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
            >

            <div class="absolute inset-0 bg-black/45"></div>

            <div class="relative z-10 flex h-full items-end p-3 sm:p-4 text-white">

                <div>

                    <p class="text-[9px] sm:text-[10px] font-semibold uppercase tracking-wide text-white/75">
                        Self Care
                    </p>

                    <p class="mt-0.5 text-xs sm:text-sm font-bold">
                        Beauty Essentials
                    </p>

                </div>

            </div>

        </a>

    </div>

</section>


{{-- =========================================================
   QUICK BENEFITS
========================================================= --}}

<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-6">

    <div class="grid grid-cols-2 md:grid-cols-4 overflow-hidden rounded-2xl border border-gray-200 bg-white">


        {{-- ITEM 1 --}}

        <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-5 md:border-b-0 md:border-r">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">

                <i
                    data-lucide="shield-check"
                    class="h-5 w-5 text-[#1F6F5B]"
                ></i>

            </div>

            <div>

                <p class="text-sm font-semibold text-gray-800">
                    Secure Shopping
                </p>

                <p class="mt-0.5 text-xs text-gray-400">
                    Shop with confidence
                </p>

            </div>

        </div>


        {{-- ITEM 2 --}}

        <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-5 md:border-b-0 md:border-r">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">

                <i
                    data-lucide="truck"
                    class="h-5 w-5 text-[#1F6F5B]"
                ></i>

            </div>

            <div>

                <p class="text-sm font-semibold text-gray-800">
                    Flexible Delivery
                </p>

                <p class="mt-0.5 text-xs text-gray-400">
                    Multiple delivery options
                </p>

            </div>

        </div>


        {{-- ITEM 3 --}}

        <div class="flex items-center gap-3 border-r border-gray-200 px-4 py-5">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">

                <i
                    data-lucide="badge-percent"
                    class="h-5 w-5 text-[#1F6F5B]"
                ></i>

            </div>

            <div>

                <p class="text-sm font-semibold text-gray-800">
                    Great Deals
                </p>

                <p class="mt-0.5 text-xs text-gray-400">
                    Save more every day
                </p>

            </div>

        </div>


        {{-- ITEM 4 --}}

        <div class="flex items-center gap-3 px-4 py-5">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">

                <i
                    data-lucide="headphones"
                    class="h-5 w-5 text-[#1F6F5B]"
                ></i>

            </div>

            <div>

                <p class="text-sm font-semibold text-gray-800">
                    Customer Support
                </p>

                <p class="mt-0.5 text-xs text-gray-400">
                    We're here to help
                </p>

            </div>

        </div>

    </div>

</section>


{{-- =========================================================
   CATEGORIES
========================================================= --}}

<section
    id="categories"
    class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-12"
>

    <div class="mb-5 flex items-end justify-between">

        <div>

            <p class="text-xs font-semibold uppercase tracking-wider text-[#1F6F5B]">
                Explore
            </p>

            <h2 class="mt-1 text-xl font-bold text-gray-900 md:text-2xl">
                Shop by Category
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Find what you need faster.
            </p>

        </div>


        <a
            href="{{ route('buyer.shop') }}"
            class="hidden items-center gap-1 text-sm font-semibold text-[#1F6F5B] hover:text-[#155244] sm:inline-flex"
        >

            View All

            <i
                data-lucide="arrow-right"
                class="h-4 w-4"
            ></i>

        </a>

    </div>


    <div class="grid grid-cols-5 gap-3 sm:grid-cols-5 sm:gap-4 md:grid-cols-10">

        @foreach($categories as $category)

            <a
                href="{{ route('buyer.shop') }}"
                class="group min-w-0 text-center"
            >

                <div class="aspect-square overflow-hidden rounded-full border border-gray-200 bg-white transition duration-200 group-hover:border-[#1F6F5B] group-hover:shadow-md">

                    <img
                        src="{{ $category['image'] }}"
                        alt="{{ $category['name'] }}"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                    >

                </div>


                <p class="mt-2 truncate text-[11px] font-medium text-gray-700 group-hover:text-[#1F6F5B] sm:text-xs">

                    {{ $category['name'] }}

                </p>

            </a>

        @endforeach

    </div>


    <div class="mt-5 flex justify-center sm:hidden">

        <a
            href="{{ route('buyer.shop') }}"
            class="inline-flex items-center gap-1 text-sm font-semibold text-[#1F6F5B]"
        >

            View All Categories

            <i
                data-lucide="arrow-right"
                class="h-4 w-4"
            ></i>

        </a>

    </div>

</section>


{{-- =========================================================
   DISCOVER PRODUCTS
========================================================= --}}

<section id="featured-products" class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-14">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4"><h2 class="text-xl font-bold">Featured Products</h2><a class="text-sm font-semibold text-[#1F6F5B]" href="{{ route('buyer.shop') }}">See All</a></div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @forelse($products as $product)
                <a href="{{ route('buyer.product',$product['slug']) }}" class="border-b border-r border-gray-100">
                    <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="aspect-square w-full object-cover">
                    <div class="p-4"><h3 class="text-sm">{{ $product['name'] }}</h3><p class="mt-3 font-bold text-[#1F6F5B]">&#8369;{{ number_format($product['price'],2) }}</p><p class="text-xs text-gray-500">{{ $product['sold'] }} sold</p></div>
                </a>
            @empty<p class="p-6 text-sm text-gray-500">No products are available yet.</p>@endforelse
        </div>
    </div>
</section>


{{-- =========================================================
   BEST SELLERS
========================================================= --}}

<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-14">

    <div class="mb-5 flex items-end justify-between">

        <div>

            <p class="text-xs font-semibold uppercase tracking-wider text-[#1F6F5B]">
                Popular Now
            </p>

            <h2 class="mt-1 text-xl font-bold text-gray-900 md:text-2xl">
                Best Sellers
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Popular products ranked by non-cancelled orders.
            </p>

        </div>


        <a
            href="{{ route('buyer.shop',['sort'=>'best_selling']) }}"
            class="hidden items-center gap-1 text-sm font-semibold text-[#1F6F5B] sm:inline-flex"
        >

            See All

            <i
                data-lucide="arrow-right"
                class="h-4 w-4"
            ></i>

        </a>

    </div>


    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">

        @forelse($bestSellers as $product)

            <a
                href="{{ route('buyer.product', ['slug' => $product['slug']]) }}"
                class="group overflow-hidden rounded-xl border border-gray-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-[#DDF3EC] hover:shadow-lg"
            >

                <div class="relative aspect-square overflow-hidden bg-gray-100">

                    <img
                        src="{{ $product['image'] }}"
                        alt="{{ $product['name'] }}"
                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    >

                    <span class="absolute left-3 top-3 rounded-md bg-[#1F6F5B] px-2 py-1 text-[10px] font-semibold text-white">
                        BEST SELLER
                    </span>

                </div>


                <div class="p-4">

                    <h3 class="min-h-[40px] line-clamp-2 text-sm text-gray-800 group-hover:text-[#1F6F5B]">

                        {{ $product['name'] }}

                    </h3>


                    <p class="mt-2 text-lg font-bold text-[#1F6F5B]">
                        ₱{{ number_format($product['price'],2) }}
                    </p>


                    <div class="mt-1 flex items-center gap-1">

                        <i
                            data-lucide="star"
                            class="h-3.5 w-3.5 fill-[#F59E0B] text-[#F59E0B]"
                        ></i>

                        <span class="text-xs text-gray-500">
                            {{ $product['rating'] }}
                        </span>

                        <span class="text-xs text-gray-400">
                            · {{ $product['sold'] }} sold
                        </span>

                    </div>

                </div>

            </a>

        @empty<p class="p-6 text-sm text-gray-500">No ordered products to rank yet.</p>@endforelse

    </div>

</section>


{{-- =========================================================
   NEW ARRIVALS
========================================================= --}}

<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-14">

    <div class="rounded-2xl bg-[#EEF8F3] p-6 md:p-8">

        <div class="mb-6 flex items-end justify-between">

            <div>

                <p class="text-xs font-semibold uppercase tracking-wider text-[#1F6F5B]">
                    Fresh Finds
                </p>

                <h2 class="mt-1 text-xl font-bold text-[#173F35] md:text-2xl">
                    New Arrivals
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Fresh products recently added to SUKI SHOP.
                </p>

            </div>


            <a
                href="{{ route('buyer.shop') }}"
                class="hidden items-center gap-1 text-sm font-semibold text-[#1F6F5B] sm:inline-flex"
            >

                View All

                <i
                    data-lucide="arrow-right"
                    class="h-4 w-4"
                ></i>

            </a>

        </div>


        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">

            @foreach($products as $product)

                <a
                    href="{{ route('buyer.product', ['slug' => $product['slug']]) }}"
                    class="group overflow-hidden rounded-xl border border-[#DCEDE6] bg-white transition hover:shadow-md"
                >

                    <div class="aspect-square overflow-hidden bg-gray-100">

                        <img
                            src="{{ $product['image'] }}"
                            alt="{{ $product['name'] }}"
                            class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        >

                    </div>


                    <div class="p-3.5">

                        <h3 class="min-h-[40px] line-clamp-2 text-sm text-gray-800">

                            {{ $product['name'] }}

                        </h3>

                        <p class="mt-2 text-base font-bold text-[#1F6F5B]">
                            ₱{{ number_format($product['price'],2) }}
                        </p>

                        <p class="mt-1 text-xs text-gray-400">
                            New on SUKI SHOP
                        </p>

                    </div>

                </a>

            @endforeach

        </div>

    </div>

</section>


{{-- =========================================================
   SELLER / RIDER CTA
========================================================= --}}

<section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-10 mt-14 mb-16">

    <div class="grid gap-4 md:grid-cols-2">


        {{-- SELLER --}}

        <div class="rounded-2xl bg-[#173F35] p-7 text-white md:p-8">

            <div class="flex items-start justify-between gap-5">

                <div>

                    <p class="text-xs font-semibold uppercase tracking-wider text-[#DDF3EC]">
                        Grow with SUKI SHOP
                    </p>

                    <h2 class="mt-2 text-xl font-bold md:text-2xl">
                        Start selling with SUKI SHOP.
                    </h2>

                    <p class="mt-2 max-w-sm text-sm leading-relaxed text-white/70">

                        Reach more customers, manage your products,
                        and grow your online business with SUKI SHOP.

                    </p>


                    <a
                        href="#"
                        class="mt-5 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-[#173F35] transition hover:bg-gray-100"
                    >

                        Become a Seller

                        <i
                            data-lucide="arrow-right"
                            class="h-4 w-4"
                        ></i>

                    </a>

                </div>


                <div class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/10 sm:flex">

                    <i
                        data-lucide="store"
                        class="h-6 w-6 text-[#DDF3EC]"
                    ></i>

                </div>

            </div>

        </div>


        {{-- RIDER --}}

        <div class="rounded-2xl border border-[#DDF3EC] bg-[#F7FCFA] p-7 md:p-8">

            <div class="flex items-start justify-between gap-5">

                <div>

                    <p class="text-xs font-semibold uppercase tracking-wider text-[#1F6F5B]">
                        Deliver with SUKI SHOP
                    </p>

                    <h2 class="mt-2 text-xl font-bold text-[#173F35] md:text-2xl">
                        Become a SUKI SHOP Rider.
                    </h2>

                    <p class="mt-2 max-w-sm text-sm leading-relaxed text-gray-500">

                        Earn while delivering orders and help bring
                        products closer to customers.

                    </p>


                    <a
                        href="/rider/register"
                        class="mt-5 inline-flex items-center gap-2 rounded-lg bg-[#1F6F5B] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#155244]"
                    >

                        Apply as Rider

                        <i
                            data-lucide="arrow-right"
                            class="h-4 w-4"
                        ></i>

                    </a>

                </div>


                <div class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#DDF3EC] sm:flex">

                    <i
                        data-lucide="bike"
                        class="h-6 w-6 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>

    </div>

</section>


{{-- =========================================================
   HERO CAROUSEL SCRIPT
========================================================= --}}

@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const carousel = document.getElementById('sukiHeroCarousel');

    if (!carousel) {
        return;
    }

    const slides = carousel.querySelectorAll('.suki-slide');
    const dots = carousel.querySelectorAll('.suki-dot');

    let currentSlide = 0;
    let carouselInterval;


    /*
    |--------------------------------------------------------------------------
    | SHOW SLIDE
    |--------------------------------------------------------------------------
    */

    function showSlide(index) {

        currentSlide = index;

        slides.forEach(function (slide, i) {

            if (i === index) {

                slide.classList.remove(
                    'opacity-0',
                    'pointer-events-none'
                );

                slide.classList.add('opacity-100');

            } else {

                slide.classList.remove('opacity-100');

                slide.classList.add(
                    'opacity-0',
                    'pointer-events-none'
                );

            }

        });


        /*
        |--------------------------------------------------------------------------
        | UPDATE DOTS
        |--------------------------------------------------------------------------
        */

        dots.forEach(function (dot, i) {

            if (i === index) {

                dot.classList.remove(
                    'w-1.5',
                    'bg-white/50'
                );

                dot.classList.add(
                    'w-6',
                    'bg-white'
                );

            } else {

                dot.classList.remove(
                    'w-6',
                    'bg-white'
                );

                dot.classList.add(
                    'w-1.5',
                    'bg-white/50'
                );

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | NEXT SLIDE
    |--------------------------------------------------------------------------
    */

    function nextSlide() {

        const nextSlide =
            (currentSlide + 1) % slides.length;

        showSlide(nextSlide);

    }


    /*
    |--------------------------------------------------------------------------
    | START AUTO PLAY
    |--------------------------------------------------------------------------
    */

    function startCarousel() {

        clearInterval(carouselInterval);

        carouselInterval = setInterval(
            nextSlide,
            4500
        );

    }


    /*
    |--------------------------------------------------------------------------
    | DOT CLICK
    |--------------------------------------------------------------------------
    */

    dots.forEach(function (dot) {

        dot.addEventListener('click', function () {

            const index =
                Number(this.dataset.index);

            showSlide(index);

            startCarousel();

        });

    });


    /*
    |--------------------------------------------------------------------------
    | PAUSE ON HOVER
    |--------------------------------------------------------------------------
    */

    carousel.addEventListener('mouseenter', function () {

        clearInterval(carouselInterval);

    });


    /*
    |--------------------------------------------------------------------------
    | RESUME AFTER HOVER
    |--------------------------------------------------------------------------
    */

    carousel.addEventListener('mouseleave', function () {

        startCarousel();

    });


    /*
    |--------------------------------------------------------------------------
    | INITIALIZE
    |--------------------------------------------------------------------------
    */

    showSlide(0);

    startCarousel();

});
</script>

@endpush

@endsection
