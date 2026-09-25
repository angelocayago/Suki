@php

    $cartCount = 0;

    if (
        auth()->check() &&
        auth()->user()->role === 'buyer'
    ) {

        try {

            $cartCount = auth()
                ->user()
                ->cartItems()
                ->count();

        } catch (\Throwable $e) {

            $cartCount = 0;

        }

    }

@endphp


{{-- =========================================================
   TOP BAR
========================================================= --}}

<div class="hidden bg-[#173F35] text-white md:block">

    <div
        class="mx-auto flex h-8 max-w-[1500px]
               items-center justify-between
               px-6 lg:px-10"
    >

        <p class="text-[10px] font-medium tracking-[0.04em] text-white/70">
            Good people. Better communities.
        </p>


        <div
            class="flex items-center gap-6
                   text-[10px] font-medium
                   text-white/70"
        >

            <a
                href="{{ route('landing') }}#how-it-works"
                class="transition hover:text-white"
            >
                How It Works
            </a>

            <a
                href="{{ route('seller.dashboard') }}"
                class="transition hover:text-white"
            >
                Seller Centre
            </a>

            <a
                href="{{ route('rider.apply') }}"
                class="transition hover:text-white"
            >
                Become a Rider
            </a>

        </div>

    </div>

</div>


{{-- =========================================================
   MAIN NAVBAR
========================================================= --}}

<header
    class="sticky top-0 z-50
           border-b border-[#173F35]/10
           bg-[#F8FAF8]/95
           backdrop-blur-xl"
>

    <div class="mx-auto max-w-[1500px] px-4 sm:px-6 lg:px-10">

        <div class="flex h-[74px] items-center gap-4 lg:gap-7">


            {{-- LOGO --}}
            <a
                href="{{ route('buyer.home') }}"
                class="flex shrink-0 items-center gap-3"
            >

                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI SHOP"
                    class="h-10 w-10 object-contain sm:h-11 sm:w-11"
                >


                <div class="hidden leading-none sm:block">

                    <span
                        class="block text-[17px] font-bold
                               tracking-[-0.04em]
                               text-[#173F35]"
                    >
                        SUKI SHOP
                    </span>

                    <span
                        class="mt-1 block
                               text-[8px] font-medium
                               uppercase tracking-[0.16em]
                               text-[#1F6F5B]/60"
                    >
                        Connected Marketplace
                    </span>

                </div>

            </a>


            {{-- SEARCH --}}
            <form
                action="{{ route('buyer.shop') }}"
                method="GET"
                class="min-w-0 flex-1 lg:max-w-2xl"
            >

                <div class="relative">

                    <i
                        data-lucide="search"
                        class="pointer-events-none
                               absolute left-4 top-1/2
                               h-4 w-4
                               -translate-y-1/2
                               text-[#789087]"
                    ></i>


                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search products and stores"
                        class="h-11 w-full
                               rounded-xl
                               border border-[#DDE6E1]
                               bg-white
                               pl-11 pr-12
                               text-sm text-[#24312C]
                               placeholder:text-[#94A39C]
                               focus:border-[#1F6F5B]
                               focus:ring-4
                               focus:ring-[#DDF3EC]/70"
                    >


                    <button
                        type="submit"
                        aria-label="Search"
                        class="absolute right-1.5 top-1.5
                               flex h-8 w-8
                               items-center justify-center
                               rounded-lg
                               bg-[#173F35]
                               text-white
                               transition
                               hover:bg-[#1F6F5B]"
                    >

                        <i
                            data-lucide="arrow-right"
                            class="h-4 w-4"
                        ></i>

                    </button>

                </div>

            </form>


            {{-- RIGHT ACTIONS --}}
            <div class="flex items-center gap-1 sm:gap-2">


                {{-- CART --}}
                <a
                    href="{{ auth()->check() ? route('buyer.cart') : route('login') }}"
                    title="Cart"
                    class="relative
                           flex h-10 w-10
                           items-center justify-center
                           rounded-xl
                           text-[#52635B]
                           transition
                           hover:bg-[#EEF5F1]
                           hover:text-[#173F35]"
                >

                    <i
                        data-lucide="shopping-bag"
                        class="h-5 w-5"
                    ></i>


                    @if($cartCount > 0)

                        <span
                            class="absolute
                                   -right-0.5 -top-0.5
                                   flex min-w-[18px]
                                   h-[18px]
                                   items-center justify-center
                                   rounded-full
                                   bg-[#173F35]
                                   px-1
                                   text-[9px]
                                   font-bold
                                   text-white"
                        >
                            {{ $cartCount > 99 ? '99+' : $cartCount }}
                        </span>

                    @endif

                </a>


                @if(session('buyer_logged_in'))


                    {{-- WISHLIST --}}
                    <a
                        href="{{ route('buyer.wishlist') }}"
                        title="Wishlist"
                        class="hidden h-10 w-10
                               items-center justify-center
                               rounded-xl
                               text-[#52635B]
                               transition
                               hover:bg-[#EEF5F1]
                               hover:text-[#173F35]
                               sm:flex"
                    >

                        <i
                            data-lucide="heart"
                            class="h-5 w-5"
                        ></i>

                    </a>


                    {{-- ACCOUNT --}}
                    <div class="group relative">

                        <a
                            href="{{ route('buyer.account') }}"
                            class="flex h-10
                                   items-center gap-2
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-2.5
                                   text-[#34483F]
                                   transition
                                   hover:border-[#BFD2C9]
                                   hover:bg-[#FAFCFB]
                                   sm:px-3"
                        >

                            <span
                                class="flex h-7 w-7
                                       items-center justify-center
                                       rounded-lg
                                       bg-[#DDF3EC]
                                       text-[#173F35]"
                            >

                                <i
                                    data-lucide="user"
                                    class="h-4 w-4"
                                ></i>

                            </span>


                            <span
                                class="hidden
                                       text-xs font-semibold
                                       lg:inline"
                            >
                                Account
                            </span>


                            <i
                                data-lucide="chevron-down"
                                class="hidden
                                       h-3.5 w-3.5
                                       text-[#8A9992]
                                       lg:block"
                            ></i>

                        </a>


                        {{-- DROPDOWN --}}
                        <div
                            class="invisible
                                   absolute right-0 top-full
                                   pt-2
                                   opacity-0
                                   transition
                                   group-hover:visible
                                   group-hover:opacity-100"
                        >

                            <div
                                class="w-56
                                       overflow-hidden
                                       rounded-2xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       p-2
                                       shadow-[0_18px_50px_rgba(23,63,53,.12)]"
                            >

                                <a
                                    href="{{ route('buyer.account') }}"
                                    class="flex items-center gap-3
                                           rounded-xl
                                           px-3 py-2.5
                                           text-sm text-[#52635B]
                                           hover:bg-[#F3F7F5]
                                           hover:text-[#173F35]"
                                >

                                    <i
                                        data-lucide="user-round"
                                        class="h-4 w-4"
                                    ></i>

                                    My Account

                                </a>


                                <a
                                    href="{{ route('buyer.my-orders') }}"
                                    class="flex items-center gap-3
                                           rounded-xl
                                           px-3 py-2.5
                                           text-sm text-[#52635B]
                                           hover:bg-[#F3F7F5]
                                           hover:text-[#173F35]"
                                >

                                    <i
                                        data-lucide="package"
                                        class="h-4 w-4"
                                    ></i>

                                    My Orders

                                </a>


                                <a
                                    href="{{ route('buyer.wishlist') }}"
                                    class="flex items-center gap-3
                                           rounded-xl
                                           px-3 py-2.5
                                           text-sm text-[#52635B]
                                           hover:bg-[#F3F7F5]
                                           hover:text-[#173F35]"
                                >

                                    <i
                                        data-lucide="heart"
                                        class="h-4 w-4"
                                    ></i>

                                    Wishlist

                                </a>


                                <div
                                    class="my-1
                                           border-t border-[#EDF1EF]"
                                ></div>


                                <form
                                    action="{{ route('logout') }}"
                                    method="POST"
                                >

                                    @csrf

                                    <button
                                        type="submit"
                                        class="flex w-full
                                               items-center gap-3
                                               rounded-xl
                                               px-3 py-2.5
                                               text-left
                                               text-sm text-[#66736D]
                                               hover:bg-red-50
                                               hover:text-red-600"
                                    >

                                        <i
                                            data-lucide="log-out"
                                            class="h-4 w-4"
                                        ></i>

                                        Log Out

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>


                @else


                    <a
                        href="{{ route('login') }}"
                        class="hidden
                               rounded-xl
                               px-3 py-2
                               text-xs font-semibold
                               text-[#52635B]
                               transition
                               hover:text-[#173F35]
                               sm:inline-flex"
                    >
                        Log In
                    </a>


                    <a
                        href="{{ route('register') }}"
                        class="hidden
                               rounded-xl
                               bg-[#173F35]
                               px-4 py-2.5
                               text-xs font-semibold
                               text-white
                               transition
                               hover:bg-[#1F6F5B]
                               sm:inline-flex"
                    >
                        Create account
                    </a>


                @endif

            </div>

        </div>


        {{-- =====================================================
           SECONDARY NAVIGATION
        ====================================================== --}}

        <nav
            class="flex h-11
                   items-center gap-5
                   overflow-x-auto
                   border-t border-[#173F35]/10
                   text-[12px] font-medium
                   sm:gap-7"
        >

            <a
                href="{{ route('buyer.home') }}"
                class="whitespace-nowrap transition
                {{ request()->routeIs('buyer.home')
                    ? 'text-[#173F35]'
                    : 'text-[#6E7D76] hover:text-[#173F35]' }}"
            >
                Home
            </a>


            <a
                href="{{ route('buyer.shop') }}"
                class="whitespace-nowrap transition
                {{ request()->routeIs('buyer.shop') || request()->routeIs('buyer.product*')
                    ? 'text-[#173F35]'
                    : 'text-[#6E7D76] hover:text-[#173F35]' }}"
            >
                Shop
            </a>


            <a
                href="{{ route('buyer.shop') }}?sort=newest"
                class="whitespace-nowrap
                       text-[#6E7D76]
                       transition
                       hover:text-[#173F35]"
            >
                New arrivals
            </a>


            <a
                href="{{ route('buyer.shop') }}?sort=best-selling"
                class="whitespace-nowrap
                       text-[#6E7D76]
                       transition
                       hover:text-[#173F35]"
            >
                Best sellers
            </a>


            @if(session('buyer_logged_in'))

                <a
                    href="{{ route('buyer.my-orders') }}"
                    class="whitespace-nowrap
                           text-[#6E7D76]
                           transition
                           hover:text-[#173F35]"
                >
                    My orders
                </a>

            @endif


            <span
                class="ml-auto hidden
                       items-center gap-2
                       whitespace-nowrap
                       text-[#8B9992]
                       lg:flex"
            >

                <i
                    data-lucide="shield-check"
                    class="h-4 w-4"
                ></i>

                Secure marketplace experience

            </span>

        </nav>

    </div>

</header>