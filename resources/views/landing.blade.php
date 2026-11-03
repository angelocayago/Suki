<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SUKI SHOP</title>

    <link rel="icon" href="{{ asset('images/suki-logo.png') }}">

    <script defer src="https://unpkg.com/lucide@latest"></script>

    @vite([
        'resources/css/landing.css',
        'resources/js/landing.js'
    ])

</head>


<body
    data-login-url="{{ route('login') }}"
    data-authenticated="{{ auth()->check() ? '1' : '0' }}"
>


@php

    /*
    |--------------------------------------------------------------------------
    | LANDING PAGE DISPLAY CONTENT
    |--------------------------------------------------------------------------
    | Landing-page preview content only.
    | Buyer/Seller/Admin backend logic is untouched.
    |--------------------------------------------------------------------------
    */

    $landingCategories = [

        [
            'name' => 'Pet Supplies',
            'image' => 'category-pet.png',
        ],

        [
            'name' => 'Electronics & Gadgets',
            'image' => 'category-electronics.png',
        ],

        [
            'name' => "Women's Apparel",
            'image' => 'category-women.png',
        ],

        [
            'name' => "Men's Apparel",
            'image' => 'category-men.png',
        ],

        [
            'name' => 'Kids & Baby',
            'image' => 'category-kid.png',
        ],

        [
            'name' => 'Home & Garden',
            'image' => 'category-home.png',
        ],

        [
            'name' => 'Sports & Outdoors',
            'image' => 'category-sports.png',
        ],

        [
            'name' => 'Health & Beauty',
            'image' => 'category-beauty.png',
        ],

        [
            'name' => 'Books & Media',
            'image' => 'category-books.png',
        ],

        [
            'name' => 'Food & Gourmet',
            'image' => 'category-food.png',
        ],

        [
            'name' => 'Automotive & Motorcycle',
            'image' => 'category-automotive.png',
        ],

        [
            'name' => 'Furniture & Office',
            'image' => 'category-office.png',
        ],

        [
            'name' => 'Jewelry & Watches',
            'image' => 'category-jewelry.png',
        ],

        [
            'name' => 'Office & School Supplies',
            'image' => 'category-school.png',
        ],

    ];


    $heroSlides = [
        [
            'image' => asset('images/landing/hero/hero-main.png'),
            'eyebrow' => 'YOUR EVERYDAY SUKI, ONLINE.',
            'heading' => 'Everything you love, all in one place.',
            'description' => 'Discover quality products for your everyday lifestyle.',
        ],
        [
            'image' => asset('images/landing/hero/hero-thumb-1.png'),
            'eyebrow' => 'FRESH FINDS FOR EVERYDAY LIVING.',
            'heading' => 'Find something worth coming back for.',
            'description' => 'Explore useful finds from sellers across the SUKI marketplace.',
        ],
        [
            'image' => asset('images/landing/hero/hero-thumb-2.png'),
            'eyebrow' => 'SHOP LOCAL. SHOP WITH CONFIDENCE.',
            'heading' => 'Your next favorite find starts here.',
            'description' => 'Browse everyday essentials, new arrivals, and trusted marketplace finds.',
        ],
    ];

@endphp

<main class="landing-page">


    {{-- =====================================================
        HERO
    ====================================================== --}}

    <section class="hero-section" id="home">


        <div class="hero-background">

            <img
                id="heroImage"
                src="{{ asset('images/landing/hero/hero-main.png') }}"
                alt="SUKI lifestyle collection"
            >

        </div>


        <div class="hero-shade"></div>



        {{-- =================================================
            SUKI LANDING NAVBAR
        ================================================== --}}

        <header class="landing-header">
            <div class="landing-container">
                <div class="navbar">

                    <a href="{{ url('/') }}" class="brand-logo" aria-label="SUKI Home">
                        <img src="{{ asset('images/suki-logo.png') }}" alt="SUKI">
                        <span>SUKI SHOP</span>
                    </a>

                    <nav class="primary-nav" aria-label="Main navigation">
                        <a href="#home" class="active">Home</a>
                        <a href="#categories">Categories</a>
                        <a href="{{ route('buyer.shop') }}">Stores</a>
                        <a href="{{ route('buyer.shop') }}">Deals</a>
                    </nav>

                    <form class="hero-search" action="{{ route('buyer.shop') }}" method="GET">
                        <i data-lucide="search"></i>
                        <input
                            type="search"
                            name="search"
                            placeholder="Search for products, stores, or categories..."
                            aria-label="Search products"
                        >
                        <button type="submit" class="hero-search-submit" aria-label="Search">
                            <i data-lucide="arrow-right"></i>
                        </button>
                    </form>

                    <div class="nav-actions">

                        <button
                            type="button"
                            class="theme-toggle"
                            id="landingThemeToggle"
                            aria-label="Toggle dark mode"
                            aria-pressed="false"
                        >
                            <i data-lucide="moon"></i>
                        </button>

                        @auth
                            @php
                                $landingAccountUrl = auth()->user()->role === 'buyer'
                                    ? route('buyer.home')
                                    : route('login');
                            @endphp

                            <a
                                href="{{ $landingAccountUrl }}"
                                class="account-link"
                                aria-label="Account"
                            >
                                <i data-lucide="user"></i>
                            </a>

                            <form
                                method="POST"
                                action="{{ route('login.switch') }}"
                                class="landing-login-switch"
                            >
                                @csrf
                                <button type="submit" class="auth-cta">
                                    Switch Account
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="auth-cta">
                                Sign In
                            </a>
                        @endauth

                        <a href="{{ route('register') }}" class="shop-now-cta">
                            Register
                        </a>

                    </div>

                </div>
            </div>
        </header>


        {{-- =================================================
            HERO CONTENT
        ================================================== --}}

        <div class="landing-container hero-inner">

            <div class="hero-content">

                <p class="hero-small" id="heroEyebrow">
                    {{ $heroSlides[0]['eyebrow'] }}
                </p>

                <h1>
                    SUKI <span>SHOP</span>
                </h1>

                <h2 id="heroHeading">
                    {{ $heroSlides[0]['heading'] }}
                </h2>

                <p class="hero-description" id="heroDescription">
                    {{ $heroSlides[0]['description'] }}
                </p>

                <div class="hero-actions">
                    <a href="{{ route('buyer.shop') }}" class="hero-btn">
                        Shop Now
                        <i data-lucide="arrow-right"></i>
                    </a>

                    <a href="#categories" class="hero-secondary-btn">
                        Explore Categories
                    </a>
                </div>

                <div class="hero-features">

                    <div>
                        <i data-lucide="leaf"></i>
                        <span>Curated Essentials</span>
                    </div>

                    <div>
                        <i data-lucide="truck"></i>
                        <span>Fast Delivery</span>
                    </div>

                    <div>
                        <i data-lucide="shield-check"></i>
                        <span>Trusted Quality</span>
                    </div>

                </div>

            </div>

        </div>


        <div class="hero-carousel-controls">

            <div class="carousel-dots">
                @foreach($heroSlides as $index => $slide)
                    <button
                        type="button"
                        class="hero-dot {{ $index === 0 ? 'active' : '' }}"
                        data-image="{{ $slide['image'] }}"
                        data-eyebrow="{{ $slide['eyebrow'] }}"
                        data-heading="{{ $slide['heading'] }}"
                        data-description="{{ $slide['description'] }}"
                        aria-label="Hero slide {{ $index + 1 }}"
                        aria-current="{{ $index === 0 ? 'true' : 'false' }}"
                    ></button>
                @endforeach
            </div>

            <span class="hero-counter">
                <strong id="heroCurrent">01</strong>
                <span>/</span>
                <span>{{ str_pad((string) count($heroSlides), 2, '0', STR_PAD_LEFT) }}</span>
            </span>

        </div>

        <div class="hero-carousel-actions">
            <button
                type="button"
                class="hero-arrow hero-prev"
                aria-label="Previous slide"
            >
                <i data-lucide="chevron-left"></i>
            </button>

            <button
                type="button"
                class="hero-arrow hero-next"
                aria-label="Next slide"
            >
                <i data-lucide="chevron-right"></i>
            </button>

            <button
                type="button"
                class="hero-toggle"
                id="heroToggle"
                aria-label="Pause carousel"
                aria-pressed="false"
            >
                <i data-lucide="pause"></i>
            </button>
        </div>

    </section>



    <section class="trust-strip" aria-label="Why shop with SUKI">
        <div class="landing-container trust-strip-grid">
            <article class="trust-item">
                <span class="trust-icon"><i data-lucide="shield-check"></i></span>
                <div><h3>Secure Checkout</h3><p>Shop with confidence</p></div>
            </article>
            <article class="trust-item">
                <span class="trust-icon"><i data-lucide="badge-check"></i></span>
                <div><h3>Trusted Sellers</h3><p>Verified and reliable</p></div>
            </article>
            <article class="trust-item">
                <span class="trust-icon"><i data-lucide="package"></i></span>
                <div><h3>Wide Variety</h3><p>Everything you need</p></div>
            </article>
            <article class="trust-item">
                <span class="trust-icon"><i data-lucide="truck"></i></span>
                <div><h3>Fast &amp; Reliable Delivery</h3><p>Bringing your orders safely to your doorstep</p></div>
            </article>
        </div>
    </section>


    {{-- =====================================================
        CATEGORY SLIDER
    ====================================================== --}}

    <section
        class="category-section"
        id="categories"
    >


        <div class="landing-container">


            <div class="section-header">


                <div>


                    <h2>
                        Browse Categories
                    </h2>


                    <p>
                        Find exactly what you need for every part of your life.
                    </p>


                </div>



                <div class="category-controls">
                    <a href="{{ route('buyer.shop') }}" class="section-link">
                        View All Categories
                        <i data-lucide="arrow-right"></i>
                    </a>


                </div>


            </div>



            <div class="category-slider-wrapper">


                <div class="category-slider">


                    @foreach($landingCategories as $category)


                        <a
                            href="{{ route('buyer.shop') }}"
                            class="category-item"
                        >


                            <div class="category-image">


                                <img
                                    src="{{ asset('images/landing/categories/' . $category['image']) }}"
                                    alt="{{ $category['name'] }}"
                                    draggable="false"
                                >


                            </div>


                            <span>
                                {{ $category['name'] }}
                            </span>


                        </a>


                    @endforeach


                </div>


            </div>



            <div
                class="category-progress"
                aria-hidden="true"
            >

                <div class="category-progress-bar"></div>

            </div>


        </div>


    </section>



    {{-- =====================================================
        FEATURED PRODUCTS
    ====================================================== --}}

    <section
        class="products-section"
        id="featured"
    >


        <div class="landing-container">


            <div class="section-header">


                <div>


                    <h2>
                        Featured Products
                    </h2>


                    <p>
                        Discover products chosen for your everyday needs.
                    </p>


                </div>



                <div class="product-section-actions">
                    <a href="{{ route('buyer.shop') }}" class="section-link">
                        View All Products
                        <i data-lucide="arrow-right"></i>
                    </a>

                </div>


            </div>



            <div class="product-grid">


                @forelse($landingProducts as $product)

                    <article class="product-card">

                        <a
                            href="{{ route('buyer.product', ['slug' => $product['slug']]) }}"
                            class="product-image"
                            aria-label="View {{ $product['name'] }}"
                        >
                            <img
                                src="{{ $product['image'] }}"
                                alt="{{ $product['name'] }}"
                                loading="lazy"
                            >
                        </a>

                        <div class="product-info">

                            <p class="product-seller">
                                <i data-lucide="store"></i>
                                {{ $product['seller'] ?? 'SUKI Seller' }}
                            </p>

                            <h3>
                                <a href="{{ route('buyer.product', ['slug' => $product['slug']]) }}">
                                    {{ $product['name'] }}
                                </a>
                            </h3>

                            <div class="rating">
                                <span class="rating-stars">★</span>
                                <span>
                                    {{ number_format((float) ($product['rating'] ?? 0), 1) }}
                                    @if(($product['sold'] ?? 0) > 0)
                                        · {{ number_format((int) $product['sold']) }} sold
                                    @endif
                                </span>
                            </div>

                            <div class="product-bottom">
                                <strong>
                                    ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                                </strong>
                                <a href="{{ route('buyer.product', ['slug' => $product['slug']]) }}" class="product-details-link">
                                    View Details <i data-lucide="arrow-right"></i>
                                </a>
                            </div>

                        </div>

                    </article>

                @empty

                    <div class="products-empty">
                        Products will appear here when approved marketplace items are available.
                    </div>

                @endforelse


            </div>


        </div>


    </section>



    {{-- =====================================================
        CTA
    ====================================================== --}}

    <section class="cta-section">


        <div class="cta-background">


            <img
                src="{{ asset('images/landing/cta-banner.png') }}"
                alt="SUKI everyday shopping"
            >


        </div>


        <div class="cta-shade"></div>


        <div class="landing-container cta-inner">


            <div class="cta-content">


                <p class="cta-label">
                    EVERYDAY ESSENTIALS, GREATER POSSIBILITIES
                </p>


                <h2>
                    Everyday finds.
                    <br>
                    Better shopping.
                </h2>


                <p>
                    Quality products from trusted sellers, all in one place.
                </p>


                <a
                    href="{{ route('buyer.shop') }}"
                    class="cta-btn"
                >

                    Shop the Deals

                    <i data-lucide="arrow-right"></i>

                </a>


            </div>


        </div>


    </section>



    {{-- =====================================================
        BETTER WAY TO SHOP
    ====================================================== --}}

    <section class="shopping-steps-section">

        <div class="landing-container">

            <p class="section-label">
                WHY CHOOSE SUKI?
            </p>

            <h2>
                A better way to shop on SUKI
            </h2>

            <div class="shopping-steps-grid">

                <article class="shopping-step">
                    <div class="shopping-step-icon">
                        <i data-lucide="search"></i>
                    </div>
                    <h3>Discover</h3>
                    <p>
                        Browse a wide variety of products from trusted sellers.
                    </p>
                </article>

                <article class="shopping-step">
                    <div class="shopping-step-icon">
                        <i data-lucide="shopping-cart"></i>
                    </div>
                    <h3>Shop</h3>
                    <p>
                        Find what you need at great prices.
                    </p>
                </article>

                <article class="shopping-step">
                    <div class="shopping-step-icon">
                        <i data-lucide="shield-check"></i>
                    </div>
                    <h3>Check Out</h3>
                    <p>
                        Enjoy a secure and hassle-free checkout experience.
                    </p>
                </article>

                <article class="shopping-step">
                    <div class="shopping-step-icon">
                        <i data-lucide="package-check"></i>
                    </div>
                    <h3>Receive</h3>
                    <p>
                        Get your items delivered and start enjoying them.
                    </p>
                </article>

            </div>

        </div>

    </section>


    {{-- =====================================================
        SUKI COMMUNITY
    ====================================================== --}}

    <section class="community-section">

        <div class="landing-container community-inner">

            <div class="community-copy">
                <div>
                    <h2>Be Part of the SUKI Community</h2>
                    <p>Get exclusive deals, new arrivals, and marketplace updates.</p>
                </div>

            </div>

            <form
                class="community-form"
                action="{{ route('buyer.shop') }}"
                method="GET"
            >
                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email address"
                    aria-label="Email address"
                >
                <button type="submit">
                    Subscribe
                </button>
            </form>

        </div>

    </section>


</main>



{{-- =========================================================
    COMPACT FOOTER
========================================================= --}}

<footer class="landing-footer">

    <div class="landing-container">

        <div class="footer-grid">

            <div class="footer-brand">
                <div class="footer-logo-line">
                    <strong>SUKI SHOP</strong>
                </div>
                <p>Your trusted online marketplace.</p>
            </div>

            <div>
                <h3>Shop</h3>
                <a href="#categories">All Categories</a>
                <a href="{{ route('buyer.shop') }}">Deals</a>
                <a href="{{ route('buyer.shop') }}">New Arrivals</a>
                <a href="{{ route('buyer.shop') }}">Best Sellers</a>
            </div>

            <div>
                <h3>Help</h3>
                <a href="{{ route('buyer.shop') }}">Shipping & Delivery</a>
                <a href="{{ route('policies.show', 'terms-of-service') }}">Returns & Refunds</a>
                <a href="{{ route('policies.show', 'privacy-policy') }}">Privacy</a>
            </div>

            <div>
                <h3>Sell on SUKI</h3>
                <a href="{{ route('register') }}">Become a Seller</a>
                <a href="{{ route('login') }}">Seller Centre</a>
            </div>

            <div class="footer-news">
                <h3>Stay in the loop</h3>
                <p>Get the latest deals and updates.</p>
            </div>

        </div>

        <div class="footer-bottom">

            <span>
                © {{ date('Y') }} SUKI SHOP. All rights reserved.
            </span>

            <div>
                <a href="{{ route('policies.show', 'terms-of-service') }}">
                    Terms of Service
                </a>

                <a href="{{ route('policies.show', 'privacy-policy') }}">
                    Privacy Policy
                </a>
            </div>

        </div>

    </div>

</footer>


</body>

</html>
