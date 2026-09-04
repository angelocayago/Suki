@extends('layouts.app')

@section('content')

@php
    $wishlistCount = count($wishlist ?? []);
@endphp

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10">

    {{-- =========================================
         PAGE HEADER
    ========================================== --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">

        <div>
            <div class="flex items-center gap-3">

                <div class="w-11 h-11 rounded-xl bg-[#DDF3EC] flex items-center justify-center shrink-0">
                    <i
                        data-lucide="heart"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>
                </div>

                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-[#173F35]">
                        My Wishlist
                    </h1>

                    <p class="text-sm text-gray-500 mt-0.5">
                        Save products you love and come back to them anytime.
                    </p>
                </div>

            </div>
        </div>

        <a
            href="{{ route('buyer.shop') }}"
            class="inline-flex items-center justify-center gap-2
                   h-10 px-4
                   rounded-xl
                   border border-gray-200
                   bg-white
                   text-sm font-semibold
                   text-[#1F6F5B]
                   hover:bg-[#EEF8F3]
                   transition"
        >
            <i data-lucide="shopping-bag" class="w-4 h-4"></i>
            Continue Shopping
        </a>

    </div>


    {{-- =========================================
         SUCCESS MESSAGE
    ========================================== --}}
    @if(session('success'))

        <div class="mb-6 flex items-start gap-3
                    rounded-xl
                    border border-[#1F6F5B]/20
                    bg-[#EEF8F3]
                    px-4 py-3
                    text-sm text-[#155244]">

            <i
                data-lucide="check-circle-2"
                class="w-5 h-5 shrink-0 text-[#1F6F5B] mt-0.5"
            ></i>

            <span>{{ session('success') }}</span>

        </div>

    @endif


    {{-- =========================================
         ERROR MESSAGE
    ========================================== --}}
    @if(session('error'))

        <div class="mb-6 flex items-start gap-3
                    rounded-xl
                    border border-red-200
                    bg-red-50
                    px-4 py-3
                    text-sm text-red-700">

            <i
                data-lucide="alert-circle"
                class="w-5 h-5 shrink-0 mt-0.5"
            ></i>

            <span>{{ session('error') }}</span>

        </div>

    @endif


    {{-- =========================================
         EMPTY WISHLIST
    ========================================== --}}
    @if(empty($wishlist))

        <div class="bg-white
                    border border-gray-200
                    rounded-2xl
                    px-6 py-16
                    text-center">

            <div class="w-20 h-20 mx-auto
                        rounded-full
                        bg-[#EEF8F3]
                        flex items-center justify-center">

                <i
                    data-lucide="heart"
                    class="w-8 h-8 text-[#1F6F5B]"
                ></i>

            </div>

            <h2 class="mt-6 text-xl font-bold text-[#173F35]">
                Your wishlist is empty
            </h2>

            <p class="mt-2 max-w-md mx-auto text-sm leading-6 text-gray-500">
                Save products you're interested in by tapping the heart icon.
                Your favorite items will appear here.
            </p>

            <a
                href="{{ route('buyer.shop') }}"
                class="inline-flex items-center justify-center gap-2
                       mt-7
                       h-11 px-6
                       rounded-xl
                       bg-[#1F6F5B]
                       text-white
                       text-sm font-semibold
                       hover:bg-[#155244]
                       transition"
            >
                <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                Browse Products
            </a>

        </div>


    @else

        {{-- =========================================
             WISHLIST TOOLBAR
        ========================================== --}}
        <div class="flex items-center justify-between mb-5">

            <div>
                <p class="text-sm text-gray-500">
                    <span class="font-semibold text-gray-900">
                        {{ $wishlistCount }}
                    </span>

                    {{ $wishlistCount === 1 ? 'item' : 'items' }} saved
                </p>
            </div>

        </div>


        {{-- =========================================
             PRODUCT GRID
        ========================================== --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">

            @foreach($wishlist as $slug => $item)

                @php
                    $price = (float) ($item['price'] ?? 0);
                    $oldPrice = isset($item['old_price'])
                        ? (float) str_replace(',', '', $item['old_price'])
                        : null;

                    $hasDiscount = $oldPrice && $oldPrice > $price;

                    $discountPercent = $hasDiscount
                        ? round((($oldPrice - $price) / $oldPrice) * 100)
                        : null;

                    $rating = $item['rating'] ?? $item['seller_rating'] ?? 4.8;
                    $sold = $item['sold'] ?? 0;
                @endphp

                <article
                    class="group bg-white
                           border border-gray-200
                           rounded-2xl
                           overflow-hidden
                           hover:border-[#1F6F5B]/30
                           hover:shadow-lg
                           transition duration-200"
                >

                    {{-- =====================================
                         PRODUCT IMAGE
                    ====================================== --}}
                    <div class="relative">

                        <a
                            href="{{ route('buyer.product', ['slug' => $slug]) }}"
                            class="block aspect-square overflow-hidden bg-[#F3F5F4]"
                        >

                            <img
                                src="{{ $item['image'] }}"
                                alt="{{ $item['name'] }}"
                                class="w-full h-full object-cover
                                       group-hover:scale-105
                                       transition duration-500"
                            >

                        </a>


                        {{-- SALE BADGE --}}
                        @if($hasDiscount)

                            <div
                                class="absolute top-3 left-3
                                       px-2 py-1
                                       rounded-md
                                       bg-[#F59E0B]
                                       text-white
                                       text-[10px]
                                       font-bold
                                       uppercase"
                            >
                                -{{ $discountPercent }}%
                            </div>

                        @endif


                        {{-- REMOVE FROM WISHLIST --}}
                        <form
                            action="{{ route('buyer.wishlist.remove', ['slug' => $slug]) }}"
                            method="POST"
                            class="absolute top-3 right-3"
                        >
                            @csrf

                            <button
                                type="submit"
                                aria-label="Remove from wishlist"
                                title="Remove from wishlist"
                                class="w-9 h-9
                                       rounded-full
                                       bg-white/95
                                       shadow-sm
                                       flex items-center justify-center
                                       text-[#1F6F5B]
                                       hover:text-red-500
                                       hover:bg-white
                                       transition"
                            >

                                <i
                                    data-lucide="heart"
                                    class="w-4 h-4 fill-current"
                                ></i>

                            </button>

                        </form>

                    </div>


                    {{-- =====================================
                         PRODUCT INFORMATION
                    ====================================== --}}
                    <div class="p-4">

                        {{-- PRODUCT NAME --}}
                        <a
                            href="{{ route('buyer.product', ['slug' => $slug]) }}"
                            class="block
                                   text-sm
                                   font-semibold
                                   text-gray-900
                                   leading-5
                                   line-clamp-2
                                   hover:text-[#1F6F5B]
                                   transition"
                        >
                            {{ $item['name'] }}
                        </a>


                        {{-- PRICE --}}
                        <div class="mt-2 flex items-baseline gap-2 flex-wrap">

                            <span class="text-lg font-bold text-[#1F6F5B]">
                                ₱{{ number_format($price, 2) }}
                            </span>

                            @if($hasDiscount)

                                <span class="text-xs text-gray-400 line-through">
                                    ₱{{ number_format($oldPrice, 2) }}
                                </span>

                            @endif

                        </div>


                        {{-- RATING / SOLD --}}
                        <div class="flex items-center gap-2 mt-2 text-[11px] text-gray-500">

                            <span class="inline-flex items-center gap-1">

                                <i
                                    data-lucide="star"
                                    class="w-3.5 h-3.5 text-[#F59E0B] fill-current"
                                ></i>

                                <span class="font-medium text-gray-700">
                                    {{ number_format((float) $rating, 1) }}
                                </span>

                            </span>

                            <span class="text-gray-300">•</span>

                            <span>
                                {{ number_format((int) $sold) }} sold
                            </span>

                        </div>


                        {{-- SHIPPING --}}
                        <div class="flex items-center gap-1.5 mt-2">

                            <i
                                data-lucide="truck"
                                class="w-3.5 h-3.5 text-[#1F6F5B]"
                            ></i>

                            <span class="text-[11px] text-gray-500">
                                Available for delivery
                            </span>

                        </div>


                        {{-- =====================================
                             ACTIONS
                        ====================================== --}}
                        <div class="mt-4 space-y-2">

                            {{-- MOVE TO CART --}}
                            <form
                                action="{{ route('buyer.wishlist.move-to-cart', ['slug' => $slug]) }}"
                                method="POST"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="w-full
                                           h-10
                                           rounded-xl
                                           bg-[#1F6F5B]
                                           text-white
                                           text-xs
                                           font-semibold
                                           flex items-center
                                           justify-center
                                           gap-2
                                           hover:bg-[#155244]
                                           transition"
                                >

                                    <i
                                        data-lucide="shopping-cart"
                                        class="w-4 h-4"
                                    ></i>

                                    Move to Cart

                                </button>

                            </form>


                            {{-- REMOVE --}}
                            <form
                                action="{{ route('buyer.wishlist.remove', ['slug' => $slug]) }}"
                                method="POST"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="w-full
                                           h-10
                                           rounded-xl
                                           border border-gray-200
                                           bg-white
                                           text-gray-500
                                           text-xs
                                           font-medium
                                           flex items-center
                                           justify-center
                                           gap-2
                                           hover:border-red-200
                                           hover:text-red-500
                                           hover:bg-red-50
                                           transition"
                                >

                                    <i
                                        data-lucide="trash-2"
                                        class="w-4 h-4"
                                    ></i>

                                    Remove

                                </button>

                            </form>

                        </div>

                    </div>

                </article>

            @endforeach

        </div>

    @endif

</section>

@endsection