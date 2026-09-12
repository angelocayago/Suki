{{-- TOP SELLER BAR --}}
<div class="bg-[#1F6F5B] text-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="h-9 flex items-center justify-end">

            <div class="flex items-center text-xs sm:text-sm">

                {{-- SELLER CENTRE --}}
                <a
                    href="{{ route('seller.dashboard') }}"
                    class="hover:text-white/80 transition"
                >
                    Seller Centre
                </a>

                <span class="mx-2 text-white/60">|</span>

                {{-- START SELLING --}}
                <a
                    href="{{ route('seller.register') }}"
                    class="hover:text-white/80 transition"
                >
                    Start Selling
                </a>

            </div>

        </div>
    </div>
</div>


{{-- MAIN NAVBAR --}}
<nav class="sticky top-0 z-50 bg-white border-b border-gray-200">

    {{-- TOP NAVIGATION --}}
    <div class="max-w-7xl mx-auto px-4">

        <div class="h-16 flex items-center gap-6">

            {{-- LOGO --}}
            <a href="{{ route('buyer.home') }}" class="shrink-0 flex items-center">

                <div class="w-24 h-12 flex items-center justify-center">
                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI SHOP"
                        class="w-full h-full object-contain"
                    >
                </div>

            </a>


            {{-- SEARCH --}}
            <div class="flex-1 max-w-2xl">

                <form action="{{ route('buyer.shop') }}" method="GET">

                    <div class="relative">

                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search products, brands, and more..."
                            class="w-full h-11 pl-4 pr-12 rounded-lg
                                   border border-gray-300
                                   focus:outline-none
                                   focus:ring-2 focus:ring-[#1F6F5B]/20
                                   focus:border-[#1F6F5B]
                                   text-sm"
                        >

                        <button
                            type="submit"
                            class="absolute right-1 top-1
                                   w-9 h-9
                                   rounded-md
                                   bg-[#1F6F5B]
                                   text-white
                                   flex items-center justify-center
                                   hover:bg-[#155244]
                                   transition"
                        >
                            <i data-lucide="search" class="w-5 h-5"></i>
                        </button>

                    </div>

                </form>

            </div>


            {{-- RIGHT ACTIONS --}}
            <div class="flex items-center gap-5">

               {{-- CART --}}
@php
    $cartCount = 0;

    if (
        auth()->check() &&
        auth()->user()->role === 'buyer'
    ) {
        $cartCount = auth()
            ->user()
            ->cartItems()
            ->count();
    }
@endphp

<a
    href="{{ auth()->check() ? route('buyer.cart') : route('login') }}"
    class="relative text-gray-600 hover:text-[#1F6F5B] transition"
    title="Shopping Cart"
>

    <i data-lucide="shopping-cart" class="w-6 h-6"></i>

    @if($cartCount > 0)
        <span
            class="absolute -top-2 -right-2
                   min-w-5 h-5 px-1
                   rounded-full
                   bg-[#F59E0B]
                   text-white
                   text-[10px]
                   font-semibold
                   flex items-center justify-center"
        >
            {{ $cartCount > 99 ? '99+' : $cartCount }}
        </span>
    @endif

</a>

                {{-- AUTHENTICATED BUYER --}}
                @if(session('buyer_logged_in'))

                    {{-- NOTIFICATIONS --}}
                    <a
                        href="#"
                        class="text-gray-600 hover:text-[#1F6F5B] transition"
                        title="Notifications"
                    >
                        <i data-lucide="bell" class="w-6 h-6"></i>
                    </a>


                    {{-- ACCOUNT --}}
                    <div class="relative group">

                        <a
                            href="{{ route('buyer.account') }}"
                            class="flex items-center gap-2
                                   text-gray-700
                                   hover:text-[#1F6F5B]
                                   transition"
                            title="My Account"
                        >

                            <div
                                class="w-9 h-9 rounded-full
                                       bg-[#DDF3EC]
                                       flex items-center justify-center"
                            >
                                <i
                                    data-lucide="user"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>
                            </div>

                            <span class="hidden lg:block text-sm font-medium">
                                Account
                            </span>

                            <i
                                data-lucide="chevron-down"
                                class="hidden lg:block w-4 h-4 text-gray-400"
                            ></i>

                        </a>


                        {{-- ACCOUNT DROPDOWN --}}
                        <div
                            class="absolute right-0 top-full pt-3
                                   invisible opacity-0 translate-y-1
                                   group-hover:visible group-hover:opacity-100
                                   group-hover:translate-y-0
                                   transition-all duration-200"
                        >

                            <div
                                class="w-56 bg-white
                                       border border-gray-200
                                       rounded-xl
                                       shadow-lg
                                       overflow-hidden"
                            >

                                {{-- PROFILE --}}
                                <a
                                    href="{{ route('buyer.account') }}"
                                    class="flex items-center gap-3 px-4 py-3
                                           text-sm text-gray-700
                                           hover:bg-[#F8FAF8]
                                           hover:text-[#1F6F5B]"
                                >
                                    <i data-lucide="user-circle" class="w-4 h-4"></i>
                                    My Account
                                </a>


                                {{-- ORDERS --}}
                                <a
                                    href="{{ route('buyer.my-orders') }}"
                                    class="flex items-center gap-3 px-4 py-3
                                           text-sm text-gray-700
                                           hover:bg-[#F8FAF8]
                                           hover:text-[#1F6F5B]"
                                >
                                    <i data-lucide="package" class="w-4 h-4"></i>
                                    My Orders
                                </a>


                                {{-- WISHLIST --}}
                                <a
                                    href="{{ route('buyer.wishlist') }}"
                                    class="flex items-center gap-3 px-4 py-3
                                           text-sm text-gray-700
                                           hover:bg-[#F8FAF8]
                                           hover:text-[#1F6F5B]"
                                >
                                    <i data-lucide="heart" class="w-4 h-4"></i>
                                    My Wishlist
                                </a>


                                <div class="border-t border-gray-100"></div>


                                {{-- LOGOUT --}}
                                <form
                                    action="{{ route('logout') }}"
                                    method="POST"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="w-full flex items-center gap-3 px-4 py-3
                                               text-sm text-gray-600
                                               hover:bg-red-50
                                               hover:text-red-600
                                               text-left"
                                    >
                                        <i data-lucide="log-out" class="w-4 h-4"></i>
                                        Log Out
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>


                {{-- GUEST --}}
                @else

                    {{-- LOGIN --}}
                    <a
                        href="{{ route('login') }}"
                        class="hidden sm:flex items-center gap-2
                               text-sm font-medium
                               text-gray-600
                               hover:text-[#1F6F5B]
                               transition"
                    >
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Log In
                    </a>


                    {{-- SIGN UP --}}
                    <a
                        href="{{ route('register') }}"
                        class="hidden sm:flex items-center
                               px-4 py-2
                               rounded-lg
                               bg-[#1F6F5B]
                               text-white
                               text-sm font-medium
                               hover:bg-[#155244]
                               transition"
                    >
                        Sign Up
                    </a>

                @endif

            </div>

        </div>


        {{-- SECONDARY NAVIGATION --}}
        <div class="h-11 flex items-center gap-7 border-t border-gray-100">

            {{-- HOME --}}
            <a
                href="{{ route('buyer.home') }}"
                class="h-full flex items-center text-sm font-medium
                       {{ request()->routeIs('buyer.home')
                            ? 'text-[#1F6F5B] border-b-2 border-[#1F6F5B]'
                            : 'text-gray-600 hover:text-[#1F6F5B]' }}
                       transition"
            >
                Home
            </a>


            {{-- CATEGORIES --}}
            <a
                href="{{ route('buyer.shop') }}"
                class="text-sm
                       {{ request()->routeIs('buyer.shop')
                            ? 'text-[#1F6F5B] font-medium'
                            : 'text-gray-600' }}
                       hover:text-[#1F6F5B] transition"
            >
                Categories
            </a>


            {{-- FLASH DEALS --}}
            <a
                href="{{ route('buyer.shop') }}#flash-deals"
                class="text-sm text-gray-600
                       hover:text-[#1F6F5B] transition"
            >
                Flash Deals
            </a>


            {{-- NEW ARRIVALS --}}
            <a
                href="{{ route('buyer.shop') }}?sort=newest"
                class="text-sm text-gray-600
                       hover:text-[#1F6F5B] transition"
            >
                New Arrivals
            </a>


            {{-- BEST SELLERS --}}
            <a
                href="{{ route('buyer.shop') }}?sort=best-selling"
                class="text-sm text-gray-600
                       hover:text-[#1F6F5B] transition"
            >
                Best Sellers
            </a>


            <div class="flex-1"></div>


            {{-- HELP CENTER --}}
            <a
                href="#"
                class="flex items-center gap-2 text-sm
                       text-gray-600
                       hover:text-[#1F6F5B]
                       transition"
            >

                <i data-lucide="help-circle" class="w-4 h-4"></i>

                Help Center

            </a>

        </div>

    </div>

</nav>