<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="SUKI SHOP is a connected marketplace where buyers discover products, sellers grow their businesses, and riders and logistics partners move every order from seller to doorstep."
    >

    <title>SUKI SHOP — Shop. Sell. Deliver. Together.</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        ::selection {
            background: #DDF3EC;
            color: #173F35;
        }

        .hero-slide {
            opacity: 0;
            visibility: hidden;
            transform: scale(1.025);
            transition:
                opacity 800ms ease,
                visibility 800ms ease,
                transform 6500ms ease;
        }

        .hero-slide.is-active {
            opacity: 1;
            visibility: visible;
            transform: scale(1);
        }

        .hero-copy {
            opacity: 0;
            transform: translateY(18px);
            transition:
                opacity 650ms ease 180ms,
                transform 650ms ease 180ms;
        }

        .hero-slide.is-active .hero-copy {
            opacity: 1;
            transform: translateY(0);
        }

        .hero-thumb {
            opacity: .48;
            transition:
                opacity 250ms ease,
                transform 250ms ease,
                border-color 250ms ease;
        }

        .hero-thumb.is-active {
            opacity: 1;
            transform: translateY(-3px);
            border-color: rgba(255, 255, 255, .9);
        }

        @media (prefers-reduced-motion: reduce) {
            .hero-slide,
            .hero-copy,
            .hero-thumb {
                transition: none !important;
            }
        }
    </style>
</head>


<body class="bg-[#F8FAF8] text-[#173F35] antialiased">


@php

    $heroSlides = [
        [
            'eyebrow' => 'SHOP • SELL • DELIVER • TOGETHER',
            'title' => 'A marketplace made',
            'highlight' => 'for everyday connections.',
            'description' => 'Discover products from marketplace sellers while SUKI SHOP connects ordering, fulfillment, and delivery in one experience.',
            'primary' => 'Explore Marketplace',
            'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=2000&q=90',
            'label' => 'Marketplace',
            'type' => 'buyer',
        ],

        [
            'eyebrow' => 'FOR MARKETPLACE SELLERS',
            'title' => 'Turn local products into',
            'highlight' => 'bigger opportunities.',
            'description' => 'Build your presence, manage products, receive orders, prepare parcels, and grow through a connected marketplace.',
            'primary' => 'Start Selling',
            'image' => 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?auto=format&fit=crop&w=2000&q=90',
            'label' => 'For Sellers',
            'type' => 'seller',
        ],

        [
            'eyebrow' => 'CONNECTED FULFILLMENT',
            'title' => 'Move orders',
            'highlight' => 'from seller to doorstep.',
            'description' => 'Riders and logistics partners work together through pickup, sorting, assignment, and final delivery.',
            'primary' => 'See How It Works',
            'image' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=2000&q=90',
            'label' => 'Delivery Network',
            'type' => 'delivery',
        ],

        [
            'eyebrow' => 'BECOME PART OF THE NETWORK',
            'title' => 'Deliver more than',
            'highlight' => 'just parcels.',
            'description' => 'Become part of the rider network that helps move customer orders through the SUKI SHOP ecosystem.',
            'primary' => 'Apply as Rider',
            'image' => 'https://images.unsplash.com/photo-1616401784845-180882ba9ba8?auto=format&fit=crop&w=2000&q=90',
            'label' => 'For Riders',
            'type' => 'rider',
        ],
    ];


    $categories = [
        [
            'name' => 'Fashion',
            'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=900&q=88',
        ],
        [
            'name' => 'Beauty',
            'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=88',
        ],
        [
            'name' => 'Home & Living',
            'image' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=900&q=88',
        ],
        [
            'name' => 'Electronics',
            'image' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?auto=format&fit=crop&w=900&q=88',
        ],
        [
            'name' => 'Food',
            'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=88',
        ],
        [
            'name' => 'Sports',
            'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=88',
        ],
    ];


    $publicJourney = [
        [
            'number' => '01',
            'title' => 'Discover',
            'description' => 'Browse products from marketplace sellers.',
        ],
        [
            'number' => '02',
            'title' => 'Order',
            'description' => 'Choose products and complete checkout.',
        ],
        [
            'number' => '03',
            'title' => 'Seller Prepares',
            'description' => 'The seller confirms, packs, and prepares the parcel.',
        ],
        [
            'number' => '04',
            'title' => 'Pickup',
            'description' => 'A rider collects the parcel from the seller.',
        ],
        [
            'number' => '05',
            'title' => 'Sort & Assign',
            'description' => 'Logistics sorts by destination and assigns a rider.',
        ],
        [
            'number' => '06',
            'title' => 'Deliver',
            'description' => 'The assigned rider brings the order to the buyer.',
        ],
        [
            'number' => '07',
            'title' => 'Receive',
            'description' => 'The buyer receives and confirms the order.',
        ],
    ];

@endphp


{{-- ========================================================= --}}
{{-- ANNOUNCEMENT BAR --}}
{{-- ========================================================= --}}

<div class="hidden bg-[#173F35] text-white md:block">

    <div class="mx-auto flex h-8 max-w-[1500px] items-center justify-between px-8">

        <p class="text-[10px] font-medium tracking-[0.04em] text-white/70">
            Good people. Better communities.
        </p>

        <div class="flex items-center gap-6 text-[10px] font-medium text-white/65">

            <a href="#how-it-works" class="hover:text-white">
                How It Works
            </a>

            <a href="{{ route('seller.register') }}" class="hover:text-white">
                Become a Seller
            </a>

            <a href="{{ route('rider.apply') }}" class="hover:text-white">
                Become a Rider
            </a>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- NAVIGATION --}}
{{-- ========================================================= --}}

<header class="sticky top-0 z-50 border-b border-[#173F35]/8 bg-[#F8FAF8]/95 backdrop-blur-xl">

    <div class="mx-auto flex h-[76px] max-w-[1500px] items-center px-5 sm:px-8 lg:px-10">

        {{-- LOGO --}}
        <a
            href="{{ route('landing') }}"
            class="flex shrink-0 items-center gap-3"
        >

            <img
                src="{{ asset('images/suki-logo.png') }}"
                alt="SUKI SHOP"
                class="h-11 w-11 object-contain"
            >

            <div class="leading-none">

                <span class="block text-[18px] font-bold tracking-[-0.04em] text-[#173F35]">
                    SUKI SHOP
                </span>

                <span class="mt-1 block text-[8px] font-medium uppercase tracking-[0.16em] text-[#1F6F5B]/60">
                    Connected Marketplace
                </span>

            </div>

        </a>


        {{-- DESKTOP NAV --}}
        <nav class="ml-12 hidden items-center gap-7 xl:flex">

            <a href="#about" class="text-[12px] font-medium text-[#173F35]/62 hover:text-[#1F6F5B]">
                About
            </a>

            <a href="#categories" class="text-[12px] font-medium text-[#173F35]/62 hover:text-[#1F6F5B]">
                Categories
            </a>

            <a href="#for-buyers" class="text-[12px] font-medium text-[#173F35]/62 hover:text-[#1F6F5B]">
                For Buyers
            </a>

            <a href="#for-sellers" class="text-[12px] font-medium text-[#173F35]/62 hover:text-[#1F6F5B]">
                For Sellers
            </a>

            <a href="#how-it-works" class="text-[12px] font-medium text-[#173F35]/62 hover:text-[#1F6F5B]">
                How It Works
            </a>

        </nav>


        {{-- ACTIONS --}}
        <div class="ml-auto flex items-center gap-3">

            <a
                href="{{ route('buyer.shop') }}"
                class="hidden h-10 items-center gap-2 rounded-full border border-[#173F35]/10 bg-white px-4 text-[11px] font-medium text-[#173F35]/60 hover:border-[#1F6F5B]/30 hover:text-[#1F6F5B] lg:inline-flex"
            >
                <i data-lucide="search" class="h-4 w-4"></i>

                Explore Marketplace
            </a>


            @auth

                <a
                    href="{{ route('buyer.home') }}"
                    class="inline-flex h-10 items-center rounded-full bg-[#173F35] px-5 text-[12px] font-semibold text-white hover:bg-[#1F6F5B]"
                >
                    Open Marketplace
                </a>

            @else

                <a
                    href="{{ route('login') }}"
                    class="hidden px-3 py-2 text-[12px] font-semibold text-[#173F35]/70 hover:text-[#1F6F5B] sm:block"
                >
                    Log in
                </a>

                <a
                    href="#join"
                    class="inline-flex h-10 items-center rounded-full bg-[#173F35] px-5 text-[12px] font-semibold text-white hover:bg-[#1F6F5B]"
                >
                    Get Started
                </a>

            @endauth


            <button
                id="landingMenuButton"
                type="button"
                class="grid h-10 w-10 place-items-center rounded-full border border-[#173F35]/10 bg-white text-[#173F35] xl:hidden"
                aria-label="Open navigation"
            >
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>

        </div>

    </div>


    {{-- MOBILE NAV --}}
    <div
        id="landingMobileMenu"
        class="hidden border-t border-[#173F35]/8 bg-[#F8FAF8] px-5 py-5 xl:hidden"
    >

        <nav class="flex flex-col">

            <a href="#about" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-white">
                About
            </a>

            <a href="#categories" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-white">
                Categories
            </a>

            <a href="#for-buyers" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-white">
                For Buyers
            </a>

            <a href="#for-sellers" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-white">
                For Sellers
            </a>

            <a href="#how-it-works" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-white">
                How It Works
            </a>

        </nav>

    </div>

</header>


<main>


{{-- ========================================================= --}}
{{-- FULL-BLEED HERO --}}
{{-- ========================================================= --}}

<section
    id="heroCarousel"
    class="relative min-h-[650px] overflow-hidden bg-[#102D26] lg:h-[calc(100svh-108px)] lg:min-h-[680px] lg:max-h-[850px]"
>

    @foreach($heroSlides as $index => $slide)

        <article
            class="hero-slide absolute inset-0 {{ $index === 0 ? 'is-active' : '' }}"
            data-slide="{{ $index }}"
        >

            <img
                src="{{ $slide['image'] }}"
                alt="{{ $slide['label'] }}"
                class="absolute inset-0 h-full w-full object-cover"
            >


            <div class="absolute inset-0 bg-gradient-to-r from-[#071A15]/95 via-[#102D26]/72 to-transparent"></div>

            <div class="absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-black/5"></div>


            <div class="hero-copy relative z-10 mx-auto flex h-full max-w-[1500px] items-center px-5 py-20 sm:px-8 lg:px-12">

                <div class="max-w-[760px] text-white">

                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-[#A9DDCB] sm:text-[11px]">
                        {{ $slide['eyebrow'] }}
                    </p>


                    <h1 class="mt-5 text-[42px] font-semibold leading-[1.02] tracking-[-0.055em] sm:text-[58px] lg:text-[72px] xl:text-[82px]">

                        {{ $slide['title'] }}

                        <span class="block text-[#DDF3EC]">
                            {{ $slide['highlight'] }}
                        </span>

                    </h1>


                    <p class="mt-6 max-w-[610px] text-[14px] leading-7 text-white/72 sm:text-[16px]">
                        {{ $slide['description'] }}
                    </p>


                    <div class="mt-8 flex flex-wrap gap-3">

                        @if($slide['type'] === 'seller')

                            <a
                                href="{{ route('seller.register') }}"
                                class="inline-flex h-12 items-center gap-2 rounded-full bg-[#1F6F5B] px-6 text-[13px] font-semibold text-white hover:bg-[#27836D]"
                            >
                                {{ $slide['primary'] }}

                                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </a>

                        @elseif($slide['type'] === 'rider')

                            <a
                                href="{{ route('rider.apply') }}"
                                class="inline-flex h-12 items-center gap-2 rounded-full bg-[#1F6F5B] px-6 text-[13px] font-semibold text-white hover:bg-[#27836D]"
                            >
                                {{ $slide['primary'] }}

                                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </a>

                        @elseif($slide['type'] === 'delivery')

                            <a
                                href="#how-it-works"
                                class="inline-flex h-12 items-center gap-2 rounded-full bg-[#1F6F5B] px-6 text-[13px] font-semibold text-white hover:bg-[#27836D]"
                            >
                                {{ $slide['primary'] }}

                                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </a>

                        @else

                            <a
                                href="{{ route('buyer.shop') }}"
                                class="inline-flex h-12 items-center gap-2 rounded-full bg-[#1F6F5B] px-6 text-[13px] font-semibold text-white hover:bg-[#27836D]"
                            >
                                {{ $slide['primary'] }}

                                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </a>

                        @endif


                        <a
                            href="#about"
                            class="inline-flex h-12 items-center rounded-full border border-white/20 bg-white/8 px-6 text-[13px] font-semibold text-white backdrop-blur-md hover:bg-white/15"
                        >
                            Learn More
                        </a>

                    </div>

                </div>

            </div>

        </article>

    @endforeach


    {{-- ARROWS --}}
    <button
        id="heroPrev"
        type="button"
        class="absolute left-5 top-1/2 z-30 hidden h-12 w-12 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-black/15 text-white backdrop-blur-md hover:bg-black/30 sm:grid"
        aria-label="Previous slide"
    >
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </button>


    <button
        id="heroNext"
        type="button"
        class="absolute right-5 top-1/2 z-30 hidden h-12 w-12 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-black/15 text-white backdrop-blur-md hover:bg-black/30 sm:grid"
        aria-label="Next slide"
    >
        <i data-lucide="chevron-right" class="h-5 w-5"></i>
    </button>


    {{-- THUMBNAILS --}}
    <div class="absolute bottom-7 left-1/2 z-30 hidden w-[min(760px,74vw)] -translate-x-1/2 gap-2 lg:flex">

        @foreach($heroSlides as $index => $slide)

            <button
                type="button"
                class="hero-thumb relative h-[70px] flex-1 overflow-hidden rounded-xl border-2 border-transparent bg-black {{ $index === 0 ? 'is-active' : '' }}"
                data-hero-target="{{ $index }}"
            >

                <img
                    src="{{ $slide['image'] }}"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover opacity-70"
                >

                <div class="absolute inset-0 bg-gradient-to-t from-black/75 to-transparent"></div>

                <span class="absolute bottom-2 left-3 text-[9px] font-semibold text-white">
                    {{ $slide['label'] }}
                </span>

            </button>

        @endforeach

    </div>


    {{-- MOBILE DOTS --}}
    <div class="absolute bottom-7 left-1/2 z-30 flex -translate-x-1/2 items-center gap-2 lg:hidden">

        @foreach($heroSlides as $index => $slide)

            <button
                type="button"
                class="hero-dot h-2 rounded-full {{ $index === 0 ? 'w-7 bg-white' : 'w-2 bg-white/40' }}"
                data-hero-target="{{ $index }}"
            ></button>

        @endforeach

    </div>


    <button
        id="heroPause"
        type="button"
        class="absolute bottom-7 right-6 z-30 grid h-10 w-10 place-items-center rounded-full border border-white/20 bg-black/20 text-white backdrop-blur-md hover:bg-black/35"
        aria-label="Pause carousel"
    >
        <i data-lucide="pause" class="h-4 w-4"></i>
    </button>

</section>


{{-- ========================================================= --}}
{{-- WHAT IS SUKI SHOP --}}
{{-- ========================================================= --}}

<section
    id="about"
    class="mx-auto max-w-[1320px] px-5 py-20 sm:px-8 lg:py-28"
>

    <div class="grid items-center gap-14 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20">

        <div>

            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#1F6F5B]">
                What is SUKI SHOP?
            </p>


            <h2 class="mt-4 text-[36px] font-semibold leading-[1.1] tracking-[-0.045em] text-[#173F35] sm:text-[48px]">
                A marketplace built around people.
            </h2>


            <p class="mt-6 text-[14px] leading-7 text-[#173F35]/58 sm:text-[15px]">
                SUKI SHOP brings buyers and sellers together through one digital marketplace while connecting the people responsible for moving every order.
            </p>


            <p class="mt-4 text-[14px] leading-7 text-[#173F35]/58 sm:text-[15px]">
                From discovering products to final delivery, the platform is designed around one connected transaction.
            </p>


            <a
                href="#how-it-works"
                class="mt-7 inline-flex items-center gap-2 text-[13px] font-semibold text-[#1F6F5B]"
            >
                See how it works

                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </a>

        </div>


        {{-- EDITORIAL IMAGE --}}
        <div class="relative min-h-[480px] overflow-hidden rounded-[28px] sm:min-h-[580px]">

            <img
                src="https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=1500&q=88"
                alt="SUKI SHOP marketplace experience"
                class="absolute inset-0 h-full w-full object-cover"
            >

            <div class="absolute inset-0 bg-gradient-to-t from-[#071A15]/65 via-transparent to-transparent"></div>


            <div class="absolute bottom-0 left-0 max-w-[520px] p-7 text-white sm:p-10">

                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#BFE7D9]">
                    One connected experience
                </p>

                <h3 class="mt-3 text-2xl font-semibold tracking-[-0.03em] sm:text-3xl">
                    Discover. Order. Deliver.
                </h3>

            </div>

        </div>

    </div>

</section>


{{-- ========================================================= --}}
{{-- CATEGORY DISCOVERY --}}
{{-- ========================================================= --}}

<section
    id="categories"
    class="bg-white py-20 lg:py-24"
>

    <div class="mx-auto max-w-[1500px] px-5 sm:px-8 lg:px-10">

        <div class="mb-8 flex items-end justify-between gap-5">

            <div>

                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#1F6F5B]">
                    Explore SUKI SHOP
                </p>

                <h2 class="mt-3 text-[32px] font-semibold tracking-[-0.04em] text-[#173F35] sm:text-[42px]">
                    Shop by category.
                </h2>

            </div>


            <a
                href="{{ route('buyer.shop') }}"
                class="hidden items-center gap-2 text-[12px] font-semibold text-[#1F6F5B] sm:inline-flex"
            >
                View all products

                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </a>

        </div>


        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">

            @foreach($categories as $category)

                <a
                    href="{{ route('buyer.shop') }}"
                    class="group relative overflow-hidden rounded-[18px]"
                >

                    <div class="aspect-[0.9] overflow-hidden bg-[#EAF2ED]">

                        <img
                            src="{{ $category['image'] }}"
                            alt="{{ $category['name'] }}"
                            class="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                        >

                    </div>


                    <div class="absolute inset-0 bg-gradient-to-t from-[#071A15]/70 via-transparent to-transparent"></div>


                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between p-4">

                        <span class="text-[12px] font-semibold text-white">
                            {{ $category['name'] }}
                        </span>

                        <i
                            data-lucide="arrow-up-right"
                            class="h-4 w-4 text-white"
                        ></i>

                    </div>

                </a>

            @endforeach

        </div>

    </div>

</section>


{{-- ========================================================= --}}
{{-- BUYER EDITORIAL --}}
{{-- ========================================================= --}}

<section
    id="for-buyers"
    class="mx-auto max-w-[1500px] px-5 py-20 sm:px-8 lg:px-10 lg:py-28"
>

    <div class="grid overflow-hidden rounded-[30px] bg-[#E8F4EF] lg:grid-cols-2">

        {{-- IMAGE --}}
        <div class="relative min-h-[460px] overflow-hidden lg:min-h-[650px]">

            <img
                src="https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=1400&q=90"
                alt="Buyer shopping experience"
                class="absolute inset-0 h-full w-full object-cover"
            >

        </div>


        {{-- CONTENT --}}
        <div class="flex items-center px-7 py-14 sm:px-10 lg:px-14">

            <div class="max-w-[560px]">

                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#1F6F5B]">
                    For Buyers
                </p>


                <h2 class="mt-4 text-[36px] font-semibold leading-[1.1] tracking-[-0.045em] text-[#173F35] sm:text-[48px]">
                    Discover products from marketplace sellers.
                </h2>


                <p class="mt-6 text-[14px] leading-7 text-[#173F35]/58">
                    Browse categories, view product details, manage your cart, place orders, and follow the delivery journey until your order reaches you.
                </p>


                <div class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5">

                    @foreach([
                        'Browse marketplace products',
                        'Manage cart and wishlist',
                        'Checkout and place orders',
                        'Track order progress',
                    ] as $item)

                        <div class="border-t border-[#173F35]/12 pt-3">

                            <p class="text-[11px] font-medium leading-5 text-[#173F35]/70">
                                {{ $item }}
                            </p>

                        </div>

                    @endforeach

                </div>


                <a
                    href="{{ route('buyer.shop') }}"
                    class="mt-9 inline-flex h-11 items-center gap-2 rounded-full bg-[#173F35] px-5 text-[12px] font-semibold text-white hover:bg-[#1F6F5B]"
                >
                    Explore Marketplace

                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>

            </div>

        </div>

    </div>

</section>


{{-- ========================================================= --}}
{{-- SELLER EDITORIAL --}}
{{-- ========================================================= --}}

<section
    id="for-sellers"
    class="bg-[#173F35] text-white"
>

    <div class="mx-auto grid max-w-[1500px] lg:grid-cols-2">

        {{-- CONTENT --}}
        <div class="flex items-center px-5 py-16 sm:px-8 lg:px-14 lg:py-24">

            <div class="max-w-[580px]">

                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8FD0BA]">
                    For Sellers
                </p>


                <h2 class="mt-4 text-[36px] font-semibold leading-[1.08] tracking-[-0.045em] sm:text-[50px]">
                    Grow your business through SUKI SHOP.
                </h2>


                <p class="mt-6 text-[14px] leading-7 text-white/58">
                    Build your marketplace presence, manage inventory, receive customer orders, prepare parcels, and hand them over for fulfillment.
                </p>


                <div class="mt-8 grid grid-cols-2 gap-x-7 gap-y-5">

                    @foreach([
                        'List and manage products',
                        'Monitor inventory',
                        'Receive customer orders',
                        'Prepare parcels for pickup',
                    ] as $item)

                        <div class="border-t border-white/12 pt-3">

                            <p class="text-[11px] font-medium leading-5 text-white/68">
                                {{ $item }}
                            </p>

                        </div>

                    @endforeach

                </div>


                <a
                    href="{{ route('seller.register') }}"
                    class="mt-9 inline-flex h-11 items-center gap-2 rounded-full bg-white px-5 text-[12px] font-semibold text-[#173F35] hover:bg-[#DDF3EC]"
                >
                    Start Selling

                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>

            </div>

        </div>


        {{-- IMAGE --}}
        <div class="relative min-h-[500px] overflow-hidden lg:min-h-[680px]">

            <img
                src="https://images.unsplash.com/photo-1472851294608-062f824d29cc?auto=format&fit=crop&w=1500&q=90"
                alt="Seller managing marketplace orders"
                class="absolute inset-0 h-full w-full object-cover"
            >

            <div class="absolute inset-0 bg-gradient-to-r from-[#173F35]/15 to-transparent"></div>

        </div>

    </div>

</section>

{{-- ========================================================= --}}
{{-- RIDER / COURIER EDITORIAL --}}
{{-- ========================================================= --}}

<section
    id="for-riders"
    class="mx-auto max-w-[1500px]
           px-5 py-20
           sm:px-8
           lg:px-10
           lg:py-28"
>

    <div
        class="grid overflow-hidden
               rounded-[30px]
               bg-[#F0F6F3]
               lg:grid-cols-2"
    >

        {{-- IMAGE --}}
        <div
            class="relative min-h-[500px]
                   overflow-hidden
                   lg:min-h-[660px]"
        >

            <img
                src="{{ asset('images/suki-rider.jpg') }}"
                alt="SUKI SHOP rider"
                class="absolute inset-0
                       h-full w-full
                       object-cover"
            >

            <div
                class="absolute inset-0
                       bg-gradient-to-t
                       from-[#071A15]/30
                       via-transparent
                       to-transparent"
            ></div>

        </div>


        {{-- CONTENT --}}
        <div
            class="flex items-center
                   px-7 py-14
                   sm:px-10
                   lg:px-14"
        >

            <div class="max-w-[570px]">

                <p
                    class="text-[10px]
                           font-semibold uppercase
                           tracking-[0.2em]
                           text-[#1F6F5B]"
                >
                    For Riders & Couriers
                </p>


                <h2
                    class="mt-4
                           text-[36px]
                           font-semibold
                           leading-[1.08]
                           tracking-[-0.045em]
                           text-[#173F35]
                           sm:text-[48px]"
                >
                    Keep every order moving forward.
                </h2>


                <p
                    class="mt-6
                           text-[14px]
                           leading-7
                           text-[#173F35]/58"
                >
                    Riders connect sellers, sorting centers,
                    and customers. Pickup riders collect
                    parcels from sellers, while assigned
                    delivery riders complete the final
                    delivery to the buyer.
                </p>


                <div
                    class="mt-9
                           grid grid-cols-2
                           gap-x-7 gap-y-5"
                >

                    @foreach([
                        'View pickup assignments',
                        'Accept seller pickups',
                        'Scan and confirm parcels',
                        'Bring parcels to logistics',
                        'Receive delivery assignments',
                        'Complete final delivery',
                    ] as $item)

                        <div
                            class="border-t
                                   border-[#173F35]/12
                                   pt-3"
                        >

                            <p
                                class="text-[11px]
                                       font-medium
                                       leading-5
                                       text-[#173F35]/65"
                            >
                                {{ $item }}
                            </p>

                        </div>

                    @endforeach

                </div>


                <a
                    href="{{ route('rider.apply') }}"
                    class="mt-9
                           inline-flex h-11
                           items-center gap-2
                           rounded-full
                           bg-[#173F35]
                           px-5
                           text-[12px]
                           font-semibold
                           text-white
                           transition
                           hover:bg-[#1F6F5B]"
                >
                    Apply as Rider

                    <i
                        data-lucide="arrow-right"
                        class="h-4 w-4"
                    ></i>
                </a>

            </div>

        </div>

    </div>

</section>


{{-- ========================================================= --}}
{{-- SIMPLIFIED PUBLIC ORDER JOURNEY --}}
{{-- ========================================================= --}}

<section
    id="how-it-works"
    class="mx-auto max-w-[1500px] px-5 py-20 sm:px-8 lg:px-10 lg:py-28"
>

    <div class="grid gap-7 lg:grid-cols-[0.75fr_1.25fr] lg:items-end">

        <div>

            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#1F6F5B]">
                How SUKI SHOP works
            </p>


            <h2 class="mt-3 max-w-[580px] text-[36px] font-semibold leading-[1.1] tracking-[-0.045em] text-[#173F35] sm:text-[48px]">
                From discovery to doorstep.
            </h2>

        </div>


        <p class="max-w-[620px] text-[13px] leading-7 text-[#173F35]/50 lg:justify-self-end">
            Customers see a simple shopping experience while SUKI SHOP connects sellers, pickup riders, logistics, and delivery riders behind each order.
        </p>

    </div>


    {{-- DESKTOP TIMELINE --}}
    <div class="relative mt-16 hidden md:block">

        <div class="absolute left-[6%] right-[6%] top-5 h-px bg-[#173F35]/13"></div>


        <div class="relative grid grid-cols-7 gap-5">

            @foreach($publicJourney as $step)

                <article>

                    <div class="flex justify-center">

                        <span class="grid h-10 w-10 place-items-center rounded-full border border-[#1F6F5B]/25 bg-[#F8FAF8] text-[10px] font-semibold text-[#1F6F5B]">
                            {{ $step['number'] }}
                        </span>

                    </div>


                    <div class="mt-6 text-center">

                        <h3 class="text-[12px] font-semibold text-[#173F35]">
                            {{ $step['title'] }}
                        </h3>


                        <p class="mx-auto mt-3 max-w-[150px] text-[10px] leading-5 text-[#173F35]/42">
                            {{ $step['description'] }}
                        </p>

                    </div>

                </article>

            @endforeach

        </div>

    </div>


    {{-- MOBILE --}}
    <div class="relative mt-12 md:hidden">

        <div class="absolute bottom-5 left-[18px] top-5 w-px bg-[#173F35]/12"></div>


        @foreach($publicJourney as $step)

            <article class="relative grid grid-cols-[38px_1fr] gap-4 py-4">

                <span class="relative z-10 grid h-9 w-9 place-items-center rounded-full border border-[#1F6F5B]/25 bg-[#F8FAF8] text-[9px] font-semibold text-[#1F6F5B]">
                    {{ $step['number'] }}
                </span>


                <div class="border-b border-[#173F35]/8 pb-5">

                    <h3 class="text-[13px] font-semibold text-[#173F35]">
                        {{ $step['title'] }}
                    </h3>


                    <p class="mt-2 text-[11px] leading-5 text-[#173F35]/45">
                        {{ $step['description'] }}
                    </p>

                </div>

            </article>

        @endforeach

    </div>

</section>


{{-- ========================================================= --}}
{{-- LOGISTICS --}}
{{-- ========================================================= --}}

<section
    id="logistics"
    class="bg-[#E8F4EF]"
>

    <div class="mx-auto grid max-w-[1500px] lg:grid-cols-2">

        {{-- IMAGE --}}
        <div class="relative min-h-[500px] overflow-hidden lg:min-h-[680px]">

            <img
                src="https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1500&q=90"
                alt="SUKI SHOP sorting center"
                class="absolute inset-0 h-full w-full object-cover"
            >

        </div>


        {{-- CONTENT --}}
        <div class="flex items-center px-6 py-16 sm:px-9 lg:px-14">

            <div class="max-w-[590px]">

                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#1F6F5B]">
                    Connected fulfillment
                </p>


                <h2 class="mt-4 text-[36px] font-semibold leading-[1.08] tracking-[-0.045em] text-[#173F35] sm:text-[48px]">
                    Sorting makes delivery more organized.
                </h2>


                <p class="mt-6 text-[14px] leading-7 text-[#173F35]/56">
                    After pickup, parcels move through the sorting center. Logistics identifies the delivery destination, organizes parcels by area, and assigns the appropriate rider.
                </p>


                <div class="mt-8 space-y-0">

                    @foreach([
                        ['01', 'Receive & Scan', 'Incoming parcels arrive at the sorting center.'],
                        ['02', 'Determine Area', 'The delivery address identifies the destination area.'],
                        ['03', 'Sort Parcel', 'Parcels are grouped according to destination.'],
                        ['04', 'Assign Rider', 'The appropriate rider receives the delivery assignment.'],
                    ] as $item)

                        <div class="grid grid-cols-[42px_1fr] gap-4 border-t border-[#173F35]/10 py-4">

                            <span class="text-[10px] font-semibold text-[#1F6F5B]">
                                {{ $item[0] }}
                            </span>


                            <div>

                                <p class="text-[12px] font-semibold text-[#173F35]">
                                    {{ $item[1] }}
                                </p>

                                <p class="mt-1 text-[11px] leading-5 text-[#173F35]/45">
                                    {{ $item[2] }}
                                </p>

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

    </div>

</section>

{{-- ========================================================= --}}
{{-- PLATFORM ADMINISTRATION --}}
{{-- ========================================================= --}}

<section
    id="platform-management"
    class="bg-white"
>

    <div
        class="mx-auto max-w-[1320px]
               px-5 py-20
               sm:px-8
               lg:py-28"
    >

        <div
            class="grid gap-14
                   lg:grid-cols-[0.85fr_1.15fr]
                   lg:gap-20"
        >

            {{-- INTRO --}}
            <div>

                <p
                    class="text-[10px]
                           font-semibold uppercase
                           tracking-[0.2em]
                           text-[#1F6F5B]"
                >
                    Platform Management
                </p>


                <h2
                    class="mt-4
                           text-[36px]
                           font-semibold
                           leading-[1.08]
                           tracking-[-0.045em]
                           text-[#173F35]
                           sm:text-[48px]"
                >
                    Managed behind the marketplace.
                </h2>


                <p
                    class="mt-6
                           text-[14px]
                           leading-7
                           text-[#173F35]/55"
                >
                    SUKI SHOP includes administrative
                    controls that support account review,
                    marketplace compliance, complaints,
                    platform commissions, reporting, and
                    system-wide operations.
                </p>

            </div>


            {{-- ADMIN CAPABILITIES --}}
            <div
                class="grid
                       sm:grid-cols-2"
            >

                @foreach([
                    [
                        '01',
                        'Registration Review',
                        'Review buyer, seller, and logistics applications before account approval.'
                    ],
                    [
                        '02',
                        'Account Management',
                        'Manage user profiles and account status across the marketplace.'
                    ],
                    [
                        '03',
                        'Seller Compliance',
                        'Review marketplace listings and seller compliance with platform policies.'
                    ],
                    [
                        '04',
                        'Complaints & Disputes',
                        'Review concerns involving buyers, sellers, riders, and marketplace transactions.'
                    ],
                    [
                        '05',
                        'Commission Management',
                        'Support platform commission monitoring and transaction reporting.'
                    ],
                    [
                        '06',
                        'Reports & Platform Settings',
                        'Support reporting, announcements, policies, and marketplace operations.'
                    ],
                ] as $admin)

                    <article
                        class="border-t
                               border-[#173F35]/10
                               py-6
                               sm:px-5"
                    >

                        <span
                            class="text-[9px]
                                   font-semibold
                                   tracking-[0.12em]
                                   text-[#1F6F5B]"
                        >
                            {{ $admin[0] }}
                        </span>


                        <h3
                            class="mt-3
                                   text-[13px]
                                   font-semibold
                                   text-[#173F35]"
                        >
                            {{ $admin[1] }}
                        </h3>


                        <p
                            class="mt-2
                                   text-[11px]
                                   leading-6
                                   text-[#173F35]/45"
                        >
                            {{ $admin[2] }}
                        </p>

                    </article>

                @endforeach

            </div>

        </div>

    </div>

</section>

{{-- ========================================================= --}}
{{-- MARKETPLACE TRUST --}}
{{-- ========================================================= --}}

<section class="bg-white py-20 lg:py-24">

    <div class="mx-auto max-w-[1320px] px-5 sm:px-8">

        <div class="mx-auto max-w-[720px] text-center">

            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#1F6F5B]">
                Marketplace standards
            </p>


            <h2 class="mt-3 text-[34px] font-semibold tracking-[-0.04em] text-[#173F35] sm:text-[44px]">
                Designed for a more accountable marketplace.
            </h2>


            <p class="mt-5 text-[13px] leading-7 text-[#173F35]/48">
                SUKI SHOP is structured around account review, seller compliance, rider applications, managed logistics operations, and visible order progress.
            </p>

        </div>


        <div class="mt-14 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">

            @foreach([
                [
                    'Application review',
                    'Marketplace registrations can move through an approval process before platform access.'
                ],
                [
                    'Seller compliance',
                    'Seller activity and listed products can be reviewed against marketplace policies.'
                ],
                [
                    'Rider verification',
                    'Courier applications include vehicle and identification requirements for logistics review.'
                ],
                [
                    'Order visibility',
                    'Orders move through clear fulfillment stages from placement to completion.'
                ],
            ] as $feature)

                <article class="border-t border-[#173F35]/12 pt-5">

                    <h3 class="text-[13px] font-semibold text-[#173F35]">
                        {{ $feature[0] }}
                    </h3>


                    <p class="mt-3 text-[11px] leading-6 text-[#173F35]/45">
                        {{ $feature[1] }}
                    </p>

                </article>

            @endforeach

        </div>

    </div>

</section>


{{-- ========================================================= --}}
{{-- JOIN --}}
{{-- ========================================================= --}}

<section
    id="join"
    class="mx-auto max-w-[1500px] px-5 py-20 sm:px-8 lg:px-10 lg:py-24"
>

    <div class="overflow-hidden rounded-[30px] bg-[#173F35] px-7 py-12 text-white sm:px-10 lg:px-14 lg:py-16">

        <div class="grid items-end gap-10 lg:grid-cols-[1fr_auto]">

            <div class="max-w-[720px]">

                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8FD0BA]">
                    Be part of SUKI SHOP
                </p>


                <h2 class="mt-4 text-[36px] font-semibold leading-[1.1] tracking-[-0.045em] sm:text-[48px]">
                    One marketplace.
                    Different ways to take part.
                </h2>


                <p class="mt-5 max-w-[620px] text-[13px] leading-7 text-white/55">
                    Shop as a buyer, build your business as a seller, or become part of the connected delivery network.
                </p>

            </div>


            <div class="flex flex-wrap gap-3 lg:max-w-[470px] lg:justify-end">

                <a
                    href="{{ route('register') }}"
                    class="inline-flex h-11 items-center rounded-full bg-white px-5 text-[12px] font-semibold text-[#173F35]"
                >
                    Join as Buyer
                </a>


                <a
                    href="{{ route('seller.register') }}"
                    class="inline-flex h-11 items-center rounded-full border border-white/15 bg-white/8 px-5 text-[12px] font-semibold text-white"
                >
                    Start Selling
                </a>


                <a
                    href="{{ route('rider.apply') }}"
                    class="inline-flex h-11 items-center rounded-full border border-white/15 bg-white/8 px-5 text-[12px] font-semibold text-white"
                >
                    Apply as Rider
                </a>

            </div>

        </div>

    </div>

</section>

</main>


{{-- ========================================================= --}}
{{-- FOOTER --}}
{{-- ========================================================= --}}

<footer class="border-t border-[#173F35]/8 bg-white">

    <div class="mx-auto max-w-[1500px] px-5 sm:px-8 lg:px-10">

        <div class="grid gap-10 py-12 md:grid-cols-[1.5fr_1fr_1fr_1fr]">

            <div>

                <div class="flex items-center gap-3">

                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI SHOP"
                        class="h-11 w-11 object-contain"
                    >

                    <div>

                        <p class="text-[16px] font-bold text-[#173F35]">
                            SUKI SHOP
                        </p>

                        <p class="text-[8px] uppercase tracking-[0.15em] text-[#1F6F5B]/60">
                            Connected Marketplace
                        </p>

                    </div>

                </div>


                <p class="mt-5 max-w-[380px] text-[11px] leading-6 text-[#173F35]/42">
                    A marketplace connecting shopping, selling, fulfillment, and delivery through one organized platform.
                </p>

            </div>


            <div>

                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#173F35]">
                    Marketplace
                </p>

                <div class="mt-5 space-y-3">

                    <a href="{{ route('buyer.shop') }}" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        Shop
                    </a>

                    <a href="{{ route('seller.register') }}" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        Become a Seller
                    </a>

                    <a href="{{ route('rider.apply') }}" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        Become a Rider
                    </a>

                </div>

            </div>


            <div>

                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#173F35]">
                    Platform
                </p>

                <div class="mt-5 space-y-3">

                    <a href="#about" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        About
                    </a>

                    <a href="#how-it-works" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        How It Works
                    </a>

                    <a href="#logistics" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        Logistics
                    </a>

                </div>

            </div>


            <div>

                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#173F35]">
                    Account
                </p>

                <div class="mt-5 space-y-3">

                    <a href="{{ route('login') }}" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        Log In
                    </a>

                    <a href="{{ route('register') }}" class="block text-[11px] text-[#173F35]/45 hover:text-[#1F6F5B]">
                        Create Account
                    </a>

                </div>

            </div>

        </div>


        <div class="flex flex-col gap-3 border-t border-[#173F35]/8 py-5 sm:flex-row sm:items-center sm:justify-between">

            <p class="text-[9px] text-[#173F35]/30">
                © {{ date('Y') }} SUKI SHOP. All rights reserved.
            </p>


            <p class="text-[9px] text-[#173F35]/30">
                Shop • Sell • Deliver • Together
            </p>

        </div>

    </div>

</footer>


{{-- ========================================================= --}}
{{-- JAVASCRIPT --}}
{{-- ========================================================= --}}

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ---------------------------------------------------------
    // LUCIDE
    // ---------------------------------------------------------

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }


    // ---------------------------------------------------------
    // MOBILE NAV
    // ---------------------------------------------------------

    const menuButton =
        document.getElementById('landingMenuButton');

    const mobileMenu =
        document.getElementById('landingMobileMenu');


    if (menuButton && mobileMenu) {

        menuButton.addEventListener(
            'click',
            function () {

                mobileMenu.classList.toggle('hidden');

            }
        );

    }


    // ---------------------------------------------------------
    // HERO CAROUSEL
    // ---------------------------------------------------------

    const hero =
        document.getElementById('heroCarousel');


    if (!hero) {
        return;
    }


    const slides =
        Array.from(
            hero.querySelectorAll('.hero-slide')
        );


    const controls =
        Array.from(
            hero.querySelectorAll('[data-hero-target]')
        );


    const thumbs =
        Array.from(
            hero.querySelectorAll('.hero-thumb')
        );


    const dots =
        Array.from(
            hero.querySelectorAll('.hero-dot')
        );


    const previousButton =
        document.getElementById('heroPrev');


    const nextButton =
        document.getElementById('heroNext');


    const pauseButton =
        document.getElementById('heroPause');


    let currentIndex = 0;

    let autoplayTimer = null;

    let isPlaying = true;

    let touchStartX = 0;

    const autoplayDelay = 6000;


    function renderSlide() {

        slides.forEach(
            function (slide, index) {

                slide.classList.toggle(
                    'is-active',
                    index === currentIndex
                );

            }
        );


        thumbs.forEach(
            function (thumb) {

                const target =
                    Number(
                        thumb.dataset.heroTarget
                    );


                thumb.classList.toggle(
                    'is-active',
                    target === currentIndex
                );

            }
        );


        dots.forEach(
            function (dot) {

                const target =
                    Number(
                        dot.dataset.heroTarget
                    );


                if (target === currentIndex) {

                    dot.classList.remove(
                        'w-2',
                        'bg-white/40'
                    );

                    dot.classList.add(
                        'w-7',
                        'bg-white'
                    );

                } else {

                    dot.classList.remove(
                        'w-7',
                        'bg-white'
                    );

                    dot.classList.add(
                        'w-2',
                        'bg-white/40'
                    );

                }

            }
        );

    }


    function goToSlide(index) {

        currentIndex =
            (index + slides.length)
            % slides.length;


        renderSlide();

    }


    function nextSlide() {

        goToSlide(
            currentIndex + 1
        );

    }


    function previousSlide() {

        goToSlide(
            currentIndex - 1
        );

    }


    function stopAutoplay() {

        if (autoplayTimer) {

            clearInterval(
                autoplayTimer
            );

            autoplayTimer = null;

        }

    }


    function startAutoplay() {

        stopAutoplay();


        if (!isPlaying) {
            return;
        }


        autoplayTimer =
            setInterval(
                nextSlide,
                autoplayDelay
            );

    }


    controls.forEach(
        function (control) {

            control.addEventListener(
                'click',
                function () {

                    goToSlide(
                        Number(
                            control.dataset.heroTarget
                        )
                    );


                    startAutoplay();

                }
            );

        }
    );


    if (nextButton) {

        nextButton.addEventListener(
            'click',
            function () {

                nextSlide();

                startAutoplay();

            }
        );

    }


    if (previousButton) {

        previousButton.addEventListener(
            'click',
            function () {

                previousSlide();

                startAutoplay();

            }
        );

    }


    if (pauseButton) {

        pauseButton.addEventListener(
            'click',
            function () {

                isPlaying = !isPlaying;


                pauseButton.innerHTML = `
                    <i
                        data-lucide="${isPlaying ? 'pause' : 'play'}"
                        class="h-4 w-4"
                    ></i>
                `;


                if (
                    typeof lucide !== 'undefined'
                ) {
                    lucide.createIcons();
                }


                if (isPlaying) {

                    startAutoplay();

                } else {

                    stopAutoplay();

                }

            }
        );

    }


    hero.addEventListener(
        'touchstart',
        function (event) {

            touchStartX =
                event.changedTouches[0]
                    .clientX;

        },
        {
            passive: true
        }
    );


    hero.addEventListener(
        'touchend',
        function (event) {

            const touchEndX =
                event.changedTouches[0]
                    .clientX;


            const distance =
                touchEndX - touchStartX;


            if (
                Math.abs(distance) < 50
            ) {
                return;
            }


            if (distance < 0) {

                nextSlide();

            } else {

                previousSlide();

            }


            startAutoplay();

        },
        {
            passive: true
        }
    );


    document.addEventListener(
        'visibilitychange',
        function () {

            if (document.hidden) {

                stopAutoplay();

            } else {

                startAutoplay();

            }

        }
    );


    renderSlide();

    startAutoplay();

});
</script>


</body>
</html>