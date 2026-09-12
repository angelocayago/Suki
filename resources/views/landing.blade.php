<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SUKI SHOP is a friendly online marketplace for everyday finds, trusted sellers, and simple shopping.">

    <title>SUKI SHOP — Find Your Next Favorite</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Poppins', sans-serif; }
        ::selection { background: #DDF3EC; color: #173F35; }
    </style>
</head>
<body class="bg-[#F8FAF8] text-[#173F35] antialiased">

    {{-- =========================================================
         LANDING NAVIGATION
    ========================================================== --}}
    <header class="fixed inset-x-0 top-0 z-50 border-b border-[#173F35]/8 bg-[#F8FAF8]/95 backdrop-blur-xl">
        <div class="mx-auto flex h-[74px] max-w-[1440px] items-center justify-between px-5 sm:px-8 lg:px-12">
            <a href="{{ route('landing') }}" class="flex items-center gap-3" aria-label="SUKI SHOP home">
                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI SHOP"
                    class="h-11 w-11 object-contain"
                >
                <div class="leading-none">
                    <span class="block text-[18px] font-bold tracking-[-0.04em] text-[#173F35]">SUKI SHOP</span>
                    <span class="mt-1 block text-[9px] font-medium uppercase tracking-[0.22em] text-[#1F6F5B]/65">Marketplace</span>
                </div>
            </a>

            <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary navigation">
                <a href="#discover" class="text-[13px] font-medium text-[#173F35]/65 transition hover:text-[#1F6F5B]">Discover</a>
                <a href="#categories" class="text-[13px] font-medium text-[#173F35]/65 transition hover:text-[#1F6F5B]">Categories</a>
                <a href="#why-suki" class="text-[13px] font-medium text-[#173F35]/65 transition hover:text-[#1F6F5B]">Why SUKI SHOP</a>
                <a href="#sellers" class="text-[13px] font-medium text-[#173F35]/65 transition hover:text-[#1F6F5B]">For Sellers</a>
            </nav>

            <div class="hidden items-center gap-3 sm:flex">
                @if(session('buyer_logged_in'))
                    <a
                        href="{{ route('buyer.home') }}"
                        class="inline-flex h-10 items-center gap-2 rounded-full border border-[#173F35]/12 bg-white px-4 text-[13px] font-semibold text-[#173F35] transition hover:border-[#1F6F5B]/35 hover:text-[#1F6F5B]"
                    >
                        Marketplace
                        <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="px-3 py-2 text-[13px] font-semibold text-[#173F35]/70 transition hover:text-[#1F6F5B]"
                    >
                        Log in
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex h-10 items-center rounded-full bg-[#173F35] px-5 text-[13px] font-semibold text-white transition hover:bg-[#1F6F5B]"
                    >
                        Create account
                    </a>
                @endif
            </div>

            <button
                id="landingMenuButton"
                type="button"
                class="grid h-10 w-10 place-items-center rounded-full border border-[#173F35]/10 bg-white text-[#173F35] sm:hidden"
                aria-label="Open navigation"
                aria-expanded="false"
            >
                <i data-lucide="menu" class="h-4.5 w-4.5"></i>
            </button>
        </div>

        <div id="landingMobileMenu" class="hidden border-t border-[#173F35]/8 bg-[#F8FAF8] px-5 py-5 sm:hidden">
            <div class="flex flex-col gap-1">
                <a href="#discover" class="rounded-xl px-3 py-3 text-sm font-medium hover:bg-white">Discover</a>
                <a href="#categories" class="rounded-xl px-3 py-3 text-sm font-medium hover:bg-white">Categories</a>
                <a href="#why-suki" class="rounded-xl px-3 py-3 text-sm font-medium hover:bg-white">Why SUKI SHOP</a>
                <a href="#sellers" class="rounded-xl px-3 py-3 text-sm font-medium hover:bg-white">For Sellers</a>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 border-t border-[#173F35]/8 pt-4">
                <a href="{{ route('login') }}" class="grid h-11 place-items-center rounded-xl border border-[#173F35]/10 bg-white text-sm font-semibold">Log in</a>
                <a href="{{ route('buyer.home') }}" class="grid h-11 place-items-center rounded-xl bg-[#173F35] text-sm font-semibold text-white">Shop SUKI SHOP</a>
            </div>
        </div>
    </header>

    <main>
        {{-- =====================================================
             HERO
        ====================================================== --}}
        <section id="discover" class="relative overflow-hidden pt-[74px]">
            <div class="absolute inset-x-0 top-[74px] h-px bg-gradient-to-r from-transparent via-[#1F6F5B]/15 to-transparent"></div>

            <div class="mx-auto grid min-h-[760px] max-w-[1440px] items-center gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[0.88fr_1.12fr] lg:px-12 lg:py-20 xl:min-h-[820px]">
                <div class="relative z-10 max-w-[640px]">
                    <div class="mb-7 inline-flex items-center gap-2.5 rounded-full border border-[#1F6F5B]/15 bg-white px-3.5 py-2 shadow-[0_6px_24px_rgba(23,63,53,0.05)]">
                        <span class="h-2 w-2 rounded-full bg-[#F59E0B]"></span>
                        <span class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#173F35]/65">Everyday finds, made easier</span>
                    </div>

                    <h1 class="max-w-[620px] text-[48px] font-semibold leading-[1.02] tracking-[-0.055em] text-[#173F35] sm:text-[62px] lg:text-[70px] xl:text-[78px]">
                        Find your next
                        <span class="relative inline-block text-[#1F6F5B]">
                            SUKI SHOP.
                            <svg class="absolute -bottom-1 left-0 w-full" viewBox="0 0 244 14" fill="none" aria-hidden="true">
                                <path d="M3 10C58 2 135 2 241 7" stroke="#F59E0B" stroke-width="5" stroke-linecap="round" opacity=".8"/>
                            </svg>
                        </span>
                    </h1>

                    <p class="mt-8 max-w-[570px] text-[15px] leading-7 text-[#173F35]/62 sm:text-[16px]">
                        From useful everyday essentials to things you simply want to keep — SUKI SHOP brings different products and sellers together in one marketplace that feels easy to browse and easy to trust.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <a
                            href="{{ route('buyer.home') }}"
                            class="group inline-flex h-13 items-center gap-3 rounded-full bg-[#173F35] px-6 text-[14px] font-semibold text-white shadow-[0_12px_32px_rgba(23,63,53,0.16)] transition hover:-translate-y-0.5 hover:bg-[#1F6F5B]"
                        >
                            Discover SUKI SHOP
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-white/12 transition group-hover:translate-x-0.5">
                                <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                            </span>
                        </a>

                        <a
                            href="#categories"
                            class="inline-flex h-13 items-center gap-2 rounded-full px-5 text-[14px] font-semibold text-[#173F35]/68 transition hover:bg-white hover:text-[#1F6F5B]"
                        >
                            Browse categories
                            <i data-lucide="move-down" class="h-4 w-4"></i>
                        </a>
                    </div>

                    <div class="mt-12 flex flex-wrap gap-x-7 gap-y-3 border-t border-[#173F35]/10 pt-5">
                        <span class="inline-flex items-center gap-2 text-[12px] font-medium text-[#173F35]/55">
                            <i data-lucide="store" class="h-4 w-4 text-[#1F6F5B]"></i>
                            Multiple sellers
                        </span>
                        <span class="inline-flex items-center gap-2 text-[12px] font-medium text-[#173F35]/55">
                            <i data-lucide="scan-search" class="h-4 w-4 text-[#1F6F5B]"></i>
                            Clear product details
                        </span>
                        <span class="inline-flex items-center gap-2 text-[12px] font-medium text-[#173F35]/55">
                            <i data-lucide="shopping-bag" class="h-4 w-4 text-[#1F6F5B]"></i>
                            One simple marketplace
                        </span>
                    </div>
                </div>

                {{-- Editorial product composition --}}
                <div class="relative mx-auto h-[540px] w-full max-w-[690px] sm:h-[640px] lg:h-[680px]">
                    <div class="absolute left-[8%] top-[6%] h-[72%] w-[72%] rounded-[50%] bg-[#DDF3EC]/75 blur-[2px]"></div>
                    <div class="absolute bottom-[5%] right-[5%] h-[38%] w-[45%] rounded-[50%] bg-[#F59E0B]/8 blur-xl"></div>

                    <a href="{{ route('buyer.product', 'everyday-sneakers') }}" class="group absolute bottom-[4%] left-[4%] z-20 w-[49%] overflow-hidden rounded-[26px] bg-white p-2.5 shadow-[0_24px_60px_rgba(23,63,53,0.14)] transition duration-500 hover:-translate-y-1.5 hover:rotate-[-1deg]">
                        <div class="relative aspect-[1.05] overflow-hidden rounded-[19px] bg-[#EEF5F1]">
                            <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=88" alt="Everyday Sneakers" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.04]">
                            <span class="absolute left-3 top-3 rounded-full bg-white/92 px-3 py-1.5 text-[10px] font-semibold text-[#173F35] shadow-sm">Everyday wear</span>
                        </div>
                        <div class="flex items-end justify-between gap-3 px-2 pb-1 pt-3">
                            <div>
                                <p class="text-[10px] font-medium uppercase tracking-[0.12em] text-[#1F6F5B]/60">Fashion</p>
                                <p class="mt-1 text-[13px] font-semibold text-[#173F35] sm:text-[14px]">Everyday Sneakers</p>
                            </div>
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#173F35] text-white">
                                <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('buyer.product', 'wireless-headphones') }}" class="group absolute right-[2%] top-[3%] z-10 w-[43%] overflow-hidden rounded-[24px] bg-[#173F35] p-2.5 shadow-[0_22px_54px_rgba(23,63,53,0.18)] transition duration-500 hover:-translate-y-1.5 hover:rotate-[1deg]">
                        <div class="aspect-[.96] overflow-hidden rounded-[17px] bg-[#E9EFEA]">
                            <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=88" alt="Wireless Headphones" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.04]">
                        </div>
                        <div class="flex items-center justify-between gap-2 px-2 pb-1 pt-3 text-white">
                            <div>
                                <p class="text-[9px] font-medium uppercase tracking-[0.12em] text-white/50">Electronics</p>
                                <p class="mt-1 text-[12px] font-semibold sm:text-[13px]">Wireless Headphones</p>
                            </div>
                            <i data-lucide="arrow-up-right" class="h-4 w-4 shrink-0 text-[#56E0B6]"></i>
                        </div>
                    </a>

                    <a href="{{ route('buyer.product', 'shoulder-bag') }}" class="group absolute left-[3%] top-[7%] z-30 w-[34%] overflow-hidden rounded-[22px] border border-white/70 bg-white p-2 shadow-[0_18px_44px_rgba(23,63,53,0.12)] transition duration-500 hover:-translate-y-1.5 hover:rotate-[-2deg]">
                        <div class="aspect-[.84] overflow-hidden rounded-[15px] bg-[#EEEAE6]">
                            <img src="https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=700&q=88" alt="Minimalist Shoulder Bag" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.05]">
                        </div>
                        <p class="px-1 pb-1 pt-2.5 text-[11px] font-semibold text-[#173F35]">Shoulder Bag</p>
                    </a>

                    <a href="{{ route('buyer.product', 'skincare-essentials') }}" class="group absolute bottom-[8%] right-[2%] z-30 w-[32%] overflow-hidden rounded-[22px] border border-white/70 bg-[#FFF9EE] p-2 shadow-[0_18px_44px_rgba(23,63,53,0.11)] transition duration-500 hover:-translate-y-1.5 hover:rotate-[2deg]">
                        <div class="aspect-[.86] overflow-hidden rounded-[15px] bg-[#F5EADF]">
                            <img src="https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=700&q=88" alt="Skincare Essentials" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.05]">
                        </div>
                        <p class="px-1 pb-1 pt-2.5 text-[11px] font-semibold text-[#173F35]">Skincare Set</p>
                    </a>

                    <div class="absolute left-[43%] top-[40%] z-40 grid h-[128px] w-[128px] place-items-center rounded-full border-[8px] border-[#F8FAF8] bg-[#173F35] shadow-[0_20px_55px_rgba(23,63,53,0.2)] sm:h-[150px] sm:w-[150px]">
                        <img src="{{ asset('images/suki-logo.png') }}" alt="SUKI SHOP logo" class="h-[96px] w-[96px] object-contain sm:h-[112px] sm:w-[112px]">
                    </div>

                    <div class="absolute right-[18%] top-[47%] z-20 hidden items-center gap-2 rounded-full border border-[#173F35]/8 bg-white/95 px-3 py-2 shadow-[0_10px_28px_rgba(23,63,53,0.08)] sm:flex">
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-[#DDF3EC] text-[#1F6F5B]">
                            <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                        </span>
                        <span class="pr-1 text-[10px] font-semibold text-[#173F35]/70">Different finds. One SUKI SHOP.</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- =====================================================
             CATEGORY RAIL
        ====================================================== --}}
        <section id="categories" class="border-y border-[#173F35]/8 bg-white">
            <div class="mx-auto max-w-[1440px] px-5 py-14 sm:px-8 lg:px-12">
                <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#1F6F5B]">Start somewhere</p>
                        <h2 class="mt-2 text-[28px] font-semibold tracking-[-0.04em] text-[#173F35] sm:text-[34px]">Whatever you're looking for.</h2>
                    </div>
                    <a href="{{ route('buyer.shop') }}" class="inline-flex items-center gap-2 text-[13px] font-semibold text-[#173F35]/60 transition hover:text-[#1F6F5B]">
                        See all products
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                </div>

                @php
                    $landingCategories = [
                        ['name' => 'Fashion', 'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=550&q=82'],
                        ['name' => 'Beauty', 'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=550&q=82'],
                        ['name' => 'Home', 'image' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=550&q=82'],
                        ['name' => 'Electronics', 'image' => 'https://images.unsplash.com/photo-1498049794561-7780e7231661?auto=format&fit=crop&w=550&q=82'],
                        ['name' => 'Sports', 'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=550&q=82'],
                        ['name' => 'Food', 'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=550&q=82'],
                    ];
                @endphp

                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                    @foreach($landingCategories as $category)
                        <a href="{{ route('buyer.shop') }}" class="group relative overflow-hidden rounded-[18px] bg-[#EEF4F0]">
                            <div class="aspect-[1.12] overflow-hidden">
                                <img src="{{ $category['image'] }}" alt="{{ $category['name'] }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                            </div>
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#102F28]/85 via-[#102F28]/30 to-transparent px-4 pb-3.5 pt-10">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[12px] font-semibold text-white">{{ $category['name'] }}</span>
                                    <i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-white/70 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5"></i>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- =====================================================
             CURATED PRODUCT EDITORIAL
        ====================================================== --}}
        <section class="bg-[#F8FAF8] py-20 sm:py-24">
            <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
                <div class="grid gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:items-end">
                    <div class="max-w-[520px]">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#1F6F5B]">SUKI SHOP picks</p>
                        <h2 class="mt-3 text-[38px] font-semibold leading-[1.08] tracking-[-0.05em] text-[#173F35] sm:text-[48px]">A little bit of everything.</h2>
                        <p class="mt-5 text-[14px] leading-7 text-[#173F35]/58">Your everyday list rarely fits inside one category. Neither should your marketplace.</p>
                    </div>

                    <p class="max-w-[600px] text-[13px] leading-6 text-[#173F35]/48 lg:justify-self-end lg:text-right">
                        Browse practical basics, personal favorites, home pieces, tech, and more — then open each listing to see the actual product details before deciding.
                    </p>
                </div>

                <div class="mt-12 grid auto-rows-[220px] grid-cols-1 gap-4 sm:grid-cols-2 lg:auto-rows-[250px] lg:grid-cols-4">
                    <a href="{{ route('buyer.product', 'shoulder-bag') }}" class="group relative overflow-hidden rounded-[28px] bg-[#E9EFEA] sm:row-span-2 lg:col-span-2">
                        <img src="https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1200&q=88" alt="Minimalist Shoulder Bag" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.035]">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#102F28]/78 via-transparent to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-6 sm:p-8">
                            <span class="inline-flex rounded-full bg-white/14 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-white backdrop-blur-md">Fashion</span>
                            <div class="mt-3 flex items-end justify-between gap-4">
                                <div>
                                    <h3 class="text-[23px] font-semibold tracking-[-0.035em] text-white">Minimalist Shoulder Bag</h3>
                                    <p class="mt-1 text-[12px] text-white/68">Everyday Finds PH</p>
                                </div>
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white text-[#173F35] transition group-hover:rotate-45">
                                    <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                                </span>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('buyer.product', 'analog-watch') }}" class="group relative overflow-hidden rounded-[28px] bg-[#ECE8E2]">
                        <img src="https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=800&q=88" alt="Classic Analog Watch" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.05]">
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-5 pt-14">
                            <p class="text-[13px] font-semibold text-white">Classic Analog Watch</p>
                        </div>
                    </a>

                    <a href="{{ route('buyer.product', 'wireless-headphones') }}" class="group relative overflow-hidden rounded-[28px] bg-[#E8ECEB]">
                        <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=800&q=88" alt="Wireless Headphones" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.05]">
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-5 pt-14">
                            <p class="text-[13px] font-semibold text-white">Wireless Headphones</p>
                        </div>
                    </a>

                    <a href="{{ route('buyer.product', 'ceramic-home-set') }}" class="group relative overflow-hidden rounded-[28px] bg-[#EEE9E0] lg:col-span-2">
                        <img src="https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1100&q=88" alt="Ceramic Home Set" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.04]">
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#173F35]/65 to-transparent p-5 pt-16">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[13px] font-semibold text-white">Ceramic Home Set</p>
                                <span class="text-[10px] font-medium text-white/70">For everyday spaces</span>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="mt-7 text-center">
                    <a href="{{ route('buyer.shop') }}" class="inline-flex items-center gap-2.5 rounded-full border border-[#173F35]/12 bg-white px-5 py-3 text-[13px] font-semibold text-[#173F35] transition hover:border-[#1F6F5B]/30 hover:text-[#1F6F5B]">
                        Browse the marketplace
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                </div>
            </div>
        </section>

        {{-- =====================================================
             WHY SUKI SHOP
        ====================================================== --}}
        <section id="why-suki" class="bg-[#173F35] py-20 text-white sm:py-24">
            <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
                <div class="grid gap-10 lg:grid-cols-[1fr_1.05fr] lg:items-start">
                    <div class="max-w-[620px]">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#65E6BB]">Why SUKI SHOP</p>
                        <h2 class="mt-3 text-[39px] font-semibold leading-[1.1] tracking-[-0.05em] sm:text-[50px]">
                            Good finds should be easy to find.
                        </h2>
                    </div>

                    <p class="max-w-[610px] text-[14px] leading-7 text-white/58 lg:pt-8">
                        SUKI SHOP keeps the experience straightforward: products from different sellers, useful listing information, familiar shopping tools, and room to compare before you buy.
                    </p>
                </div>

                <div class="mt-14 grid gap-px overflow-hidden rounded-[26px] border border-white/10 bg-white/10 md:grid-cols-3">
                    <article class="bg-[#173F35] p-7 sm:p-8">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold tracking-[0.12em] text-[#65E6BB]">01</span>
                            <i data-lucide="layers-3" class="h-5 w-5 text-white/34"></i>
                        </div>
                        <h3 class="mt-14 text-[18px] font-semibold">Different sellers, one place.</h3>
                        <p class="mt-3 text-[12.5px] leading-6 text-white/50">Browse across sellers instead of feeling like every product belongs to one store.</p>
                    </article>

                    <article class="bg-[#173F35] p-7 sm:p-8">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold tracking-[0.12em] text-[#65E6BB]">02</span>
                            <i data-lucide="list-checks" class="h-5 w-5 text-white/34"></i>
                        </div>
                        <h3 class="mt-14 text-[18px] font-semibold">See the useful details.</h3>
                        <p class="mt-3 text-[12.5px] leading-6 text-white/50">Product pages surface prices, options, stock, seller information, and the details you actually need.</p>
                    </article>

                    <article class="bg-[#173F35] p-7 sm:p-8">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold tracking-[0.12em] text-[#65E6BB]">03</span>
                            <i data-lucide="mouse-pointer-click" class="h-5 w-5 text-white/34"></i>
                        </div>
                        <h3 class="mt-14 text-[18px] font-semibold">Shop at your own pace.</h3>
                        <p class="mt-3 text-[12.5px] leading-6 text-white/50">Save products, add to cart, check your orders, and continue when you're ready.</p>
                    </article>
                </div>

                <div class="mt-10 flex flex-wrap items-center justify-between gap-5 border-t border-white/10 pt-7">
                    <p class="text-[13px] font-medium text-white/56">Find it. Like it. Make it your <span class="font-semibold text-white">SUKI SHOP.</span></p>
                    <div class="flex flex-wrap gap-5 text-[11px] font-medium text-white/42">
                        <span>Marketplace browsing</span>
                        <span>Wishlist</span>
                        <span>Cart & checkout</span>
                        <span>Order tracking</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- =====================================================
             SELLER SECTION
        ====================================================== --}}
        <section id="sellers" class="bg-[#F1F6F3] py-20 sm:py-24">
            <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
                <div class="overflow-hidden rounded-[32px] border border-[#173F35]/8 bg-white">
                    <div class="grid lg:grid-cols-[1.05fr_.95fr]">
                        <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-14 xl:p-16">
                            <span class="w-fit rounded-full bg-[#DDF3EC] px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#1F6F5B]">For sellers</span>
                            <h2 class="mt-6 max-w-[560px] text-[38px] font-semibold leading-[1.08] tracking-[-0.05em] text-[#173F35] sm:text-[48px]">
                                Your products deserve their own shelf.
                            </h2>
                            <p class="mt-5 max-w-[550px] text-[14px] leading-7 text-[#173F35]/58">
                                SUKI SHOP is built as a marketplace, which means your shop keeps its own identity while shoppers discover it alongside other sellers.
                            </p>
                            <div class="mt-8 flex flex-wrap gap-3">
                                <a href="{{ route('seller.register') }}" class="inline-flex h-12 items-center gap-2.5 rounded-full bg-[#173F35] px-5 text-[13px] font-semibold text-white transition hover:bg-[#1F6F5B]">
                                    Start selling
                                    <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                                </a>
                                <a href="{{ route('login') }}" class="inline-flex h-12 items-center rounded-full px-4 text-[13px] font-semibold text-[#173F35]/62 transition hover:text-[#1F6F5B]">Seller sign in</a>
                            </div>
                        </div>

                        <div class="relative min-h-[430px] overflow-hidden bg-[#DDF3EC] lg:min-h-[520px]">
                            <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1200&q=88" alt="Online seller preparing products" class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#173F35]/70 via-transparent to-transparent"></div>

                            <div class="absolute bottom-5 left-5 right-5 rounded-[20px] border border-white/20 bg-[#173F35]/90 p-5 text-white backdrop-blur-md sm:bottom-7 sm:left-7 sm:right-7">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-10 w-10 place-items-center rounded-full bg-[#56E0B6]/15 text-[#65E6BB]">
                                        <i data-lucide="store" class="h-4.5 w-4.5"></i>
                                    </span>
                                    <div>
                                        <p class="text-[13px] font-semibold">A marketplace, not one giant store.</p>
                                        <p class="mt-1 text-[11px] text-white/50">Customers can discover different sellers under one SUKI SHOP experience.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- =====================================================
             FINAL CTA
        ====================================================== --}}
        <section class="bg-white py-20 sm:py-24">
            <div class="mx-auto max-w-[1120px] px-5 text-center sm:px-8">
                <img src="{{ asset('images/suki-logo.png') }}" alt="SUKI SHOP" class="mx-auto h-24 w-24 object-contain sm:h-28 sm:w-28">
                <p class="mt-5 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#1F6F5B]">Your marketplace, your favorites</p>
                <h2 class="mx-auto mt-4 max-w-[850px] text-[39px] font-semibold leading-[1.08] tracking-[-0.05em] text-[#173F35] sm:text-[52px]">
                    Your next favorite thing might be one scroll away.
                </h2>
                <p class="mx-auto mt-5 max-w-[620px] text-[14px] leading-7 text-[#173F35]/52">
                    Take a look around. The marketplace is ready when you are.
                </p>
                <a href="{{ route('buyer.home') }}" class="mt-8 inline-flex h-13 items-center gap-3 rounded-full bg-[#173F35] px-6 text-[14px] font-semibold text-white shadow-[0_12px_30px_rgba(23,63,53,0.14)] transition hover:-translate-y-0.5 hover:bg-[#1F6F5B]">
                    Enter SUKI SHOP
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </div>
        </section>
    </main>

    {{-- =========================================================
         LANDING FOOTER
    ========================================================== --}}
    <footer class="border-t border-[#173F35]/8 bg-[#F8FAF8]">
        <div class="mx-auto max-w-[1440px] px-5 py-10 sm:px-8 lg:px-12">
            <div class="grid gap-9 md:grid-cols-[1.2fr_.8fr_.8fr_.8fr]">
                <div class="max-w-[360px]">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('images/suki-logo.png') }}" alt="SUKI SHOP" class="h-10 w-10 object-contain">
                        <span class="text-[17px] font-bold tracking-[-0.04em] text-[#173F35]">SUKI SHOP</span>
                    </div>
                    <p class="mt-4 text-[12px] leading-6 text-[#173F35]/48">A friendly online marketplace for everyday finds, different sellers, and products worth coming back to.</p>
                </div>

                <div>
                    <h3 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#173F35]/45">Explore</h3>
                    <div class="mt-4 space-y-2.5 text-[12px] font-medium text-[#173F35]/62">
                        <a href="{{ route('buyer.home') }}" class="block hover:text-[#1F6F5B]">Marketplace</a>
                        <a href="{{ route('buyer.shop') }}" class="block hover:text-[#1F6F5B]">Products</a>
                        <a href="#categories" class="block hover:text-[#1F6F5B]">Categories</a>
                    </div>
                </div>

                <div>
                    <h3 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#173F35]/45">Account</h3>
                    <div class="mt-4 space-y-2.5 text-[12px] font-medium text-[#173F35]/62">
                        <a href="{{ route('login') }}" class="block hover:text-[#1F6F5B]">Log in</a>
                        <a href="{{ route('register') }}" class="block hover:text-[#1F6F5B]">Create account</a>
                        <a href="{{ route('seller.register') }}" class="block hover:text-[#1F6F5B]">Sell on SUKI SHOP</a>
                    </div>
                </div>

                <div>
                    <h3 class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#173F35]/45">Information</h3>
                    <div class="mt-4 space-y-2.5 text-[12px] font-medium text-[#173F35]/62">
                        <a href="#why-suki" class="block hover:text-[#1F6F5B]">About SUKI SHOP</a>
                        <span class="block">Help & support</span>
                        <span class="block">Privacy & terms</span>
                    </div>
                </div>
            </div>

            <div class="mt-9 flex flex-col justify-between gap-3 border-t border-[#173F35]/8 pt-6 text-[10.5px] text-[#173F35]/40 sm:flex-row sm:items-center">
                <p>© {{ date('Y') }} SUKI SHOP Marketplace. All rights reserved.</p>
                <p>Designed for clear, simple, everyday shopping.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const menuButton = document.getElementById('landingMenuButton');
            const mobileMenu = document.getElementById('landingMobileMenu');

            if (menuButton && mobileMenu) {
                menuButton.addEventListener('click', function () {
                    const isOpen = !mobileMenu.classList.contains('hidden');
                    mobileMenu.classList.toggle('hidden');
                    menuButton.setAttribute('aria-expanded', String(!isOpen));
                });

                mobileMenu.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        mobileMenu.classList.add('hidden');
                        menuButton.setAttribute('aria-expanded', 'false');
                    });
                });
            }
        });
    </script>
</body>
</html>
