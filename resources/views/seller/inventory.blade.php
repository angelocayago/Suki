@extends('layouts.seller')

@section('title', 'Inventory')
@section('page-title', 'Inventory')

@section('content')

@php

    $products = $products ?? session('seller_products', []);

    $totalProducts = count($products);

    $inStock = collect($products)
        ->filter(function ($product) {
            return (int) ($product['stock'] ?? 0) > 10;
        })
        ->count();

    $lowStock = collect($products)
        ->filter(function ($product) {
            $stock = (int) ($product['stock'] ?? 0);

            return $stock >= 1 && $stock <= 10;
        })
        ->count();

    $outOfStock = collect($products)
        ->filter(function ($product) {
            return (int) ($product['stock'] ?? 0) <= 0;
        })
        ->count();

    $totalUnits = collect($products)
        ->sum(function ($product) {
            return max(
                0,
                (int) ($product['stock'] ?? 0)
            );
        });

@endphp


{{-- =========================================================
    SUCCESS MESSAGE
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

            <p
                class="text-xs
                       font-semibold
                       text-emerald-800"
            >
                Inventory updated
            </p>

            <p
                class="mt-0.5
                       text-xs
                       leading-5
                       text-emerald-700"
            >
                {{ session('success') }}
            </p>

        </div>

    </div>

@endif


{{-- =========================================================
    PAGE INTRODUCTION
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           sm:flex-row
           sm:items-end
           sm:justify-between"
>

    <div>

        {{-- BREADCRUMB --}}
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
                Inventory
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Inventory Management
        </h2>

        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Monitor product availability and adjust
            stock levels from one workspace.
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
    INVENTORY SUMMARY
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>


    {{-- TOTAL PRODUCTS --}}
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
            {{ $totalUnits }} total units available
        </p>

    </div>


    {{-- IN STOCK --}}
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
                    In Stock
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $inStock }}
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
                    data-lucide="package-check"
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
            More than 10 units available
        </p>

    </div>


    {{-- LOW STOCK --}}
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
                    Low Stock
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $lowStock }}
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
            Between 1 and 10 units
        </p>

    </div>


    {{-- OUT OF STOCK --}}
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
                    Out of Stock
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $outOfStock }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-red-50
                       text-red-600"
            >

                <i
                    data-lucide="package-x"
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
            Requires restocking
        </p>

    </div>

</div>


{{-- =========================================================
    INVENTORY PANEL
========================================================= --}}

<section
    class="overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>


    {{-- =====================================================
        PANEL HEADER
    ====================================================== --}}

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
                Stock Levels
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Search products and adjust available stock.
            </p>

        </div>


        <a
            href="{{ route('seller.products') }}"
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
                data-lucide="package-search"
                class="h-4 w-4"
            ></i>

            Product Management

        </a>

    </div>


    {{-- =====================================================
        FILTERS
    ====================================================== --}}

    <div
        class="border-b border-[#EDF1EF]
               bg-[#FBFCFB]
               p-4"
    >

        <div
            class="grid gap-3
                   lg:grid-cols-[minmax(0,1fr)_220px_200px_auto]"
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
                    id="inventorySearch"
                    placeholder="Search product name or SKU"
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


                    @foreach(
                        collect($products)
                            ->pluck('category')
                            ->filter()
                            ->unique()
                            ->sort()
                        as $category
                    )

                        <option
                            value="{{ strtolower($category) }}"
                        >
                            {{ $category }}
                        </option>

                    @endforeach

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


            {{-- STOCK FILTER --}}
            <div class="relative">

                <select
                    id="stockFilter"
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
                        All stock
                    </option>

                    <option value="in-stock">
                        In Stock
                    </option>

                    <option value="low-stock">
                        Low Stock
                    </option>

                    <option value="out-of-stock">
                        Out of Stock
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


            {{-- CLEAR --}}
            <button
                type="button"
                id="clearInventoryFilters"
                class="inline-flex h-10
                       items-center justify-center gap-2
                       rounded-xl
                       border border-[#DDE6E1]
                       bg-white
                       px-3.5
                       text-[11px]
                       font-semibold
                       text-[#68776F]
                       transition
                       hover:bg-[#F3F7F5]
                       hover:text-[#173F35]"
            >

                <i
                    data-lucide="rotate-ccw"
                    class="h-3.5 w-3.5"
                ></i>

                Clear

            </button>

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
                        SKU
                    </th>

                    <th class="px-5 py-3 text-left">
                        Price
                    </th>

                    <th class="px-5 py-3 text-center">
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


            <tbody id="inventoryTable">

                @forelse($products as $productId => $product)

                    @php

                        $resolvedProductId =
                            $product['id'] ?? $productId;

                        $stock =
                            (int) ($product['stock'] ?? 0);

                        if ($stock <= 0) {

                            $stockStatus =
                                'out-of-stock';

                            $stockLabel =
                                'Out of Stock';

                            $stockClass =
                                'border-red-200 bg-red-50 text-red-700';

                        } elseif ($stock <= 10) {

                            $stockStatus =
                                'low-stock';

                            $stockLabel =
                                'Low Stock';

                            $stockClass =
                                'border-amber-200 bg-amber-50 text-amber-700';

                        } else {

                            $stockStatus =
                                'in-stock';

                            $stockLabel =
                                'In Stock';

                            $stockClass =
                                'border-emerald-200 bg-emerald-50 text-emerald-700';

                        }

                    @endphp


                    <tr
                        class="inventory-row
                               border-b border-[#F0F3F1]
                               transition
                               last:border-b-0
                               hover:bg-[#FAFCFB]"
                        data-name="{{ strtolower(
                            ($product['name'] ?? '') .
                            ' ' .
                            ($product['sku'] ?? '')
                        ) }}"
                        data-category="{{ strtolower(
                            $product['category'] ?? ''
                        ) }}"
                        data-stock-status="{{ $stockStatus }}"
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
                                            class="h-5 w-5 text-[#87958E]"
                                        ></i>

                                    @endif

                                </div>


                                <div class="min-w-0">

                                    <p
                                        class="max-w-[230px]
                                               truncate
                                               text-xs
                                               font-semibold
                                               text-[#34483F]"
                                    >
                                        {{ $product['name'] ?? 'Unnamed Product' }}
                                    </p>

                                    <p
                                        class="mt-1
                                               text-[10px]
                                               text-[#8A9791]"
                                    >
                                        {{ $product['brand'] ?? 'No brand' }}
                                    </p>

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


                        {{-- SKU --}}
                        <td
                            class="px-5 py-4
                                   text-[11px]
                                   text-[#77857E]"
                        >
                            {{ $product['sku'] ?? '—' }}
                        </td>


                        {{-- PRICE --}}
                        <td
                            class="px-5 py-4
                                   text-xs
                                   font-semibold
                                   text-[#24312C]"
                        >
                            ₱{{ number_format(
                                (float) ($product['price'] ?? 0),
                                2
                            ) }}
                        </td>


                        {{-- STOCK CONTROL --}}
                        <td class="px-5 py-4">

                            <div
                                class="flex items-center
                                       justify-center gap-2"
                            >


                                {{-- DECREASE --}}
                                <form
                                    action="{{ route(
                                        'seller.inventory.decrease',
                                        $resolvedProductId
                                    ) }}"
                                    method="POST"
                                >

                                    @csrf


                                    <button
                                        type="submit"
                                        title="Decrease stock"
                                        {{ $stock <= 0 ? 'disabled' : '' }}
                                        class="flex h-8 w-8
                                               items-center justify-center
                                               rounded-lg
                                               border border-[#DDE6E1]
                                               bg-white
                                               text-[#68776F]
                                               transition
                                               hover:border-[#BFD2C9]
                                               hover:bg-[#EEF5F1]
                                               hover:text-[#173F35]
                                               disabled:cursor-not-allowed
                                               disabled:bg-[#F5F6F5]
                                               disabled:text-[#B6BFBA]"
                                    >

                                        <i
                                            data-lucide="minus"
                                            class="h-3.5 w-3.5"
                                        ></i>

                                    </button>

                                </form>


                                {{-- CURRENT STOCK --}}
                                <span
                                    class="min-w-[42px]
                                           text-center
                                           text-sm
                                           font-semibold
                                           text-[#24312C]"
                                >
                                    {{ $stock }}
                                </span>


                                {{-- INCREASE --}}
                                <form
                                    action="{{ route(
                                        'seller.inventory.increase',
                                        $resolvedProductId
                                    ) }}"
                                    method="POST"
                                >

                                    @csrf


                                    <button
                                        type="submit"
                                        title="Increase stock"
                                        class="flex h-8 w-8
                                               items-center justify-center
                                               rounded-lg
                                               border border-[#DDE6E1]
                                               bg-white
                                               text-[#68776F]
                                               transition
                                               hover:border-[#BFD2C9]
                                               hover:bg-[#EEF5F1]
                                               hover:text-[#173F35]"
                                    >

                                        <i
                                            data-lucide="plus"
                                            class="h-3.5 w-3.5"
                                        ></i>

                                    </button>

                                </form>

                            </div>

                        </td>


                        {{-- STATUS --}}
                        <td class="px-5 py-4">

                            <span
                                class="
                                    inline-flex
                                    items-center gap-1.5
                                    rounded-full
                                    border
                                    px-2.5 py-1
                                    text-[10px]
                                    font-semibold
                                    {{ $stockClass }}
                                "
                            >

                                <span
                                    class="
                                        h-1.5 w-1.5
                                        rounded-full

                                        {{ $stockStatus === 'in-stock'
                                            ? 'bg-emerald-500'
                                            : ($stockStatus === 'low-stock'
                                                ? 'bg-amber-500'
                                                : 'bg-red-500') }}
                                    "
                                ></span>

                                {{ $stockLabel }}

                            </span>

                        </td>


                        {{-- ACTION --}}
                        <td class="px-5 py-4">

                            <div
                                class="flex items-center
                                       justify-end gap-2"
                            >

                                <a
                                    href="{{ route(
                                        'seller.products.edit',
                                        $resolvedProductId
                                    ) }}"
                                    title="Edit product"
                                    class="inline-flex h-9
                                           items-center gap-2
                                           rounded-xl
                                           border border-[#DDE6E1]
                                           bg-white
                                           px-3
                                           text-[10px]
                                           font-semibold
                                           text-[#68776F]
                                           transition
                                           hover:border-[#BFD2C9]
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

                        </td>

                    </tr>


                @empty

                    <tr>

                        <td
                            colspan="7"
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
                                No inventory yet
                            </h3>


                            <p
                                class="mx-auto mt-1
                                       max-w-sm
                                       text-xs
                                       leading-5
                                       text-[#849089]"
                            >
                                Add your first product to begin
                                tracking stock levels.
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
        MOBILE INVENTORY
    ====================================================== --}}

    <div
        id="inventoryMobile"
        class="divide-y divide-[#EDF1EF] md:hidden"
    >

        @forelse($products as $productId => $product)

            @php

                $resolvedProductId =
                    $product['id'] ?? $productId;

                $stock =
                    (int) ($product['stock'] ?? 0);

                if ($stock <= 0) {

                    $stockStatus =
                        'out-of-stock';

                    $stockLabel =
                        'Out of Stock';

                    $stockClass =
                        'border-red-200 bg-red-50 text-red-700';

                } elseif ($stock <= 10) {

                    $stockStatus =
                        'low-stock';

                    $stockLabel =
                        'Low Stock';

                    $stockClass =
                        'border-amber-200 bg-amber-50 text-amber-700';

                } else {

                    $stockStatus =
                        'in-stock';

                    $stockLabel =
                        'In Stock';

                    $stockClass =
                        'border-emerald-200 bg-emerald-50 text-emerald-700';

                }

            @endphp


            <article
                class="inventory-mobile-row p-4"
                data-name="{{ strtolower(
                    ($product['name'] ?? '') .
                    ' ' .
                    ($product['sku'] ?? '')
                ) }}"
                data-category="{{ strtolower(
                    $product['category'] ?? ''
                ) }}"
                data-stock-status="{{ $stockStatus }}"
            >

                <div class="flex items-start gap-3">


                    {{-- IMAGE --}}
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
                                class="h-5 w-5 text-[#87958E]"
                            ></i>

                        @endif

                    </div>


                    <div class="min-w-0 flex-1">

                        {{-- NAME --}}
                        <div
                            class="flex items-start
                                   justify-between gap-3"
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
                                           text-[#8A9791]"
                                >
                                    {{ $product['category'] ?? 'Uncategorized' }}

                                    @if(!empty($product['sku']))
                                        · {{ $product['sku'] }}
                                    @endif
                                </p>

                            </div>


                            <span
                                class="
                                    shrink-0
                                    rounded-full
                                    border
                                    px-2 py-1
                                    text-[9px]
                                    font-semibold
                                    {{ $stockClass }}
                                "
                            >
                                {{ $stockLabel }}
                            </span>

                        </div>


                        {{-- PRICE --}}
                        <p
                            class="mt-3
                                   text-sm
                                   font-semibold
                                   text-[#24312C]"
                        >
                            ₱{{ number_format(
                                (float) ($product['price'] ?? 0),
                                2
                            ) }}
                        </p>

                    </div>

                </div>


                {{-- STOCK CONTROLS --}}
                <div
                    class="mt-4
                           flex items-center
                           justify-between
                           rounded-xl
                           bg-[#F7F9F8]
                           px-3 py-3"
                >

                    <div>

                        <p
                            class="text-[9px]
                                   font-semibold
                                   uppercase
                                   tracking-[0.1em]
                                   text-[#93A099]"
                        >
                            Available Stock
                        </p>

                        <p
                            class="mt-1
                                   text-lg
                                   font-semibold
                                   text-[#24312C]"
                        >
                            {{ $stock }}
                        </p>

                    </div>


                    <div class="flex items-center gap-2">


                        {{-- DECREASE --}}
                        <form
                            action="{{ route(
                                'seller.inventory.decrease',
                                $resolvedProductId
                            ) }}"
                            method="POST"
                        >

                            @csrf


                            <button
                                type="submit"
                                title="Decrease stock"
                                {{ $stock <= 0 ? 'disabled' : '' }}
                                class="flex h-9 w-9
                                       items-center justify-center
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       text-[#68776F]
                                       transition
                                       hover:bg-[#EEF5F1]
                                       hover:text-[#173F35]
                                       disabled:cursor-not-allowed
                                       disabled:bg-[#F1F3F2]
                                       disabled:text-[#B6BFBA]"
                            >

                                <i
                                    data-lucide="minus"
                                    class="h-4 w-4"
                                ></i>

                            </button>

                        </form>


                        {{-- INCREASE --}}
                        <form
                            action="{{ route(
                                'seller.inventory.increase',
                                $resolvedProductId
                            ) }}"
                            method="POST"
                        >

                            @csrf


                            <button
                                type="submit"
                                title="Increase stock"
                                class="flex h-9 w-9
                                       items-center justify-center
                                       rounded-xl
                                       bg-[#173F35]
                                       text-white
                                       transition
                                       hover:bg-[#1F6F5B]"
                            >

                                <i
                                    data-lucide="plus"
                                    class="h-4 w-4"
                                ></i>

                            </button>

                        </form>

                    </div>

                </div>


                {{-- EDIT --}}
                <a
                    href="{{ route(
                        'seller.products.edit',
                        $resolvedProductId
                    ) }}"
                    class="mt-3
                           inline-flex h-9
                           w-full
                           items-center
                           justify-center gap-2
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
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

                    Edit Product Details

                </a>

            </article>


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
                    No inventory yet
                </p>


                <p
                    class="mt-1
                           text-xs
                           text-[#849089]"
                >
                    Add a product to begin managing stock.
                </p>

            </div>

        @endforelse

    </div>


    {{-- =====================================================
        NO FILTER RESULTS
    ====================================================== --}}

    <div
        id="noInventoryResults"
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
            No matching inventory
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
            id="noResultsClear"
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

</section>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const searchInput =
            document.getElementById(
                'inventorySearch'
            );

        const categoryFilter =
            document.getElementById(
                'categoryFilter'
            );

        const stockFilter =
            document.getElementById(
                'stockFilter'
            );

        const clearButton =
            document.getElementById(
                'clearInventoryFilters'
            );

        const noResultsClear =
            document.getElementById(
                'noResultsClear'
            );

        const desktopRows =
            Array.from(
                document.querySelectorAll(
                    '.inventory-row'
                )
            );

        const mobileRows =
            Array.from(
                document.querySelectorAll(
                    '.inventory-mobile-row'
                )
            );

        const noResults =
            document.getElementById(
                'noInventoryResults'
            );


        function matchesInventory(
            element,
            search,
            category,
            stock
        ) {

            const name =
                (
                    element.dataset.name || ''
                ).toLowerCase();

            const rowCategory =
                (
                    element.dataset.category || ''
                ).toLowerCase();

            const rowStock =
                element.dataset.stockStatus || '';


            const matchesSearch =
                search === '' ||
                name.includes(search);

            const matchesCategory =
                category === 'all' ||
                rowCategory === category;

            const matchesStock =
                stock === 'all' ||
                rowStock === stock;


            return (
                matchesSearch &&
                matchesCategory &&
                matchesStock
            );

        }


        function filterInventory() {

            const search =
                (
                    searchInput?.value || ''
                )
                .toLowerCase()
                .trim();

            const category =
                (
                    categoryFilter?.value ||
                    'all'
                ).toLowerCase();

            const stock =
                stockFilter?.value || 'all';


            let visibleProducts = 0;


            desktopRows.forEach(
                function (row) {

                    const visible =
                        matchesInventory(
                            row,
                            search,
                            category,
                            stock
                        );

                    row.classList.toggle(
                        'hidden',
                        !visible
                    );

                    if (visible) {
                        visibleProducts++;
                    }

                }
            );


            mobileRows.forEach(
                function (row) {

                    const visible =
                        matchesInventory(
                            row,
                            search,
                            category,
                            stock
                        );

                    row.classList.toggle(
                        'hidden',
                        !visible
                    );

                }
            );


            const hasProducts =
                desktopRows.length > 0 ||
                mobileRows.length > 0;


            if (noResults) {

                noResults.classList.toggle(
                    'hidden',
                    !hasProducts ||
                    visibleProducts > 0
                );

            }

        }


        function clearFilters() {

            if (searchInput) {
                searchInput.value = '';
            }

            if (categoryFilter) {
                categoryFilter.value = 'all';
            }

            if (stockFilter) {
                stockFilter.value = 'all';
            }

            filterInventory();

        }


        searchInput?.addEventListener(
            'input',
            filterInventory
        );


        categoryFilter?.addEventListener(
            'change',
            filterInventory
        );


        stockFilter?.addEventListener(
            'change',
            filterInventory
        );


        clearButton?.addEventListener(
            'click',
            clearFilters
        );


        noResultsClear?.addEventListener(
            'click',
            clearFilters
        );


        if (
            typeof lucide !== 'undefined' &&
            typeof lucide.createIcons ===
                'function'
        ) {

            lucide.createIcons();

        }

    }
);

</script>

@endpush

@endsection