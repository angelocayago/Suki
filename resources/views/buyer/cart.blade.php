@extends('layouts.app')

@section('content')

{{-- =========================================================
   SUKI SHOPPING CART
========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10">

    {{-- =====================================================
       HEADER
    ====================================================== --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-7">

        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                <a href="{{ route('buyer.home') }}" class="hover:text-[#1F6F5B] transition">
                    Home
                </a>

                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>

                <span class="text-gray-700">
                    Shopping Cart
                </span>
            </div>

            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                Shopping Cart
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Review your selected items before checkout.
            </p>
        </div>

        <a
            href="{{ route('buyer.shop') }}"
            class="inline-flex items-center gap-2 text-sm font-semibold text-[#1F6F5B] hover:text-[#155244] transition"
        >
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Continue Shopping
        </a>

    </div>


    {{-- =====================================================
       SUCCESS MESSAGE
    ====================================================== --}}
    @if(session('success'))

        <div class="mb-6 flex items-start gap-3 rounded-xl border border-[#1F6F5B]/20 bg-[#EEF8F3] px-4 py-3.5">

            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">
                <i
                    data-lucide="check"
                    class="w-4 h-4 text-[#1F6F5B]"
                ></i>
            </div>

            <div>
                <p class="text-sm font-semibold text-[#155244]">
                    Success
                </p>

                <p class="text-sm text-gray-600 mt-0.5">
                    {{ session('success') }}
                </p>
            </div>

        </div>

    @endif


    {{-- =====================================================
       ERROR MESSAGE
    ====================================================== --}}
    @if(session('error'))

        <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3.5">

            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100">
                <i
                    data-lucide="alert-circle"
                    class="w-4 h-4 text-red-500"
                ></i>
            </div>

            <div>
                <p class="text-sm font-semibold text-red-700">
                    Something went wrong
                </p>

                <p class="text-sm text-red-600 mt-0.5">
                    {{ session('error') }}
                </p>
            </div>

        </div>

    @endif


    {{-- =====================================================
       EMPTY CART
    ====================================================== --}}
    @if(empty($cart))

        <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 md:px-10">

            <div class="max-w-md mx-auto text-center">

                <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-[#EEF8F3]">
                    <i
                        data-lucide="shopping-cart"
                        class="w-9 h-9 text-[#1F6F5B]"
                    ></i>
                </div>

                <h2 class="mt-6 text-xl font-bold text-gray-900">
                    Your cart is empty
                </h2>

                <p class="mt-2 text-sm leading-6 text-gray-500">
                    Looks like you haven't added anything to your cart yet.
                    Discover something you'll love from our marketplace.
                </p>

                <a
                    href="{{ route('buyer.shop') }}"
                    class="mt-7 inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-7 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244]"
                >
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                    Start Shopping
                </a>

            </div>

        </div>


    @else

        @php
            $subtotal = 0;
            $totalItems = 0;

            foreach ($cart as $item) {
                $subtotal += (float) $item['price'] * (int) $item['quantity'];
                $totalItems += (int) $item['quantity'];
            }
        @endphp


        {{-- =================================================
           CART CONTENT
        ================================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">


            {{-- =============================================
               CART ITEMS
            ============================================== --}}
            <div class="lg:col-span-2">

                {{-- ITEMS HEADER --}}
                <div class="flex items-center justify-between mb-3">

                    <div class="flex items-center gap-2">

                        <h2 class="text-base md:text-lg font-bold text-gray-900">
                            Cart Items
                        </h2>

                        <span class="inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-[#EEF8F3] text-[#1F6F5B] text-xs font-bold">
                            {{ $totalItems }}
                        </span>

                    </div>

                    <span class="text-xs md:text-sm text-gray-500">
                        {{ $totalItems == 1 ? '1 item' : $totalItems . ' items' }}
                    </span>

                </div>


                {{-- PRODUCT ITEMS --}}
                <div class="space-y-4">

                    @foreach($cart as $slug => $item)

                        @php
                            $itemPrice = (float) $item['price'];
                            $quantity = (int) $item['quantity'];
                            $itemTotal = $itemPrice * $quantity;
                        @endphp

                        <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5">

                            <div class="flex gap-4 sm:gap-5">

                                {{-- =================================
                                   PRODUCT IMAGE
                                ================================== --}}
                                <a
                                    href="{{ route('buyer.product', ['slug' => $slug]) }}"
                                    class="group relative h-24 w-24 sm:h-28 sm:w-28 shrink-0 overflow-hidden rounded-xl bg-gray-100"
                                >

                                    <img
                                        src="{{ $item['image'] }}"
                                        alt="{{ $item['name'] }}"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    >

                                </a>


                                {{-- =================================
                                   PRODUCT INFORMATION
                                ================================== --}}
                                <div class="min-w-0 flex-1">

                                    {{-- TOP ROW --}}
                                    <div class="flex items-start justify-between gap-3">

                                        <div class="min-w-0">

                                            <a
                                                href="{{ route('buyer.product', ['slug' => $slug]) }}"
                                                class="line-clamp-2 text-sm sm:text-base font-semibold leading-6 text-gray-900 transition hover:text-[#1F6F5B]"
                                            >
                                                {{ $item['name'] }}
                                            </a>

                                            <p class="mt-1 text-sm font-medium text-[#1F6F5B]">
                                                ₱{{ number_format($itemPrice, 2) }}
                                            </p>

                                        </div>


                                        {{-- REMOVE --}}
                                        <form
                                            action="{{ route('buyer.cart.remove', ['slug' => $slug]) }}"
                                            method="POST"
                                            class="shrink-0"
                                        >
                                            @csrf

                                            <button
                                                type="submit"
                                                class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-red-50 hover:text-red-500"
                                                title="Remove item"
                                                aria-label="Remove {{ $item['name'] }}"
                                            >
                                                <i
                                                    data-lucide="trash-2"
                                                    class="w-4.5 h-4.5"
                                                ></i>
                                            </button>

                                        </form>

                                    </div>


                                    {{-- PRODUCT META --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">

                                        <span class="inline-flex items-center gap-1">
                                            <i
                                                data-lucide="package"
                                                class="w-3.5 h-3.5"
                                            ></i>
                                            Ready to ship
                                        </span>

                                        <span class="text-gray-300">
                                            |
                                        </span>

                                        <span class="inline-flex items-center gap-1 text-[#1F6F5B]">
                                            <i
                                                data-lucide="truck"
                                                class="w-3.5 h-3.5"
                                            ></i>
                                            Free shipping
                                        </span>

                                    </div>


                                    {{-- BOTTOM CONTROLS --}}
                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">

                                        {{-- QUANTITY --}}
                                        <div>

                                            <p class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-gray-400">
                                                Quantity
                                            </p>

                                            <div class="inline-flex items-center overflow-hidden rounded-lg border border-gray-200 bg-white">

                                                {{-- DECREASE --}}
                                                <form
                                                    action="{{ route('buyer.cart.decrease', ['slug' => $slug]) }}"
                                                    method="POST"
                                                >
                                                    @csrf

                                                    <button
                                                        type="submit"
                                                        class="flex h-9 w-9 items-center justify-center text-gray-600 transition hover:bg-gray-50 hover:text-[#1F6F5B]"
                                                        title="Decrease quantity"
                                                        aria-label="Decrease quantity"
                                                    >
                                                        <i
                                                            data-lucide="minus"
                                                            class="w-4 h-4"
                                                        ></i>
                                                    </button>

                                                </form>


                                                {{-- QUANTITY --}}
                                                <span class="flex h-9 min-w-10 items-center justify-center border-x border-gray-200 px-2 text-sm font-semibold text-gray-900">
                                                    {{ $quantity }}
                                                </span>


                                                {{-- INCREASE --}}
                                                <form
                                                    action="{{ route('buyer.cart.increase', ['slug' => $slug]) }}"
                                                    method="POST"
                                                >
                                                    @csrf

                                                    <button
                                                        type="submit"
                                                        class="flex h-9 w-9 items-center justify-center text-gray-600 transition hover:bg-gray-50 hover:text-[#1F6F5B]"
                                                        title="Increase quantity"
                                                        aria-label="Increase quantity"
                                                    >
                                                        <i
                                                            data-lucide="plus"
                                                            class="w-4 h-4"
                                                        ></i>
                                                    </button>

                                                </form>

                                            </div>

                                        </div>


                                        {{-- MOVE TO WISHLIST --}}
                                        <form
                                            action="{{ route('buyer.wishlist.add', ['slug' => $slug]) }}"
                                            method="POST"
                                        >
                                            @csrf

                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-medium text-gray-500 transition hover:text-[#1F6F5B]"
                                            >
                                                <i
                                                    data-lucide="heart"
                                                    class="w-4 h-4"
                                                ></i>

                                                Move to Wishlist
                                            </button>

                                        </form>


                                        {{-- ITEM TOTAL --}}
                                        <div class="text-right">

                                            <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400">
                                                Item Total
                                            </p>

                                            <p class="mt-0.5 text-base sm:text-lg font-bold text-gray-900">
                                                ₱{{ number_format($itemTotal, 2) }}
                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>


                {{-- CART NOTE --}}
                <div class="mt-5 rounded-xl border border-[#1F6F5B]/15 bg-[#EEF8F3] px-4 py-3.5">

                    <div class="flex items-start gap-3">

                        <i
                            data-lucide="shield-check"
                            class="mt-0.5 w-5 h-5 shrink-0 text-[#1F6F5B]"
                        ></i>

                        <div>
                            <p class="text-sm font-semibold text-[#155244]">
                                Shop with confidence
                            </p>

                            <p class="mt-0.5 text-xs leading-5 text-gray-600">
                                Your order is protected through SUKI's secure checkout process.
                            </p>
                        </div>

                    </div>

                </div>

            </div>


            {{-- =============================================
               ORDER SUMMARY
            ============================================== --}}
            <div>

                <div class="lg:sticky lg:top-24 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6">

                    {{-- SUMMARY HEADER --}}
                    <div class="flex items-center justify-between">

                        <h2 class="text-lg font-bold text-gray-900">
                            Order Summary
                        </h2>

                        <i
                            data-lucide="receipt"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>


                    {{-- SUMMARY DETAILS --}}
                    <div class="mt-6 space-y-4">

                        {{-- SUBTOTAL --}}
                        <div class="flex items-center justify-between text-sm">

                            <span class="text-gray-500">
                                Subtotal
                            </span>

                            <span class="font-medium text-gray-900">
                                ₱{{ number_format($subtotal, 2) }}
                            </span>

                        </div>


                        {{-- SHIPPING --}}
                        <div class="flex items-center justify-between text-sm">

                            <span class="text-gray-500">
                                Shipping
                            </span>

                            <span class="font-semibold text-[#1F6F5B]">
                                FREE
                            </span>

                        </div>

                    </div>


                    {{-- FREE SHIPPING NOTICE --}}
                    <div class="mt-5 flex items-start gap-2.5 rounded-xl bg-[#EEF8F3] px-3.5 py-3">

                        <i
                            data-lucide="truck"
                            class="mt-0.5 w-4 h-4 shrink-0 text-[#1F6F5B]"
                        ></i>

                        <p class="text-xs leading-5 text-[#155244]">
                            You qualify for free shipping on this order.
                        </p>

                    </div>


                    {{-- TOTAL --}}
                    <div class="mt-6 border-t border-gray-200 pt-5">

                        <div class="flex items-end justify-between gap-4">

                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    Total
                                </p>

                                <p class="mt-1 text-xs text-gray-400">
                                    Including shipping
                                </p>
                            </div>

                            <span class="text-xl sm:text-2xl font-bold text-[#1F6F5B]">
                                ₱{{ number_format($subtotal, 2) }}
                            </span>

                        </div>

                    </div>


                    {{-- CHECKOUT --}}
                    <a
                        href="{{ route('buyer.checkout') }}"
                        class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244]"
                    >
                        Proceed to Checkout

                        <i
                            data-lucide="arrow-right"
                            class="w-4 h-4"
                        ></i>
                    </a>


                    {{-- CONTINUE SHOPPING --}}
                    <a
                        href="{{ route('buyer.shop') }}"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-[#1F6F5B] px-6 py-3.5 text-sm font-semibold text-[#1F6F5B] transition hover:bg-[#EEF8F3]"
                    >
                        <i
                            data-lucide="shopping-bag"
                            class="w-4 h-4"
                        ></i>

                        Continue Shopping
                    </a>


                    {{-- PAYMENT INFO --}}
                    <div class="mt-6 border-t border-gray-100 pt-5">

                        <p class="text-xs font-semibold text-gray-700">
                            Secure checkout
                        </p>

                        <div class="mt-3 space-y-2.5">

                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <i
                                    data-lucide="lock"
                                    class="w-3.5 h-3.5 text-[#1F6F5B]"
                                ></i>
                                Secure payment process
                            </div>

                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <i
                                    data-lucide="badge-check"
                                    class="w-3.5 h-3.5 text-[#1F6F5B]"
                                ></i>
                                Buyer protection
                            </div>

                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <i
                                    data-lucide="headphones"
                                    class="w-3.5 h-3.5 text-[#1F6F5B]"
                                ></i>
                                SUKI customer support
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    @endif

</section>

@endsection