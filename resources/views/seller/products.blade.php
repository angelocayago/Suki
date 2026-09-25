@extends('layouts.seller')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')

@php

    $products = $products ?? session('seller_products', []);

    $totalProducts = count($products);

    $activeProducts = collect($products)
        ->where('status', 'active')
        ->count();

    $inactiveProducts = collect($products)
        ->where('status', 'inactive')
        ->count();

    $lowStockProducts = collect($products)
        ->filter(function ($product) {
            $stock = (int) ($product['stock'] ?? 0);

            return $stock > 0 && $stock <= 10;
        })
        ->count();

    $outOfStockProducts = collect($products)
        ->filter(function ($product) {
            return (int) ($product['stock'] ?? 0) <= 0;
        })
        ->count();

@endphp


{{-- =========================================================
    ALERT
========================================================= --}}

@if(session('success'))

    <div
        class="mb-6
               flex items-start gap-3
               rounded-2xl
               border border-emerald-200
               bg-emerald-50
               px-4 py-3.5"
    >

        <div
            class="flex h-8 w-8
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-white"
        >
            <i
                data-lucide="check"
                class="h-4 w-4 text-emerald-600"
            ></i>
        </div>

        <div>
            <p class="text-xs font-semibold text-emerald-800">
                Product updated
            </p>

            <p class="mt-0.5 text-xs leading-5 text-emerald-700">
                {{ session('success') }}
            </p>
        </div>

    </div>

@endif


{{-- =========================================================
    PAGE INTRO
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           sm:flex-row
           sm:items-end
           sm:justify-between"
>

    <div>

        <div
            class="mb-2
                   flex items-center gap-2
                   text-[11px]
                   font-medium
                   text-[#8A9791]"
        >

            <a
                href="{{ route('seller.dashboard') }}"
                class="transition hover:text-[#1F6F5B]"
            >
                Dashboard
            </a>

            <i
                data-lucide="chevron-right"
                class="h-3 w-3"
            ></i>

            <span class="text-[#52635B]">
                Products
            </span>

        </div>

        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Product Management
        </h2>

        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Manage your product listings, pricing, availability,
            and inventory from one workspace.
        </p>

    </div>


    <a
        href="{{ route('seller.products.create') }}"
        class="inline-flex h-10
               items-center justify-center gap-2
               self-start
               rounded-xl
               bg-[#173F35]
               px-4
               text-xs
               font-semibold
               text-white
               transition
               hover:bg-[#1F6F5B]
               sm:self-auto"
    >

        <i
            data-lucide="plus"
            class="h-4 w-4"
        ></i>

        Add Product

    </a>

</div>


{{-- =========================================================
    SUMMARY CARDS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>

    {{-- TOTAL --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-4">

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Total Products
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $totalProducts }}
                </p>

            </div>

            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#EEF5F1]
                       text-[#173F35]"
            >
                <i
                    data-lucide="package"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>

        </div>

        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[11px]
                   text-[#7B8982]"
        >
            All store listings
        </p>

    </div>


    {{-- ACTIVE --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-4">

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Active
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $activeProducts }}
                </p>

            </div>

            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-emerald-50
                       text-emerald-700"
            >
                <i
                    data-lucide="circle-check"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>

        </div>

        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[11px]
                   text-[#7B8982]"
        >
            Visible products
        </p>

    </div>


    {{-- INACTIVE --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-4">

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Inactive
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $inactiveProducts }}
                </p>

            </div>

            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#F1F4F2]
                       text-[#68776F]"
            >
                <i
                    data-lucide="eye-off"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>

        </div>

        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[11px]
                   text-[#7B8982]"
        >
            Hidden listings
        </p>

    </div>


    {{-- STOCK --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-4">

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Stock Attention
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $lowStockProducts + $outOfStockProducts }}
                </p>

            </div>

            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-amber-50
                       text-amber-700"
            >
                <i
                    data-lucide="triangle-alert"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>

        </div>

        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[11px]
                   text-[#7B8982]"
        >
            {{ $outOfStockProducts }} out of stock
        </p>

    </div>

</div>


{{-- =========================================================
    PRODUCT MANAGEMENT
========================================================= --}}

<div
    class="overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>


    {{-- CARD HEADER --}}
    <div
        class="flex flex-col gap-4
               border-b border-[#EDF1EF]
               px-5 py-5
               lg:flex-row
               lg:items-center
               lg:justify-between"
    >

        <div>

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Product Listings
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Search, filter, and manage products in your store.
            </p>

        </div>


        <a
            href="{{ route('seller.inventory') }}"
            class="inline-flex h-9
                   items-center justify-center gap-2
                   self-start
                   rounded-xl
                   border border-[#DDE6E1]
                   bg-white
                   px-3.5
                   text-[11px]
                   font-semibold
                   text-[#52635B]
                   transition
                   hover:border-[#BFD2C9]
                   hover:bg-[#F8FAF8]
                   hover:text-[#173F35]
                   lg:self-auto"
        >

            <i
                data-lucide="boxes"
                class="h-4 w-4"
            ></i>

            Manage Inventory

        </a>

    </div>


    {{-- FILTERS --}}
    <div
        class="border-b border-[#EDF1EF]
               bg-[#FBFCFB]
               p-4"
    >

        <div
            class="grid gap-3
                   lg:grid-cols-[minmax(0,1fr)_180px_220px]"
        >


            {{-- SEARCH --}}
            <div class="relative">

                <i
                    data-lucide="search"
                    class="pointer-events-none
                           absolute left-3.5 top-1/2
                           h-4 w-4
                           -translate-y-1/2
                           text-[#91A099]"
                ></i>

                <input
                    type="text"
                    id="productSearch"
                    placeholder="Search by product name or SKU"
                    class="h-10 w-full
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           pl-10 pr-4
                           text-xs
                           text-[#34483F]
                           placeholder:text-[#9AA69F]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >

            </div>


            {{-- STATUS --}}
            <div class="relative">

                <select
                    id="statusFilter"
                    class="h-10 w-full
                           appearance-none
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           px-3 pr-9
                           text-xs
                           font-medium
                           text-[#52635B]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >
                    <option value="all">
                        All status
                    </option>

                    <option value="active">
                        Active
                    </option>

                    <option value="inactive">
                        Inactive
                    </option>
                </select>

                <i
                    data-lucide="chevron-down"
                    class="pointer-events-none
                           absolute right-3 top-1/2
                           h-3.5 w-3.5
                           -translate-y-1/2
                           text-[#87958E]"
                ></i>

            </div>


            {{-- CATEGORY --}}
            <div class="relative">

                <select
                    id="categoryFilter"
                    class="h-10 w-full
                           appearance-none
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           px-3 pr-9
                           text-xs
                           font-medium
                           text-[#52635B]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >

                    <option value="all">
                        All categories
                    </option>

                    <option value="Electronics">
                        Electronics
                    </option>

                    <option value="Fashion">
                        Fashion
                    </option>

                    <option value="Beauty">
                        Beauty
                    </option>

                    <option value="Home & Living">
                        Home & Living
                    </option>

                    <option value="Groceries">
                        Groceries
                    </option>

                    <option value="Health">
                        Health
                    </option>

                    <option value="Sports">
                        Sports
                    </option>

                    <option value="Toys & Hobbies">
                        Toys & Hobbies
                    </option>

                    <option value="Automotive">
                        Automotive
                    </option>

                    <option value="Pet Supplies">
                        Pet Supplies
                    </option>

                    <option value="Books & Stationery">
                        Books & Stationery
                    </option>

                    <option value="Others">
                        Others
                    </option>

                </select>

                <i
                    data-lucide="chevron-down"
                    class="pointer-events-none
                           absolute right-3 top-1/2
                           h-3.5 w-3.5
                           -translate-y-1/2
                           text-[#87958E]"
                ></i>

            </div>

        </div>

    </div>


    {{-- =====================================================
        DESKTOP TABLE
    ====================================================== --}}

    <div class="hidden overflow-x-auto md:block">

        <table class="w-full">

            <thead>

                <tr
                    class="border-b
                           border-[#EDF1EF]
                           bg-[#F7F9F8]"
                >

                    <th class="px-5 py-3 text-left">
                        Product
                    </th>

                    <th class="px-5 py-3 text-left">
                        Category
                    </th>

                    <th class="px-5 py-3 text-left">
                        Price
                    </th>

                    <th class="px-5 py-3 text-left">
                        Stock
                    </th>

                    <th class="px-5 py-3 text-left">
                        Status
                    </th>

                    <th class="px-5 py-3 text-right">
                        Action
                    </th>

                </tr>

            </thead>


            <tbody id="productTable">

                @forelse($products as $productId => $product)

                    @php

                        $stock = (int) ($product['stock'] ?? 0);

                        $productStatus =
                            $product['status'] ?? 'active';

                        $resolvedProductId =
                            $product['id'] ?? $productId;

                        if ($stock <= 0) {

                            $stockLabel = 'Out of stock';

                            $stockClass =
                                'bg-red-50 text-red-700 border-red-200';

                        } elseif ($stock <= 10) {

                            $stockLabel = $stock . ' left';

                            $stockClass =
                                'bg-amber-50 text-amber-700 border-amber-200';

                        } else {

                            $stockLabel = $stock . ' in stock';

                            $stockClass =
                                'bg-[#F1F5F3] text-[#52635B] border-[#E0E7E3]';

                        }

                    @endphp


                    <tr
                        class="product-row
                               border-b border-[#F0F3F1]
                               transition
                               last:border-b-0
                               hover:bg-[#FAFCFB]"
                        data-name="{{ strtolower(($product['name'] ?? '') . ' ' . ($product['sku'] ?? '')) }}"
                        data-status="{{ $productStatus }}"
                        data-category="{{ $product['category'] ?? '' }}"
                    >


                        {{-- PRODUCT --}}
                        <td class="px-5 py-4">

                            <div class="flex items-center gap-3">

                                <div
                                    class="flex h-12 w-12
                                           shrink-0
                                           items-center justify-center
                                           overflow-hidden
                                           rounded-xl
                                           border border-[#E5EBE7]
                                           bg-[#F1F5F3]"
                                >

                                    @if(!empty($product['image']))

                                        <img
                                            src="{{ $product['image'] }}"
                                            alt="{{ $product['name'] ?? 'Product' }}"
                                            class="h-full w-full object-cover"
                                        >

                                    @else

                                        <i
                                            data-lucide="package"
                                            class="h-5 w-5 text-[#85958D]"
                                        ></i>

                                    @endif

                                </div>


                                <div class="min-w-0">

                                    <p
                                        class="max-w-[260px]
                                               truncate
                                               text-xs
                                               font-semibold
                                               text-[#34483F]"
                                    >
                                        {{ $product['name'] ?? 'Unnamed Product' }}
                                    </p>


                                    @if(!empty($product['sku']))

                                        <p
                                            class="mt-1
                                                   text-[10px]
                                                   font-medium
                                                   text-[#929E98]"
                                        >
                                            SKU:
                                            {{ $product['sku'] }}
                                        </p>

                                    @else

                                        <p
                                            class="mt-1
                                                   text-[10px]
                                                   text-[#A0AAA5]"
                                        >
                                            No SKU
                                        </p>

                                    @endif

                                </div>

                            </div>

                        </td>


                        {{-- CATEGORY --}}
                        <td class="px-5 py-4">

                            <span
                                class="inline-flex
                                       rounded-lg
                                       bg-[#F3F6F4]
                                       px-2.5 py-1
                                       text-[10px]
                                       font-medium
                                       text-[#65746D]"
                            >
                                {{ $product['category'] ?? 'Uncategorized' }}
                            </span>

                        </td>


                        {{-- PRICE --}}
                        <td class="px-5 py-4">

                            <p
                                class="text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                            </p>

                        </td>


                        {{-- STOCK --}}
                        <td class="px-5 py-4">

                            <span
                                class="
                                    inline-flex
                                    rounded-full
                                    border
                                    px-2.5 py-1
                                    text-[10px]
                                    font-semibold
                                    {{ $stockClass }}
                                "
                            >
                                {{ $stockLabel }}
                            </span>

                        </td>


                        {{-- STATUS --}}
                        <td class="px-5 py-4">

                            @if($productStatus === 'active')

                                <span
                                    class="inline-flex
                                           items-center gap-1.5
                                           rounded-full
                                           border border-emerald-200
                                           bg-emerald-50
                                           px-2.5 py-1
                                           text-[10px]
                                           font-semibold
                                           text-emerald-700"
                                >

                                    <span
                                        class="h-1.5 w-1.5
                                               rounded-full
                                               bg-emerald-500"
                                    ></span>

                                    Active

                                </span>

                            @else

                                <span
                                    class="inline-flex
                                           items-center gap-1.5
                                           rounded-full
                                           border border-[#DFE5E2]
                                           bg-[#F2F4F3]
                                           px-2.5 py-1
                                           text-[10px]
                                           font-semibold
                                           text-[#68766F]"
                                >

                                    <span
                                        class="h-1.5 w-1.5
                                               rounded-full
                                               bg-[#94A099]"
                                    ></span>

                                    Inactive

                                </span>

                            @endif

                        </td>


                        {{-- ACTION --}}
                        <td class="px-5 py-4">

                            <div
                                class="flex items-center
                                       justify-end gap-2"
                            >

                                <a
                                    href="{{ route('seller.products.edit', $resolvedProductId) }}"
                                    title="Edit product"
                                    class="inline-flex
                                           h-9 w-9
                                           items-center justify-center
                                           rounded-xl
                                           border border-[#DDE6E1]
                                           bg-white
                                           text-[#68776F]
                                           transition
                                           hover:border-[#BFD2C9]
                                           hover:bg-[#EEF5F1]
                                           hover:text-[#173F35]"
                                >
                                    <i
                                        data-lucide="pencil"
                                        class="h-4 w-4"
                                    ></i>
                                </a>

                            </div>

                        </td>

                    </tr>


                @empty

                    <tr id="emptyProductRow">

                        <td
                            colspan="6"
                            class="px-6 py-16 text-center"
                        >

                            <div
                                class="mx-auto
                                       flex h-12 w-12
                                       items-center justify-center
                                       rounded-2xl
                                       bg-[#EEF5F1]
                                       text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="package-open"
                                    class="h-5 w-5"
                                ></i>
                            </div>

                            <h3
                                class="mt-4
                                       text-sm
                                       font-semibold
                                       text-[#34483F]"
                            >
                                No products yet
                            </h3>

                            <p
                                class="mx-auto mt-1
                                       max-w-sm
                                       text-xs
                                       leading-5
                                       text-[#849089]"
                            >
                                Start building your store by creating
                                your first product listing.
                            </p>

                            <a
                                href="{{ route('seller.products.create') }}"
                                class="mt-4
                                       inline-flex h-9
                                       items-center gap-2
                                       rounded-xl
                                       bg-[#173F35]
                                       px-4
                                       text-[11px]
                                       font-semibold
                                       text-white
                                       transition
                                       hover:bg-[#1F6F5B]"
                            >

                                <i
                                    data-lucide="plus"
                                    class="h-4 w-4"
                                ></i>

                                Add Product

                            </a>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- =====================================================
        MOBILE CARDS
    ====================================================== --}}

    <div
        id="mobileProducts"
        class="divide-y divide-[#EDF1EF] md:hidden"
    >

        @forelse($products as $productId => $product)

            @php

                $stock =
                    (int) ($product['stock'] ?? 0);

                $productStatus =
                    $product['status'] ?? 'active';

                $resolvedProductId =
                    $product['id'] ?? $productId;

                if ($stock <= 0) {

                    $stockLabel = 'Out of stock';

                    $mobileStockClass =
                        'text-red-600';

                } elseif ($stock <= 10) {

                    $stockLabel =
                        $stock . ' left';

                    $mobileStockClass =
                        'text-amber-700';

                } else {

                    $stockLabel =
                        $stock . ' in stock';

                    $mobileStockClass =
                        'text-[#68776F]';

                }

            @endphp


            <div
                class="mobile-product
                       p-4"
                data-name="{{ strtolower(($product['name'] ?? '') . ' ' . ($product['sku'] ?? '')) }}"
                data-status="{{ $productStatus }}"
                data-category="{{ $product['category'] ?? '' }}"
            >

                <div class="flex items-start gap-3">


                    {{-- ICON --}}
                    <div
                        class="flex h-14 w-14
                               shrink-0
                               items-center justify-center
                               overflow-hidden
                               rounded-xl
                               border border-[#E5EBE7]
                               bg-[#F1F5F3]"
                    >

                        @if(!empty($product['image']))

                            <img
                                src="{{ $product['image'] }}"
                                alt="{{ $product['name'] ?? 'Product' }}"
                                class="h-full w-full object-cover"
                            >

                        @else

                            <i
                                data-lucide="package"
                                class="h-5 w-5 text-[#85958D]"
                            ></i>

                        @endif

                    </div>


                    <div class="min-w-0 flex-1">

                        <div
                            class="flex
                                   items-start
                                   justify-between
                                   gap-3"
                        >

                            <div class="min-w-0">

                                <h3
                                    class="truncate
                                           text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    {{ $product['name'] ?? 'Unnamed Product' }}
                                </h3>

                                <p
                                    class="mt-1
                                           truncate
                                           text-[10px]
                                           text-[#89968F]"
                                >
                                    {{ $product['category'] ?? 'Uncategorized' }}

                                    @if(!empty($product['sku']))
                                        · {{ $product['sku'] }}
                                    @endif
                                </p>

                            </div>


                            @if($productStatus === 'active')

                                <span
                                    class="shrink-0
                                           rounded-full
                                           border border-emerald-200
                                           bg-emerald-50
                                           px-2 py-1
                                           text-[9px]
                                           font-semibold
                                           text-emerald-700"
                                >
                                    Active
                                </span>

                            @else

                                <span
                                    class="shrink-0
                                           rounded-full
                                           border border-[#DFE5E2]
                                           bg-[#F2F4F3]
                                           px-2 py-1
                                           text-[9px]
                                           font-semibold
                                           text-[#68766F]"
                                >
                                    Inactive
                                </span>

                            @endif

                        </div>


                        <div
                            class="mt-4
                                   flex items-end
                                   justify-between gap-4"
                        >

                            <div>

                                <p
                                    class="text-sm
                                           font-semibold
                                           text-[#24312C]"
                                >
                                    ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                                </p>

                                <p
                                    class="mt-0.5
                                           text-[10px]
                                           font-medium
                                           {{ $mobileStockClass }}"
                                >
                                    {{ $stockLabel }}
                                </p>

                            </div>


                            <a
                                href="{{ route('seller.products.edit', $resolvedProductId) }}"
                                class="inline-flex h-9
                                       items-center gap-2
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       px-3
                                       text-[10px]
                                       font-semibold
                                       text-[#52635B]
                                       transition
                                       hover:bg-[#EEF5F1]
                                       hover:text-[#173F35]"
                            >

                                <i
                                    data-lucide="pencil"
                                    class="h-3.5 w-3.5"
                                ></i>

                                Edit

                            </a>

                        </div>

                    </div>

                </div>

            </div>


        @empty

            <div class="px-6 py-14 text-center">

                <div
                    class="mx-auto
                           flex h-12 w-12
                           items-center justify-center
                           rounded-2xl
                           bg-[#EEF5F1]
                           text-[#1F6F5B]"
                >
                    <i
                        data-lucide="package-open"
                        class="h-5 w-5"
                    ></i>
                </div>

                <p
                    class="mt-4
                           text-sm
                           font-semibold
                           text-[#34483F]"
                >
                    No products yet
                </p>

                <p
                    class="mt-1
                           text-xs
                           text-[#849089]"
                >
                    Add your first product to get started.
                </p>

                <a
                    href="{{ route('seller.products.create') }}"
                    class="mt-4
                           inline-flex h-9
                           items-center gap-2
                           rounded-xl
                           bg-[#173F35]
                           px-4
                           text-[11px]
                           font-semibold
                           text-white"
                >
                    <i
                        data-lucide="plus"
                        class="h-4 w-4"
                    ></i>

                    Add Product
                </a>

            </div>

        @endforelse

    </div>


    {{-- =====================================================
        NO FILTER RESULTS
    ====================================================== --}}

    <div
        id="noResults"
        class="hidden
               px-6 py-14
               text-center"
    >

        <div
            class="mx-auto
                   flex h-12 w-12
                   items-center justify-center
                   rounded-2xl
                   bg-[#F1F4F2]
                   text-[#87958E]"
        >
            <i
                data-lucide="search-x"
                class="h-5 w-5"
            ></i>
        </div>

        <h3
            class="mt-4
                   text-sm
                   font-semibold
                   text-[#34483F]"
        >
            No matching products
        </h3>

        <p
            class="mt-1
                   text-xs
                   text-[#849089]"
        >
            Try another keyword or change your filters.
        </p>

        <button
            type="button"
            id="clearProductFilters"
            class="mt-4
                   inline-flex h-9
                   items-center gap-2
                   rounded-xl
                   border border-[#DDE6E1]
                   bg-white
                   px-4
                   text-[11px]
                   font-semibold
                   text-[#52635B]
                   transition
                   hover:bg-[#F5F8F6]
                   hover:text-[#173F35]"
        >
            <i
                data-lucide="rotate-ccw"
                class="h-3.5 w-3.5"
            ></i>

            Clear filters
        </button>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('productSearch');

    const statusFilter =
        document.getElementById('statusFilter');

    const categoryFilter =
        document.getElementById('categoryFilter');

    const clearFiltersButton =
        document.getElementById('clearProductFilters');

    const desktopRows =
        Array.from(
            document.querySelectorAll('.product-row')
        );

    const mobileProducts =
        Array.from(
            document.querySelectorAll('.mobile-product')
        );

    const noResults =
        document.getElementById('noResults');


    function matchesProduct(
        element,
        search,
        status,
        category
    ) {

        const name =
            (element.dataset.name || '')
                .toLowerCase();

        const itemStatus =
            element.dataset.status || '';

        const itemCategory =
            element.dataset.category || '';


        const matchesSearch =
            search === '' ||
            name.includes(search);

        const matchesStatus =
            status === 'all' ||
            itemStatus === status;

        const matchesCategory =
            category === 'all' ||
            itemCategory === category;


        return (
            matchesSearch &&
            matchesStatus &&
            matchesCategory
        );

    }


    function filterProducts() {

        const search =
            (searchInput?.value || '')
                .toLowerCase()
                .trim();

        const status =
            statusFilter?.value || 'all';

        const category =
            categoryFilter?.value || 'all';


        let desktopVisible = 0;
        let mobileVisible = 0;


        desktopRows.forEach(function (row) {

            const visible =
                matchesProduct(
                    row,
                    search,
                    status,
                    category
                );

            row.style.display =
                visible ? '' : 'none';

            if (visible) {
                desktopVisible++;
            }

        });


        mobileProducts.forEach(function (card) {

            const visible =
                matchesProduct(
                    card,
                    search,
                    status,
                    category
                );

            card.style.display =
                visible ? '' : 'none';

            if (visible) {
                mobileVisible++;
            }

        });


        const hasProducts =
            desktopRows.length > 0 ||
            mobileProducts.length > 0;

        const hasVisibleProducts =
            desktopVisible > 0 ||
            mobileVisible > 0;


        if (noResults) {

            noResults.classList.toggle(
                'hidden',
                !hasProducts || hasVisibleProducts
            );

        }

    }


    searchInput?.addEventListener(
        'input',
        filterProducts
    );

    statusFilter?.addEventListener(
        'change',
        filterProducts
    );

    categoryFilter?.addEventListener(
        'change',
        filterProducts
    );


    clearFiltersButton?.addEventListener(
        'click',
        function () {

            if (searchInput) {
                searchInput.value = '';
            }

            if (statusFilter) {
                statusFilter.value = 'all';
            }

            if (categoryFilter) {
                categoryFilter.value = 'all';
            }

            filterProducts();

        }
    );


    if (
        typeof lucide !== 'undefined' &&
        typeof lucide.createIcons === 'function'
    ) {
        lucide.createIcons();
    }

});

</script>

@endpush


@endsection