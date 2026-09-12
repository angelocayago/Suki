@extends('layouts.app')

@section('content')

{{-- SUCCESS MESSAGE --}}
@if(session('success'))
    <div class="max-w-7xl mx-auto px-4 pt-5">
        <div class="flex items-center gap-3 p-4 rounded-xl
                    bg-[#DDF3EC] border border-[#1F6F5B]/20
                    text-[#155244] text-sm">

            <i data-lucide="check-circle" class="w-5 h-5"></i>

            <span>{{ session('success') }}</span>
        </div>
    </div>
@endif


{{-- BREADCRUMB --}}
<section class="max-w-7xl mx-auto px-4 pt-7">

    <div class="flex items-center gap-2 text-sm text-gray-500">

        <a href="{{ route('buyer.home') }}"
           class="hover:text-[#1F6F5B]">
            Home
        </a>

        <i data-lucide="chevron-right" class="w-4 h-4"></i>

        <a href="{{ route('buyer.shop') }}"
           class="hover:text-[#1F6F5B]">
            Shop
        </a>

        <i data-lucide="chevron-right" class="w-4 h-4"></i>

        <span class="text-gray-800 font-medium">
            {{ $product['name'] }}
        </span>

    </div>

</section>


{{-- PRODUCT DETAILS --}}
<section class="max-w-7xl mx-auto px-4 py-7">

    <div class="bg-white rounded-2xl border border-gray-200 p-5 md:p-7">

        <div class="grid lg:grid-cols-2 gap-8">


            {{-- PRODUCT IMAGE --}}
            <div>

                <div class="relative aspect-square rounded-xl overflow-hidden bg-gray-100">

                    <img
                        id="mainProductImage"
                        src="{{ $product['image'] }}"
                        alt="{{ $product['name'] }}"
                        class="w-full h-full object-cover"
                    >

                    {{-- ===================================================== --}}
{{-- WISHLIST TOGGLE --}}
{{-- ===================================================== --}}

@php
    $isWishlisted = false;

    if (
        auth()->check() &&
        auth()->user()->role === 'buyer'
    ) {
        $isWishlisted = auth()
            ->user()
            ->wishlistItems()
            ->where('product_slug', $slug)
            ->exists();
    }
@endphp

<form
    action="{{ route('buyer.wishlist.toggle', ['slug' => $slug]) }}"
    method="POST"
    class="absolute top-4 right-4 z-20"
>
    @csrf

    <button
        type="submit"
        class="w-10 h-10 rounded-full border flex items-center justify-center shadow-sm transition-all duration-200"
        style="
            @if($isWishlisted)
                background-color: #1F6F5B;
                border-color: #1F6F5B;
                color: #ffffff;
            @else
                background-color: #ffffff;
                border-color: #d1d5db;
                color: #6b7280;
            @endif
        "
        title="{{ $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist' }}"
        aria-label="{{ $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist' }}"
    >
        <i
            data-lucide="heart"
            class="w-5 h-5"
            style="
                @if($isWishlisted)
                    fill: #ffffff;
                    stroke: #ffffff;
                @else
                    fill: none;
                    stroke: #6b7280;
                @endif
            "
        ></i>
    </button>
</form>

                {{-- THUMBNAILS --}}
                <div class="grid grid-cols-4 gap-3 mt-4">

                    @foreach($product['images'] as $index => $image)

                        <button
                            type="button"
                            onclick="changeImage(this)"
                            data-image="{{ $image }}"
                            class="product-thumb aspect-square rounded-lg overflow-hidden
                                   border-2
                                   {{ $index === 0
                                        ? 'border-[#1F6F5B]'
                                        : 'border-transparent hover:border-[#1F6F5B]' }}"
                        >

                            <img
                                src="{{ $image }}"
                                class="w-full h-full object-cover"
                                alt="{{ $product['name'] }} image {{ $index + 1 }}"
                            >

                        </button>

                    @endforeach

                </div>

            </div>


            {{-- PRODUCT INFORMATION --}}
            <div class="flex flex-col">

                {{-- CATEGORY --}}
                <p class="text-sm text-[#1F6F5B] font-medium">
                    {{ $product['category'] }}
                </p>


                {{-- NAME --}}
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mt-2">
                    {{ $product['name'] }}
                </h1>


                {{-- RATING --}}
                <div class="flex flex-wrap items-center gap-3 mt-4">

                    <div class="flex items-center gap-1 text-[#F59E0B]">

                        <i data-lucide="star"
                           class="w-4 h-4 fill-current">
                        </i>

                        <span class="font-semibold text-gray-800">
                            {{ $product['rating'] }}
                        </span>

                    </div>

                    <span class="text-gray-300">|</span>

                    <span class="text-sm text-gray-500 underline">
                        {{ $product['ratings'] }} Ratings
                    </span>

                    <span class="text-gray-300">|</span>

                    <span class="text-sm text-gray-500">
                        {{ $product['sold'] }} Sold
                    </span>

                </div>


                {{-- PRICE --}}
                <div class="mt-6 p-5 rounded-xl bg-[#F8FAF8]">

                    <div class="flex items-center gap-3 flex-wrap">

                        <span class="text-3xl font-bold text-[#1F6F5B]">
                            ₱{{ $product['price'] }}
                        </span>

                        <span class="text-sm text-gray-400 line-through">
                            ₱{{ $product['old_price'] }}
                        </span>

                        <span class="text-xs font-semibold
                                     bg-[#F59E0B] text-white
                                     px-2 py-1 rounded">
                            {{ $product['discount'] }}
                        </span>

                    </div>

                </div>


                {{-- SHIPPING --}}
                <div class="py-5 border-b border-gray-100">

                    <div class="flex gap-4">

                        <i data-lucide="truck"
                           class="w-5 h-5 text-[#1F6F5B] mt-0.5">
                        </i>

                        <div>

                            <p class="text-sm font-medium">
                                Shipping
                            </p>

                            <p class="text-sm text-gray-500 mt-1">
                                Choose your preferred delivery option at checkout.
                            </p>

                        </div>

                    </div>

                </div>


                {{-- ADD TO CART FORM --}}
                <form
                    action="{{ route('buyer.cart.add', ['slug' => $slug]) }}"
                    method="POST"
                    id="addToCartForm"
                >

                    @csrf


                    {{-- COLOR --}}
                    @if(!empty($product['colors']))

                        <div class="py-5">

                            <div class="flex items-start gap-4">

                                <span class="text-sm text-gray-500 w-16 pt-2">
                                    Color
                                </span>

                                <div class="flex flex-wrap gap-2">

                                    @foreach($product['colors'] as $index => $color)

                                        <button
                                            type="button"
                                            onclick="selectColor(this)"
                                            data-color="{{ $color }}"
                                            class="color-option px-4 py-2 rounded-lg
                                                   text-sm font-medium
                                                   {{ $index === 0
                                                        ? 'border-2 border-[#1F6F5B] bg-[#F8FAF8]'
                                                        : 'border border-gray-200 hover:border-[#1F6F5B]' }}"
                                        >
                                            {{ $color }}
                                        </button>

                                    @endforeach

                                </div>

                            </div>

                        </div>

                        {{-- SELECTED COLOR --}}
                        <input
                            type="hidden"
                            name="color"
                            id="selectedColor"
                            value="{{ $product['colors'][0] ?? '' }}"
                        >

                    @endif


                    {{-- QUANTITY --}}
                    <div class="flex items-center gap-4">

                        <span class="text-sm text-gray-500 w-16">
                            Quantity
                        </span>

                        <div class="flex items-center border border-gray-200 rounded-lg">

                            <button
                                type="button"
                                onclick="decreaseQuantity()"
                                class="w-10 h-10 flex items-center justify-center
                                       hover:bg-gray-50"
                            >
                                <i data-lucide="minus" class="w-4 h-4"></i>
                            </button>

                            <span
                                id="quantity"
                                class="w-10 text-center text-sm font-medium"
                            >
                                1
                            </span>

                            <button
                                type="button"
                                onclick="increaseQuantity()"
                                class="w-10 h-10 flex items-center justify-center
                                       hover:bg-gray-50"
                            >
                                <i data-lucide="plus" class="w-4 h-4"></i>
                            </button>

                        </div>

                        <span class="text-xs text-gray-400">
                            {{ $product['stock'] }} pieces available
                        </span>

                    </div>


                    {{-- HIDDEN QUANTITY --}}
                    <input
                        type="hidden"
                        name="quantity"
                        id="quantityInput"
                        value="1"
                    >


                    {{-- ACTIONS --}}
                    <div class="grid sm:grid-cols-2 gap-3 mt-7">

                        {{-- ADD TO CART --}}
                        <button
                            type="submit"
                            class="w-full h-12 flex items-center justify-center gap-2
                                   rounded-lg border-2
                                   border-[#1F6F5B]
                                   text-[#1F6F5B]
                                   font-semibold
                                   hover:bg-[#DDF3EC]
                                   transition"
                        >

                            <i data-lucide="shopping-cart"
                               class="w-5 h-5">
                            </i>

                            Add to Cart

                        </button>


                        {{-- BUY NOW --}}
                        <button
                            type="button"
                            onclick="buyNow()"
                            class="h-12 rounded-lg
                                   bg-[#1F6F5B] text-white
                                   font-semibold
                                   hover:bg-[#155244]
                                   transition"
                        >
                            Buy Now
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</section>


{{-- SELLER --}}
<section class="max-w-7xl mx-auto px-4">

    <div class="bg-white border border-gray-200 rounded-2xl p-6">

        <div class="flex flex-col sm:flex-row sm:items-center
                    justify-between gap-5">

            <div class="flex items-center gap-4">

                <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-100">

                    <img
                        src="https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=200&q=80"
                        class="w-full h-full object-cover"
                        alt="{{ $product['seller'] }}"
                    >

                </div>

                <div>

                    <h2 class="font-semibold">
                        {{ $product['seller'] }}
                    </h2>

                    <p class="text-xs text-gray-500 mt-1">
                        Active 10 minutes ago
                    </p>

                    <div class="flex items-center gap-2 mt-2">

                        <span class="text-xs text-[#1F6F5B]">
                            {{ $product['seller_rating'] }} Positive Rating
                        </span>

                        <span class="text-xs text-gray-400">
                            •
                        </span>

                        <span class="text-xs text-gray-500">
                            {{ $product['seller_products'] }} Products
                        </span>

                    </div>

                </div>

            </div>


            <div class="flex gap-2">

                <button
                    type="button"
                    class="px-4 py-2 rounded-lg
                           border border-[#1F6F5B]
                           text-[#1F6F5B] text-sm font-medium
                           hover:bg-[#DDF3EC]"
                >
                    View Shop
                </button>

                <button
                    type="button"
                    class="px-4 py-2 rounded-lg
                           bg-[#1F6F5B] text-white
                           text-sm font-medium
                           hover:bg-[#155244]"
                >
                    Chat
                </button>

            </div>

        </div>

    </div>

</section>


{{-- PRODUCT DESCRIPTION + REVIEWS --}}
<section class="max-w-7xl mx-auto px-4 py-8">

    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">

        <div class="flex border-b border-gray-200">

            <button
                type="button"
                class="px-6 py-4 text-sm font-semibold
                       text-[#1F6F5B]
                       border-b-2 border-[#1F6F5B]"
            >
                Product Description
            </button>

            <button
                type="button"
                class="px-6 py-4 text-sm font-medium
                       text-gray-500 hover:text-gray-800"
            >
                Reviews ({{ $product['ratings'] }})
            </button>

        </div>


        <div class="p-6 md:p-8">

            <h2 class="font-semibold text-lg">
                About this product
            </h2>

            <p class="text-sm text-gray-600 leading-7 mt-4 max-w-4xl">
                {{ $product['description'] }}
            </p>

            <ul class="mt-5 space-y-3 text-sm text-gray-600">

                @foreach($product['features'] as $feature)

                    <li class="flex gap-3">

                        <span class="text-[#1F6F5B]">
                            •
                        </span>

                        {{ $feature }}

                    </li>

                @endforeach

            </ul>

        </div>

    </div>

</section>


{{-- RELATED PRODUCTS --}}
<section class="max-w-7xl mx-auto px-4 pb-16">

    <div class="flex items-center justify-between mb-5">

        <h2 class="text-xl font-bold">
            You May Also Like
        </h2>

        <a href="{{ route('buyer.shop') }}"
           class="text-sm text-[#1F6F5B] font-medium hover:underline">
            View More
        </a>

    </div>


    @php

        $relatedProducts = [

            [
                'name' => 'Everyday Sneakers',
                'slug' => 'everyday-sneakers',
                'price' => '799',
                'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=600&q=80'
            ],

            [
                'name' => 'Classic Analog Watch',
                'slug' => 'analog-watch',
                'price' => '699',
                'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=600&q=80'
            ],

            [
                'name' => 'Skincare Essentials Set',
                'slug' => 'skincare-essentials',
                'price' => '459',
                'image' => 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=600&q=80'
            ],

            [
                'name' => 'Portable Bluetooth Speaker',
                'slug' => 'bluetooth-speaker',
                'price' => '649',
                'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=600&q=80'
            ]

        ];

        $relatedProducts = collect($relatedProducts)
            ->where('slug', '!=', $slug)
            ->take(4);

    @endphp


    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        @foreach($relatedProducts as $related)

            <a
                href="{{ route('buyer.product', ['slug' => $related['slug']]) }}"
                class="group bg-white rounded-xl border border-gray-200
                       overflow-hidden hover:-translate-y-1
                       hover:shadow-lg transition"
            >

                <div class="aspect-square overflow-hidden bg-gray-100">

                    <img
                        src="{{ $related['image'] }}"
                        alt="{{ $related['name'] }}"
                        class="w-full h-full object-cover
                               group-hover:scale-105 transition duration-300"
                    >

                </div>

                <div class="p-4">

                    <h3 class="text-sm font-medium line-clamp-2">
                        {{ $related['name'] }}
                    </h3>

                    <p class="text-lg font-bold text-[#1F6F5B] mt-2">
                        ₱{{ $related['price'] }}
                    </p>

                </div>

            </a>

        @endforeach

    </div>

</section>


{{-- PAGE SCRIPT --}}
<script>

    // ==========================================
    // CHANGE PRODUCT IMAGE
    // ==========================================

    function changeImage(button) {

        const image = button.dataset.image;

        document.getElementById('mainProductImage').src = image;

        document.querySelectorAll('.product-thumb').forEach(function (thumb) {

            thumb.classList.remove('border-[#1F6F5B]');
            thumb.classList.add('border-transparent');

        });

        button.classList.remove('border-transparent');
        button.classList.add('border-[#1F6F5B]');
    }


    // ==========================================
    // SELECT COLOR
    // ==========================================

    function selectColor(button) {

        document.querySelectorAll('.color-option').forEach(function (option) {

            option.classList.remove(
                'border-2',
                'border-[#1F6F5B]',
                'bg-[#F8FAF8]'
            );

            option.classList.add(
                'border',
                'border-gray-200'
            );

        });

        button.classList.remove(
            'border',
            'border-gray-200'
        );

        button.classList.add(
            'border-2',
            'border-[#1F6F5B]',
            'bg-[#F8FAF8]'
        );

        document.getElementById('selectedColor').value =
            button.dataset.color;
    }


    // ==========================================
    // QUANTITY
    // ==========================================

    let quantity = 1;

    const maxStock = {{ $product['stock'] }};


    function increaseQuantity() {

        if (quantity < maxStock) {

            quantity++;

            document.getElementById('quantity').textContent = quantity;

            document.getElementById('quantityInput').value = quantity;

        }

    }


    function decreaseQuantity() {

        if (quantity > 1) {

            quantity--;

            document.getElementById('quantity').textContent = quantity;

            document.getElementById('quantityInput').value = quantity;

        }

    }


    // ==========================================
    // BUY NOW
    // ==========================================

    function buyNow() {

        alert(
            "Proceeding to checkout for {{ $product['name'] }} x" +
            quantity
        );

    }

</script>

@endsection