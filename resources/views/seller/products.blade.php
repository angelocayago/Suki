@extends('layouts.seller')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')

@php

    $products = $products ?? session('seller_products', []);

    $totalProducts = $paginator->total();

    $activeProducts = collect($filteredSummary)
        ->where('status', 'active')
        ->count();

    $inactiveProducts = collect($filteredSummary)
        ->where('status', 'inactive')
        ->count();

    $lowStockProducts = collect($filteredSummary)
        ->where('stock_status', 'low-stock')
        ->count();

    $outOfStockProducts = collect($filteredSummary)
        ->where('stock_status', 'out-of-stock')
        ->count();

@endphp

@if(!empty(session('seller_products_legacy_review')))
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-xs leading-5 text-amber-800" role="status">
        Earlier listings need an ownership review before they can appear here. Contact support before ending this session.
    </div>
@endif


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
            All matching listings
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
            Active matching listings
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
            Inactive matching listings
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


@include('seller.partials.product-listings')
@endsection
