@extends('layouts.app')

@section('content')

@php
    $subtotal = collect($cart)->sum(function ($item) {
        return $item['price'] * $item['quantity'];
    });

    $shipping = 30;
    $total = $subtotal + $shipping;
@endphp

<div class="min-h-screen bg-[#F8FAF8]">

    {{-- PAGE HEADER --}}
    <div class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

            <div class="flex items-center gap-3">

                <a
                    href="{{ route('buyer.cart') }}"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-[#EEF8F3] hover:text-[#1F6F5B]"
                >
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                </a>

                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Checkout
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        Review your order before placing it.
                    </p>
                </div>

            </div>

        </div>
    </div>


    {{-- CHECKOUT CONTENT --}}
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">

        {{-- ERROR MESSAGE --}}
        @if(session('error'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                <i data-lucide="circle-alert" class="mt-0.5 h-5 w-5 shrink-0"></i>

                <span>
                    {{ session('error') }}
                </span>
            </div>
        @endif


        {{-- VALIDATION ERRORS --}}
        @if($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4">

                <div class="flex items-start gap-3">

                    <i data-lucide="circle-alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-600"></i>

                    <div>
                        <p class="font-semibold text-red-700">
                            Please check the following:
                        </p>

                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-600">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>

                </div>

            </div>
        @endif


        <form action="{{ route('buyer.checkout.place-order') }}" method="POST">
            @csrf

            <div class="grid gap-6 lg:grid-cols-3">


                {{-- ========================================================= --}}
                {{-- LEFT SIDE --}}
                {{-- ========================================================= --}}

                <div class="space-y-6 lg:col-span-2">


                    {{-- ===================================================== --}}
                    {{-- DELIVERY ADDRESS --}}
                    {{-- ===================================================== --}}

                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">

                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-5 sm:px-6">

                            <div class="flex items-center gap-3">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">
                                    <i data-lucide="map-pin" class="h-5 w-5 text-[#1F6F5B]"></i>
                                </div>

                                <div>
                                    <h2 class="font-semibold text-gray-900">
                                        Delivery Address
                                    </h2>

                                    <p class="text-xs text-gray-500">
                                        Where should we deliver your order?
                                    </p>
                                </div>

                            </div>

                            <a
                                href="{{ route('buyer.addresses') }}"
                                class="text-sm font-medium text-[#1F6F5B] hover:underline"
                            >
                                Manage
                            </a>

                        </div>


                        <div class="px-5 py-5 sm:px-6">

                            @if(!empty($addresses))

                                <div class="space-y-3">

                                    @foreach($addresses as $address)

                                        @php
                                            $isSelected = $defaultAddress
                                                && (string) $defaultAddress['id'] === (string) $address['id'];
                                        @endphp

                                        <label class="block cursor-pointer">

                                            <div
                                                class="address-option rounded-xl border-2 p-4 transition
                                                {{ $isSelected
                                                    ? 'border-[#1F6F5B] bg-[#F8FAF8]'
                                                    : 'border-gray-200 hover:border-[#1F6F5B]' }}"
                                            >

                                                <div class="flex items-start gap-4">

                                                    <input
                                                        type="radio"
                                                        name="address_id"
                                                        value="{{ $address['id'] }}"
                                                        {{ $isSelected ? 'checked' : '' }}
                                                        class="mt-1 h-4 w-4 accent-[#1F6F5B]"
                                                    >

                                                    <div class="min-w-0 flex-1">

                                                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">

                                                            <span class="font-semibold text-gray-900">
                                                                {{ $address['name'] }}
                                                            </span>

                                                            <span class="text-sm text-gray-500">
                                                                {{ $address['phone'] }}
                                                            </span>

                                                            @if(!empty($address['is_default']))
                                                                <span class="rounded-md bg-[#DDF3EC] px-2.5 py-1 text-xs font-medium text-[#1F6F5B]">
                                                                    Default
                                                                </span>
                                                            @endif

                                                        </div>

                                                        <p class="mt-2 text-sm leading-6 text-gray-600">

                                                            @if(!empty($address['house_number']))
                                                                {{ $address['house_number'] }},
                                                            @endif

                                                            {{ $address['street'] }},
                                                            Barangay {{ $address['barangay'] }},
                                                            {{ $address['municipality'] }},
                                                            {{ $address['province'] }},
                                                            {{ $address['postal_code'] }}

                                                        </p>

                                                        @if(!empty($address['label']))
                                                            <p class="mt-2 text-xs font-medium text-gray-500">
                                                                {{ $address['label'] }}
                                                            </p>
                                                        @endif

                                                    </div>

                                                </div>

                                            </div>

                                        </label>

                                    @endforeach

                                </div>


                                <div class="mt-4">

                                    <a
                                        href="{{ route('buyer.addresses') }}"
                                        class="inline-flex items-center gap-2 text-sm font-medium text-[#1F6F5B] hover:underline"
                                    >
                                        <i data-lucide="plus" class="h-4 w-4"></i>
                                        Add or manage addresses
                                    </a>

                                </div>

                            @else

                                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center">

                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                                        <i data-lucide="map-pin-off" class="h-6 w-6 text-gray-400"></i>
                                    </div>

                                    <h3 class="mt-3 font-semibold text-gray-900">
                                        No delivery address
                                    </h3>

                                    <p class="mt-1 text-sm text-gray-500">
                                        Please add an address before placing your order.
                                    </p>

                                    <a
                                        href="{{ route('buyer.addresses') }}"
                                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#1F6F5B] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#155244]"
                                    >
                                        <i data-lucide="plus" class="h-4 w-4"></i>
                                        Add Address
                                    </a>

                                </div>

                            @endif

                        </div>

                    </section>



                    {{-- ===================================================== --}}
                    {{-- YOUR ORDER --}}
                    {{-- ===================================================== --}}

                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">

                        <div class="border-b border-gray-100 px-5 py-5 sm:px-6">

                            <div class="flex items-center justify-between">

                                <div>

                                    <h2 class="font-semibold text-gray-900">
                                        Your Order
                                    </h2>

                                    <p class="mt-1 text-xs text-gray-500">
                                        Review the items you're purchasing.
                                    </p>

                                </div>

                                <span class="text-sm text-gray-500">
                                    {{ count($cart) }}
                                    item{{ count($cart) !== 1 ? 's' : '' }}
                                </span>

                            </div>

                        </div>


                        <div class="divide-y divide-gray-100">

                            @foreach($cart as $slug => $item)

                                <div class="flex gap-4 px-5 py-5 sm:px-6">

                                    <img
                                        src="{{ $item['image'] }}"
                                        alt="{{ $item['name'] }}"
                                        class="h-20 w-20 shrink-0 rounded-xl object-cover sm:h-24 sm:w-24"
                                    >

                                    <div class="min-w-0 flex-1">

                                        <h3 class="line-clamp-2 font-medium text-gray-900">
                                            {{ $item['name'] }}
                                        </h3>

                                        <p class="mt-1 text-sm text-gray-500">
                                            ₱{{ number_format($item['price'], 2) }} each
                                        </p>

                                        <p class="mt-1 text-sm text-gray-500">
                                            Qty: {{ $item['quantity'] }}
                                        </p>

                                    </div>

                                    <div class="shrink-0 text-right">

                                        <p class="font-semibold text-gray-900">
                                            ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                        </p>

                                    </div>

                                </div>

                            @endforeach

                        </div>

                    </section>



{{-- ===================================================== --}}
{{-- SUKI LOGISTICS DELIVERY --}}
{{-- ===================================================== --}}

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">

    <div class="border-b border-gray-100 px-5 py-5 sm:px-6">

        <div class="flex items-center gap-3">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">
                <i
                    data-lucide="truck"
                    class="h-5 w-5 text-[#1F6F5B]"
                ></i>
            </div>

            <div>

                <h2 class="font-semibold text-gray-900">
                    Delivery
                </h2>

                <p class="text-xs text-gray-500">
                    Your order will be handled by SUKI Logistics.
                </p>

            </div>

        </div>

    </div>


    <div class="px-5 py-5 sm:px-6">

        <div class="rounded-xl border-2 border-[#1F6F5B] bg-[#F8FAF8] p-4">

            <div class="flex items-start gap-4">

                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-[#DDF3EC] bg-white">

                    <i
                        data-lucide="package-check"
                        class="h-5 w-5 text-[#1F6F5B]"
                    ></i>

                </div>


                <div class="min-w-0 flex-1">

                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">

                        <div>

                            <p class="font-semibold text-gray-900">
                                SUKI Logistics
                            </p>

                            <p class="mt-0.5 text-xs leading-5 text-gray-500">
                                SUKI Logistics will process your parcel,
                                sort it at the logistics center, and assign
                                an approved SUKI Rider for delivery.
                            </p>

                        </div>


                        <div class="mt-2 shrink-0 sm:mt-0">

                            @if($shipping > 0)

                                <span class="font-semibold text-gray-900">
                                    ₱{{ number_format($shipping, 2) }}
                                </span>

                            @else

                                <span class="inline-flex rounded-full bg-[#DDF3EC] px-3 py-1 text-xs font-semibold text-[#1F6F5B]">
                                    No delivery fee
                                </span>

                            @endif

                        </div>

                    </div>


                    <div class="mt-4 flex items-start gap-2 border-t border-[#DDF3EC] pt-4">

                        <i
                            data-lucide="route"
                            class="mt-0.5 h-4 w-4 shrink-0 text-[#1F6F5B]"
                        ></i>

                        <p class="text-xs leading-5 text-gray-500">
                            Seller preparation → Rider pickup →
                            SUKI Sorting Center → Rider assignment →
                            Delivery to buyer
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


                    {{-- ===================================================== --}}
                    {{-- PAYMENT METHOD --}}
                    {{-- ===================================================== --}}

                    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">

                        <div class="border-b border-gray-100 px-5 py-5 sm:px-6">

                            <div class="flex items-center gap-3">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">
                                    <i data-lucide="credit-card" class="h-5 w-5 text-[#1F6F5B]"></i>
                                </div>

                                <div>
                                    <h2 class="font-semibold text-gray-900">
                                        Payment Method
                                    </h2>

                                    <p class="text-xs text-gray-500">
                                        Select how you want to pay for your order.
                                    </p>
                                </div>

                            </div>

                        </div>


                        <div class="space-y-3 px-5 py-5 sm:px-6">


                            {{-- COD --}}
                            <label class="block cursor-pointer">

                                <div class="payment-option rounded-xl border-2 border-[#1F6F5B] bg-[#F8FAF8] p-4 transition">

                                    <div class="flex items-center gap-4">

                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="cod"
                                            checked
                                            class="payment-radio h-4 w-4 accent-[#1F6F5B]"
                                        >

                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white">
                                            <i data-lucide="banknote" class="h-5 w-5 text-[#1F6F5B]"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">

                                            <p class="font-semibold text-gray-900">
                                                Cash on Delivery
                                            </p>

                                            <p class="mt-0.5 text-xs text-gray-500">
                                                Pay when your order arrives.
                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </label>


                            {{-- GCASH --}}
                            <label class="block cursor-pointer">

                                class="payment-option rounded-xl border-2 border-gray-200 bg-white p-4 transition hover:border-[#1F6F5B]"

                                    <div class="flex items-center gap-4">

                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="gcash"
                                            class="payment-radio h-4 w-4 accent-[#1F6F5B]"
                                        >

                                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white">
                                            <i data-lucide="smartphone" class="h-5 w-5 text-gray-600"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">

                                            <p class="font-semibold text-gray-900">
                                                GCash
                                            </p>

                                            <p class="mt-0.5 text-xs text-gray-500">
                                                Pay through GCash.
                                            </p>

                                        </div>

                                    </div>


                                    {{-- GCASH DETAILS --}}
                                    <div class="gcash-details mt-4 hidden rounded-lg border border-[#DDF3EC] bg-[#EEF8F3] px-4 py-3">

                                        <div class="flex items-start gap-3">

                                            <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-[#1F6F5B]"></i>

                                            <div class="text-xs text-gray-600">

                                                <p class="font-medium text-gray-800">
                                                    SUKI SHOP Official GCash
                                                </p>

                                                <p class="mt-1">
                                                    09XX XXX XXXX
                                                </p>

                                                <p class="mt-2 text-gray-500">
                                                    Send the exact order total and keep your payment receipt.
                                                </p>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </label>

                        </div>

                    </section>

                </div>



                {{-- ========================================================= --}}
                {{-- RIGHT SIDE — ORDER SUMMARY --}}
                {{-- ========================================================= --}}

                <aside class="lg:col-span-1">

                    <div class="sticky top-24 overflow-hidden rounded-2xl border border-gray-200 bg-white">

                        <div class="border-b border-gray-100 px-5 py-5 sm:px-6">

                            <h2 class="font-semibold text-gray-900">
                                Order Summary
                            </h2>

                        </div>


                        <div class="space-y-5 px-5 py-5 sm:px-6">

                            {{-- SUBTOTAL --}}
                            <div class="flex items-center justify-between text-sm">

                                <span class="text-gray-500">
                                    Merchandise Subtotal
                                </span>

                                <span class="font-medium text-gray-900">
                                    ₱{{ number_format($subtotal, 2) }}
                                </span>

                            </div>


                            {{-- SUKI LOGISTICS DELIVERY --}}
<div class="flex items-center justify-between gap-4 text-sm">

    <span class="text-gray-500">
        SUKI Logistics Delivery
    </span>

    <span class="font-medium text-gray-900">

        @if($shipping > 0)

            ₱{{ number_format($shipping, 2) }}

        @else

            Free

        @endif

    </span>

</div>


                            <div class="border-t border-gray-100 pt-5">

                                <div class="flex items-end justify-between">

                                    <div>
                                        <p class="text-sm text-gray-500">
                                            Total
                                        </p>

                                        <p class="mt-1 text-xs text-gray-400">
    Delivery handled by SUKI Logistics
</p>
                                    </div>

                                    <span
                                        id="order-total"
                                        class="text-2xl font-bold text-[#1F6F5B]"
                                    >
                                        ₱{{ number_format($total, 2) }}
                                    </span>

                                </div>

                            </div>


                            {{-- PLACE ORDER --}}
                            <button
                                type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244] focus:outline-none focus:ring-2 focus:ring-[#1F6F5B] focus:ring-offset-2"
                            >

                                <i data-lucide="shopping-bag" class="h-5 w-5"></i>

                                Place Order

                            </button>


                            {{-- TERMS --}}
                            <p class="text-center text-xs leading-5 text-gray-400">
                                By placing your order, you agree to SUKI SHOP's
                                <span class="font-medium text-gray-500">
                                    Terms & Conditions
                                </span>
                                and
                                <span class="font-medium text-gray-500">
                                    Privacy Policy
                                </span>.
                            </p>

                        </div>

                    </div>

                </aside>

            </div>

        </form>

    </div>

</div>

@endsection


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    /* =========================================================
       PAYMENT METHOD
    ========================================================= */

    const paymentRadios =
        document.querySelectorAll(
            '.payment-radio'
        );

    const paymentOptions =
        document.querySelectorAll(
            '.payment-option'
        );

    const gcashDetails =
        document.querySelector(
            '.gcash-details'
        );


    function updatePayment() {

        paymentOptions.forEach(function (option) {

            option.classList.remove(
                'border-[#1F6F5B]',
                'bg-[#F8FAF8]'
            );

            option.classList.add(
                'border-gray-200',
                'bg-white'
            );

        });


        paymentRadios.forEach(function (radio) {

            if (radio.checked) {

                const option =
                    radio.closest(
                        '.payment-option'
                    );


                if (!option) {
                    return;
                }


                option.classList.remove(
                    'border-gray-200',
                    'bg-white'
                );


                option.classList.add(
                    'border-[#1F6F5B]',
                    'bg-[#F8FAF8]'
                );

            }

        });


        const selectedPayment =
            document.querySelector(
                'input[name="payment_method"]:checked'
            );


        if (!gcashDetails) {
            return;
        }


        if (
            selectedPayment
            &&
            selectedPayment.value === 'gcash'
        ) {

            gcashDetails.classList.remove(
                'hidden'
            );

        } else {

            gcashDetails.classList.add(
                'hidden'
            );

        }

    }


    paymentRadios.forEach(function (radio) {

        radio.addEventListener(
            'change',
            updatePayment
        );

    });


    /* =========================================================
       ADDRESS SELECTION
    ========================================================= */

    const addressRadios =
        document.querySelectorAll(
            'input[name="address_id"]'
        );


    const addressOptions =
        document.querySelectorAll(
            '.address-option'
        );


    addressRadios.forEach(function (radio) {

        radio.addEventListener(
            'change',
            function () {

                addressOptions.forEach(
                    function (option) {

                        option.classList.remove(
                            'border-[#1F6F5B]',
                            'bg-[#F8FAF8]'
                        );


                        option.classList.add(
                            'border-gray-200',
                            'bg-white'
                        );

                    }
                );


                if (radio.checked) {

                    const option =
                        radio.closest(
                            '.address-option'
                        );


                    if (!option) {
                        return;
                    }


                    option.classList.remove(
                        'border-gray-200',
                        'bg-white'
                    );


                    option.classList.add(
                        'border-[#1F6F5B]',
                        'bg-[#F8FAF8]'
                    );

                }

            }
        );

    });


    /* =========================================================
       INITIAL STATE
    ========================================================= */

    updatePayment();

});

</script>

@endpush