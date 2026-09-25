@extends('layouts.app')

@section('content')

{{-- ========================================================= --}}
{{-- BREADCRUMBS --}}
{{-- ========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

    <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500 overflow-hidden">

        <a
            href="{{ route('buyer.home') }}"
            class="shrink-0 hover:text-[#1F6F5B] transition"
        >
            Home
        </a>

        <i data-lucide="chevron-right" class="w-4 h-4 shrink-0"></i>

        <a
            href="{{ route('buyer.shop') }}"
            class="shrink-0 hover:text-[#1F6F5B] transition"
        >
            Shop
        </a>

        <i data-lucide="chevron-right" class="w-4 h-4 shrink-0"></i>

        <span class="truncate text-gray-800 font-medium">
            {{ $product['name'] }}
        </span>

    </div>

</section>



{{-- ========================================================= --}}
{{-- PRODUCT MAIN --}}
{{-- ========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">

        <div class="grid lg:grid-cols-2">


            {{-- ================================================= --}}
            {{-- LEFT: PRODUCT IMAGES --}}
            {{-- ================================================= --}}

            <div class="p-5 sm:p-7 lg:p-8">

                {{-- MAIN IMAGE --}}
                <div class="relative aspect-square rounded-xl overflow-hidden bg-gray-100">

                    <img
                        id="mainProductImage"
                        src="{{ $product['image'] }}"
                        alt="{{ $product['name'] }}"
                        class="w-full h-full object-cover"
                    >


                    {{-- SALE BADGE --}}
                    @if(!empty($product['discount']))

                        <span class="absolute top-4 left-4 bg-[#F59E0B] text-white text-xs font-bold px-3 py-1.5 rounded-md shadow-sm">
                            SALE
                        </span>

                    @endif


                    {{-- FAVORITE --}}
                    <button
                        type="button"
                        class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/95 shadow-md flex items-center justify-center text-gray-500 hover:text-red-500 transition"
                    >

                        <i
                            data-lucide="heart"
                            class="w-5 h-5"
                        ></i>

                    </button>

                </div>



                {{-- THUMBNAILS --}}
                @if(!empty($product['images']))

                    <div class="grid grid-cols-4 gap-3 mt-4">

                        @foreach($product['images'] as $index => $image)

                            <button
                                type="button"
                                onclick="changeImage(this)"
                                data-image="{{ $image }}"
                                class="product-thumb aspect-square rounded-lg overflow-hidden border-2
                                {{ $index === 0
                                    ? 'border-[#1F6F5B]'
                                    : 'border-transparent hover:border-[#1F6F5B]'
                                }}"
                            >

                                <img
                                    src="{{ $image }}"
                                    alt="{{ $product['name'] }} image {{ $index + 1 }}"
                                    class="w-full h-full object-cover"
                                >

                            </button>

                        @endforeach

                    </div>

                @endif



                {{-- SHARE --}}
                <div class="flex items-center justify-between mt-5">

                    <p class="text-xs text-gray-400">
                        Share this product
                    </p>

                    <div class="flex items-center gap-2">

                        <button
                            type="button"
                            class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:text-[#1F6F5B] hover:border-[#1F6F5B] transition"
                        >
                            <i data-lucide="facebook" class="w-4 h-4"></i>
                        </button>

                        <button
                            type="button"
                            class="w-8 h-8 rounded-full border border-gray-200 flex items-center justify-center text-gray-500 hover:text-[#1F6F5B] hover:border-[#1F6F5B] transition"
                        >
                            <i data-lucide="link" class="w-4 h-4"></i>
                        </button>

                    </div>

                </div>

            </div>



            {{-- ================================================= --}}
            {{-- RIGHT: PRODUCT INFORMATION --}}
            {{-- ================================================= --}}

            <div class="p-5 sm:p-7 lg:p-10 border-t lg:border-t-0 lg:border-l border-gray-200">


                {{-- CATEGORY --}}
                <p class="text-xs sm:text-sm text-[#1F6F5B] font-semibold uppercase tracking-wide">

                    {{ $product['category'] }}

                </p>



                {{-- PRODUCT NAME --}}
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 leading-tight">

                    {{ $product['name'] }}

                </h1>



                {{-- RATING --}}
                <div class="flex flex-wrap items-center gap-3 mt-4">

                    <div class="flex items-center gap-1">

                        <i
                            data-lucide="star"
                            class="w-4 h-4 fill-[#F59E0B] text-[#F59E0B]"
                        ></i>

                        <span class="text-sm font-semibold text-gray-800">

                            {{ $product['rating'] }}

                        </span>

                    </div>


                    <span class="text-gray-300">
                        |
                    </span>


                    <span class="text-sm text-gray-500">

                        {{ $product['ratings'] }} Reviews

                    </span>


                    <span class="text-gray-300">
                        |
                    </span>


                    <span class="text-sm text-gray-500">

                        {{ $product['sold'] }} Sold

                    </span>

                </div>



                {{-- PRICE --}}
                <div class="mt-6 bg-[#F8FAF8] border border-[#E5F1EC] rounded-xl px-5 py-5">

                    <div class="flex flex-wrap items-center gap-3">

                        <span class="text-3xl font-bold text-[#1F6F5B]">

                            ₱{{ $product['price'] }}

                        </span>


                        @if(!empty($product['old_price']))

                            <span class="text-sm text-gray-400 line-through">

                                ₱{{ $product['old_price'] }}

                            </span>

                        @endif


                        @if(!empty($product['discount']))

                            <span class="text-xs font-bold bg-[#F59E0B] text-white px-2.5 py-1 rounded-md">

                                {{ $product['discount'] }} OFF

                            </span>

                        @endif

                    </div>


                    <p class="text-xs text-gray-400 mt-2">
                        Price may vary depending on selected variation.
                    </p>

                </div>



                {{-- SHIPPING --}}
                <div class="mt-6">

                    <div class="flex gap-3">

                        <div class="w-9 h-9 rounded-lg bg-[#DDF3EC] flex items-center justify-center shrink-0">

                            <i
                                data-lucide="truck"
                                class="w-4 h-4 text-[#1F6F5B]"
                            ></i>

                        </div>


                        <div>

                            <p class="text-sm font-semibold text-gray-800">
                                Delivery
                            </p>

                            <p class="text-sm text-gray-500 mt-1">
                                Ships from Laguna
                            </p>

                            <p class="text-xs text-gray-400 mt-1">
                                Available for nationwide delivery
                            </p>

                        </div>

                    </div>

                </div>



                {{-- DIVIDER --}}
                <div class="border-t border-gray-100 mt-6"></div>



                {{-- COLOR --}}
                @if(!empty($product['colors']))

                    <div class="mt-6">

                        <p class="text-sm font-semibold text-gray-800 mb-3">
                            Color
                        </p>


                        <div class="flex flex-wrap gap-2">

                            @foreach($product['colors'] as $index => $color)

                                <button
                                    type="button"
                                    onclick="selectColor(this)"
                                    class="color-option px-4 py-2.5 rounded-lg text-sm
                                    {{ $index === 0
                                        ? 'border-2 border-[#1F6F5B] bg-[#F8FAF8] text-[#1F6F5B] font-medium'
                                        : 'border border-gray-200 text-gray-600 hover:border-[#1F6F5B]'
                                    }}"
                                >

                                    {{ $color }}

                                </button>

                            @endforeach

                        </div>

                    </div>

                @endif



                {{-- SIZE --}}
                @if(!empty($product['sizes']))

                    <div class="mt-6">

                        <p class="text-sm font-semibold text-gray-800 mb-3">
                            Size
                        </p>


                        <div class="flex flex-wrap gap-2">

                            @foreach($product['sizes'] as $index => $size)

                                <button
                                    type="button"
                                    onclick="selectSize(this)"
                                    class="size-option px-4 py-2.5 rounded-lg text-sm
                                    {{ $index === 0
                                        ? 'border-2 border-[#1F6F5B] bg-[#F8FAF8] text-[#1F6F5B] font-medium'
                                        : 'border border-gray-200 text-gray-600 hover:border-[#1F6F5B]'
                                    }}"
                                >

                                    {{ $size }}

                                </button>

                            @endforeach

                        </div>

                    </div>

                @endif



                {{-- QUANTITY --}}
                <div class="mt-6">

                    <div class="flex items-center justify-between mb-3">

                        <p class="text-sm font-semibold text-gray-800">
                            Quantity
                        </p>

                        <span class="text-xs text-gray-400">

                            {{ $product['stock'] }} pieces available

                        </span>

                    </div>


                    <div class="flex items-center">

                        <button
                            type="button"
                            onclick="decreaseQuantity()"
                            class="w-10 h-10 border border-gray-200 rounded-l-lg flex items-center justify-center text-gray-600 hover:bg-gray-50"
                        >

                            <i
                                data-lucide="minus"
                                class="w-4 h-4"
                            ></i>

                        </button>


                        <div
                            id="quantity"
                            class="w-12 h-10 border-t border-b border-gray-200 flex items-center justify-center text-sm font-medium"
                        >
                            1
                        </div>


                        <button
                            type="button"
                            onclick="increaseQuantity()"
                            class="w-10 h-10 border border-gray-200 rounded-r-lg flex items-center justify-center text-gray-600 hover:bg-gray-50"
                        >

                            <i
                                data-lucide="plus"
                                class="w-4 h-4"
                            ></i>

                        </button>

                    </div>

                </div>



                {{-- ACTIONS --}}
                <div class="grid grid-cols-2 gap-3 mt-7">

                    <button
                        type="button"
                        onclick="addToCart()"
                        class="inline-flex items-center justify-center gap-2 border-2 border-[#1F6F5B] text-[#1F6F5B] py-3.5 rounded-xl text-sm font-semibold hover:bg-[#DDF3EC] transition"
                    >

                        <i
                            data-lucide="shopping-cart"
                            class="w-5 h-5"
                        ></i>

                        Add to Cart

                    </button>


                    <button
                        type="button"
                        onclick="buyNow()"
                        class="inline-flex items-center justify-center gap-2 bg-[#1F6F5B] text-white py-3.5 rounded-xl text-sm font-semibold hover:bg-[#155244] transition"
                    >

                        Buy Now

                        <i
                            data-lucide="arrow-right"
                            class="w-4 h-4"
                        ></i>

                    </button>

                </div>



                {{-- GUARANTEES --}}
                <div class="grid grid-cols-3 gap-2 sm:gap-4 mt-8 pt-6 border-t border-gray-100">

                    <div class="text-center">

                        <div class="w-9 h-9 mx-auto rounded-full bg-[#DDF3EC] flex items-center justify-center">

                            <i
                                data-lucide="shield-check"
                                class="w-4 h-4 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <p class="text-[10px] sm:text-[11px] text-gray-500 mt-2">
                            Secure Payment
                        </p>

                    </div>


                    <div class="text-center">

                        <div class="w-9 h-9 mx-auto rounded-full bg-[#DDF3EC] flex items-center justify-center">

                            <i
                                data-lucide="package-check"
                                class="w-4 h-4 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <p class="text-[10px] sm:text-[11px] text-gray-500 mt-2">
                            Quality Products
                        </p>

                    </div>


                    <div class="text-center">

                        <div class="w-9 h-9 mx-auto rounded-full bg-[#DDF3EC] flex items-center justify-center">

                            <i
                                data-lucide="rotate-ccw"
                                class="w-4 h-4 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <p class="text-[10px] sm:text-[11px] text-gray-500 mt-2">
                            Easy Returns
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>



{{-- ========================================================= --}}
{{-- SELLER / SHOP --}}
{{-- ========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">

    <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">


            {{-- SHOP INFO --}}
            <div class="flex items-center gap-4">

                <div class="w-14 h-14 rounded-xl bg-[#DDF3EC] flex items-center justify-center shrink-0">

                    <i
                        data-lucide="store"
                        class="w-6 h-6 text-[#1F6F5B]"
                    ></i>

                </div>


                <div>

                    <h2 class="font-semibold text-gray-900">

                        {{ $product['seller'] }}

                    </h2>


                    <p class="text-xs text-gray-400 mt-1">
                        Active 10 minutes ago
                    </p>


                    <div class="flex flex-wrap items-center gap-2 mt-2">

                        <span class="text-xs text-[#1F6F5B] font-medium">

                            {{ $product['seller_rating'] }} Positive Rating

                        </span>


                        <span class="text-gray-300">
                            |
                        </span>


                        <span class="text-xs text-gray-500">

                            {{ $product['seller_products'] }} Products

                        </span>

                    </div>

                </div>

            </div>



            {{-- SHOP ACTIONS --}}
            <div class="flex gap-2 sm:gap-3">

                <button
                    type="button"
                    class="flex-1 sm:flex-none px-5 py-2.5 rounded-lg border border-[#1F6F5B] text-[#1F6F5B] text-sm font-medium hover:bg-[#DDF3EC] transition"
                >
                    Visit Shop
                </button>


                <button
                    type="button"
                    class="flex-1 sm:flex-none px-5 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-medium hover:bg-[#155244] transition"
                >
                    Chat
                </button>

            </div>

        </div>

    </div>

</section>



{{-- ========================================================= --}}
{{-- DESCRIPTION + REVIEWS --}}
{{-- ========================================================= --}}

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">

    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">


        {{-- TAB HEADER --}}
        <div class="flex border-b border-gray-200 overflow-x-auto">

            <button
                type="button"
                class="shrink-0 px-6 py-4 text-sm font-semibold text-[#1F6F5B] border-b-2 border-[#1F6F5B]"
            >
                Product Details
            </button>


            <button
                type="button"
                class="shrink-0 px-6 py-4 text-sm text-gray-500 hover:text-[#1F6F5B]"
            >
                Ratings & Reviews
            </button>

        </div>



        {{-- DESCRIPTION --}}
        <div class="p-6 sm:p-8">

            <h2 class="font-bold text-lg text-gray-900">
                Product Description
            </h2>


            <p class="text-sm text-gray-600 leading-7 mt-4">
                {{ $product['description'] }}
            </p>


            @if(!empty($product['features']))

                <div class="mt-6">

                    <h3 class="text-sm font-semibold text-gray-900 mb-3">
                        Product Features
                    </h3>


                    <div class="grid sm:grid-cols-2 gap-3">

                        @foreach($product['features'] as $feature)

                            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#F8FAF8]">

                                <i
                                    data-lucide="check"
                                    class="w-4 h-4 text-[#1F6F5B] mt-0.5 shrink-0"
                                ></i>

                                <span class="text-sm text-gray-600">
                                    {{ $feature }}
                                </span>

                            </div>

                        @endforeach

                    </div>

                </div>

            @endif

        </div>

    </div>

</section>



{{-- ========================================================= --}}
{{-- JAVASCRIPT --}}
{{-- ========================================================= --}}

<script>

    /*
    |--------------------------------------------------------------------------
    | IMAGE GALLERY
    |--------------------------------------------------------------------------
    */

    function changeImage(button) {

        const image = button.dataset.image;

        document.getElementById('mainProductImage').src = image;


        document.querySelectorAll('.product-thumb').forEach(function (thumb) {

            thumb.classList.remove(
                'border-[#1F6F5B]'
            );

            thumb.classList.add(
                'border-transparent'
            );

        });


        button.classList.remove(
            'border-transparent'
        );

        button.classList.add(
            'border-[#1F6F5B]'
        );

    }



    /*
    |--------------------------------------------------------------------------
    | COLOR
    |--------------------------------------------------------------------------
    */

    function selectColor(button) {

        document.querySelectorAll('.color-option').forEach(function (option) {

            option.classList.remove(
                'border-2',
                'border-[#1F6F5B]',
                'bg-[#F8FAF8]',
                'text-[#1F6F5B]',
                'font-medium'
            );

            option.classList.add(
                'border',
                'border-gray-200',
                'text-gray-600'
            );

        });


        button.classList.remove(
            'border',
            'border-gray-200',
            'text-gray-600'
        );

        button.classList.add(
            'border-2',
            'border-[#1F6F5B]',
            'bg-[#F8FAF8]',
            'text-[#1F6F5B]',
            'font-medium'
        );

    }



    /*
    |--------------------------------------------------------------------------
    | SIZE
    |--------------------------------------------------------------------------
    */

    function selectSize(button) {

        document.querySelectorAll('.size-option').forEach(function (option) {

            option.classList.remove(
                'border-2',
                'border-[#1F6F5B]',
                'bg-[#F8FAF8]',
                'text-[#1F6F5B]',
                'font-medium'
            );

            option.classList.add(
                'border',
                'border-gray-200',
                'text-gray-600'
            );

        });


        button.classList.remove(
            'border',
            'border-gray-200',
            'text-gray-600'
        );

        button.classList.add(
            'border-2',
            'border-[#1F6F5B]',
            'bg-[#F8FAF8]',
            'text-[#1F6F5B]',
            'font-medium'
        );

    }



    /*
    |--------------------------------------------------------------------------
    | QUANTITY
    |--------------------------------------------------------------------------
    */

    let quantity = 1;

    const maxStock = {{ $product['stock'] }};


    function increaseQuantity() {

        if (quantity < maxStock) {

            quantity++;

            document.getElementById('quantity').textContent = quantity;

        }

    }


    function decreaseQuantity() {

        if (quantity > 1) {

            quantity--;

            document.getElementById('quantity').textContent = quantity;

        }

    }



    /*
    |--------------------------------------------------------------------------
    | ACTIONS
    |--------------------------------------------------------------------------
    */

    function addToCart() {

        alert(
            "{{ $product['name'] }} x" +
            quantity +
            " added to cart!"
        );

    }


    function buyNow() {

        alert(
            "Proceeding to checkout for {{ $product['name'] }} x" +
            quantity
        );

    }

</script>

@endsection