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


<body>


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


    $landingProducts = [

        [
            'name' => 'Wireless Earbuds',
            'image' => 'product-earbuds.png',
            'price' => '₱1,299',
        ],

        [
            'name' => 'Minimalist Backpack',
            'image' => 'product-backpack.png',
            'price' => '₱1,899',
        ],

        [
            'name' => 'Insulated Tumbler',
            'image' => 'product-tumbler.png',
            'price' => '₱799',
        ],

        [
            'name' => 'Casual Sneakers',
            'image' => 'product-sneakers.png',
            'price' => '₱2,499',
        ],

        [
            'name' => 'Indoor Plant',
            'image' => 'product-plant.png',
            'price' => '₱599',
        ],

        [
            'name' => 'Mechanical Keyboard',
            'image' => 'product-keyboard.png',
            'price' => '₱3,299',
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
            TRANSPARENT LANDING NAVBAR
        ================================================== --}}

        <header class="landing-header">


            <div class="landing-container">


                <div class="navbar">


                    <a
                        href="{{ url('/') }}"
                        class="brand-logo"
                        aria-label="SUKI Home"
                    >

                        <img
                            src="{{ asset('images/suki-logo.png') }}"
                            alt="SUKI"
                        >

                    </a>



                    <form
                        class="hero-search"
                        action="{{ route('buyer.shop') }}"
                        method="GET"
                    >

                        <i data-lucide="search"></i>

                        <input
                            type="search"
                            name="search"
                            placeholder="Search for products, brands and more..."
                            aria-label="Search products"
                        >

                    </form>



                    <div class="nav-actions">


                        <a
                            href="{{ route('login') }}"
                            class="login-link"
                        >
                            Log In
                        </a>


                        <a
                            href="{{ route('register') }}"
                            class="register-btn"
                        >
                            Register
                        </a>


                    </div>


                </div>



                <nav
                    class="secondary-nav"
                    aria-label="Landing navigation"
                >


                    <a href="#categories">

                        <i data-lucide="menu"></i>

                        All Categories

                    </a>


                    <a href="{{ route('buyer.shop') }}">
                        New Arrivals
                    </a>


                    <a href="{{ route('buyer.shop') }}">
                        Deals
                    </a>


                    <a href="{{ route('buyer.shop') }}">
                        Best Sellers
                    </a>


                    <a href="{{ route('buyer.shop') }}">
                        Gift Ideas
                    </a>


                </nav>


            </div>


        </header>



        {{-- =================================================
            HERO CONTENT
        ================================================== --}}

        <div class="landing-container hero-inner">


            <div class="hero-content">


                <p class="hero-small">
                    YOUR EVERYDAY SUKI, ONLINE.
                </p>


                <h1>
                    SUKI <span>SHOP</span>
                </h1>


                <h2>
                    Everything you love,
                    <br>
                    all in one place.
                </h2>


                <p class="hero-description">
                    Discover quality products for your everyday lifestyle.
                </p>


                <a
                    href="{{ route('buyer.shop') }}"
                    class="hero-btn"
                >

                    Shop Now

                    <i data-lucide="arrow-right"></i>

                </a>



                <div class="hero-features">


                    <div>

                        <i data-lucide="leaf"></i>

                        <span>
                            Curated Essentials
                        </span>

                    </div>


                    <div>

                        <i data-lucide="truck"></i>

                        <span>
                            Fast Delivery
                        </span>

                    </div>


                    <div>

                        <i data-lucide="shield-check"></i>

                        <span>
                            Trusted Quality
                        </span>

                    </div>


                </div>


            </div>


        </div>



        {{-- =================================================
            HERO DOTS
        ================================================== --}}

        <div class="carousel-dots">


            <button
                type="button"
                class="hero-dot active"
                data-image="{{ asset('images/landing/hero/hero-main.png') }}"
                aria-label="Hero slide 1"
                aria-current="true"
            ></button>


            <button
                type="button"
                class="hero-dot"
                data-image="{{ asset('images/landing/hero/hero-thumb-1.png') }}"
                aria-label="Hero slide 2"
                aria-current="false"
            ></button>


            <button
                type="button"
                class="hero-dot"
                data-image="{{ asset('images/landing/hero/hero-thumb-2.png') }}"
                aria-label="Hero slide 3"
                aria-current="false"
            ></button>


        </div>


    </section>



    {{-- =====================================================
        EDITORIAL COLLECTIONS
    ====================================================== --}}

    <section class="editorial-section">


        <div class="landing-container">


            <div class="editorial-heading">


                <p class="section-label">
                    MORE THAN JUST PRODUCTS
                </p>


                <h2>
                    Everyday living, made better.
                </h2>


                <p>
                    Explore essentials, smart finds, and everyday favorites
                    made for the way you live.
                </p>


            </div>



            <div class="editorial-grid">


                <a
                    href="{{ route('buyer.shop') }}"
                    class="editorial-item"
                >


                    <img
                        src="{{ asset('images/landing/shortcuts/shortcut-category.png') }}"
                        alt="Home Essentials"
                    >


                    <div class="editorial-overlay"></div>


                    <div class="editorial-content">


                        <span>
                            FOR A BETTER HOME
                        </span>


                        <h3>
                            Home Essentials
                        </h3>


                        <p>
                            Create a space you love.
                        </p>


                        <strong>

                            Explore

                            <i data-lucide="arrow-right"></i>

                        </strong>


                    </div>


                </a>



                <a
                    href="{{ route('buyer.shop') }}"
                    class="editorial-item"
                >


                    <img
                        src="{{ asset('images/landing/shortcuts/shortcut-essentials.png') }}"
                        alt="Everyday Tech"
                    >


                    <div class="editorial-overlay"></div>


                    <div class="editorial-content">


                        <span>
                            SMARTER LIVING
                        </span>


                        <h3>
                            Everyday Tech
                        </h3>


                        <p>
                            Smart tools for daily life.
                        </p>


                        <strong>

                            Explore

                            <i data-lucide="arrow-right"></i>

                        </strong>


                    </div>


                </a>


            </div>


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


                    <p class="section-label">
                        SHOP BY CATEGORY
                    </p>


                    <h2>
                        Browse Categories
                    </h2>


                    <p>
                        Find exactly what you need for every part of your life.
                    </p>


                </div>



                <div class="category-controls">


                    <button
                        type="button"
                        class="category-arrow category-prev"
                        aria-label="Previous categories"
                    >

                        <i data-lucide="chevron-left"></i>

                    </button>


                    <button
                        type="button"
                        class="category-arrow category-next"
                        aria-label="Next categories"
                    >

                        <i data-lucide="chevron-right"></i>

                    </button>


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


                    <p class="section-label">
                        FEATURED PRODUCTS
                    </p>


                    <h2>
                        Featured Products
                    </h2>


                    <p>
                        Discover products chosen for your everyday needs.
                    </p>


                </div>



                <a
                    href="{{ route('buyer.shop') }}"
                    class="section-link"
                >

                    View Shop

                    <i data-lucide="arrow-right"></i>

                </a>


            </div>



            <div class="product-grid">


                @foreach($landingProducts as $product)


                    <article class="product-card">


                        <button
                            type="button"
                            class="wishlist-btn"
                            aria-label="Save {{ $product['name'] }}"
                            aria-pressed="false"
                        >

                            <i data-lucide="heart"></i>

                        </button>



                        <a
                            href="{{ route('buyer.shop') }}"
                            class="product-image"
                            aria-label="View {{ $product['name'] }}"
                        >


                            <img
                                src="{{ asset('images/landing/products/' . $product['image']) }}"
                                alt="{{ $product['name'] }}"
                            >


                        </a>



                        <div class="product-info">


                            <h3>
                                {{ $product['name'] }}
                            </h3>


                            <div class="rating">


                                <span
                                    class="rating-stars"
                                    aria-hidden="true"
                                >
                                    ★★★★★
                                </span>


                                <span>
                                    New
                                </span>


                            </div>



                            <div class="product-bottom">


                                <strong>
                                    {{ $product['price'] }}
                                </strong>


                                <a
                                    href="{{ route('buyer.shop') }}"
                                    class="cart-btn"
                                >

                                    <i data-lucide="shopping-bag"></i>

                                    View Product

                                </a>


                            </div>


                        </div>


                    </article>


                @endforeach


            </div>


        </div>


    </section>



    {{-- =====================================================
        BENEFITS
    ====================================================== --}}

    <section class="benefits-section">


        <div class="landing-container">


            <div class="benefits-grid">


                <div class="benefit-item">


                    <div class="benefit-icon">

                        <i data-lucide="truck"></i>

                    </div>


                    <div>


                        <h3>
                            Fast & Reliable Delivery
                        </h3>


                        <p>
                            Get your orders delivered with care.
                        </p>


                    </div>


                </div>



                <div class="benefit-item">


                    <div class="benefit-icon">

                        <i data-lucide="shield-check"></i>

                    </div>


                    <div>


                        <h3>
                            Secure Payments
                        </h3>


                        <p>
                            Shop with confidence and peace of mind.
                        </p>


                    </div>


                </div>



                <div class="benefit-item">


                    <div class="benefit-icon">

                        <i data-lucide="badge-check"></i>

                    </div>


                    <div>


                        <h3>
                            Trusted Marketplace
                        </h3>


                        <p>
                            Quality products from verified sellers.
                        </p>


                    </div>


                </div>



                <div class="benefit-item">


                    <div class="benefit-icon">

                        <i data-lucide="headphones"></i>

                    </div>


                    <div>


                        <h3>
                            Dedicated Support
                        </h3>


                        <p>
                            We're here to help every step of the way.
                        </p>


                    </div>


                </div>


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
                    GOOD PRODUCTS. BRIGHTER DAYS.
                </p>


                <h2>
                    More Everyday Goodness
                    <br>
                    at SUKI SHOP
                </h2>


                <p>
                    Discover products that make life simpler,
                    better, and brighter.
                </p>


                <a
                    href="{{ route('buyer.shop') }}"
                    class="cta-btn"
                >

                    Explore Now

                    <i data-lucide="arrow-right"></i>

                </a>


            </div>


        </div>


    </section>


</main>



{{-- =========================================================
    COMPACT FOOTER
========================================================= --}}

<footer class="landing-footer">


    <div class="landing-container">


        <div class="footer-row">


            <div class="footer-brand">


                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI"
                >


                <p>
                    Quality products. Brighter everyday living.
                </p>


            </div>



            <nav
                class="footer-links"
                aria-label="Footer navigation"
            >


                <a href="{{ route('buyer.shop') }}">
                    Shop
                </a>


                <a href="#categories">
                    Categories
                </a>


                <a href="#featured">
                    Products
                </a>


                <a href="{{ route('login') }}">
                    Account
                </a>


            </nav>



            <div class="footer-legal">


                <span>
                    © {{ date('Y') }} SUKI SHOP.
                </span>


                <span>
                    Privacy Policy
                </span>


                <span>
                    Terms of Service
                </span>


            </div>


        </div>


    </div>


</footer>


</body>

</html>