@extends('layouts.app')

@section('content')

@php
    $paymentMethod = strtoupper($order['payment_method'] ?? 'COD');
    $shippingMethod = strtoupper($order['shipping_method'] ?? 'J&T');
@endphp

<div class="min-h-screen bg-[#F8FAF8]">

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">

        {{-- SUCCESS HEADER --}}
        <div class="text-center">

            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#DDF3EC]">
                <i
                    data-lucide="check"
                    class="h-8 w-8 text-[#1F6F5B]"
                ></i>
            </div>

            <h1 class="mt-5 text-2xl font-bold text-gray-900 sm:text-3xl">
                Order Placed Successfully!
            </h1>

            <p class="mt-2 text-sm text-gray-500 sm:text-base">
                Thank you for shopping with SUKI.
            </p>

        </div>


        {{-- MAIN CARD --}}
        <div class="mt-8 overflow-hidden rounded-2xl border border-gray-200 bg-white">


            {{-- ORDER CONFIRMATION --}}
            <div class="border-b border-gray-100 px-5 py-6 text-center sm:px-8">

                <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                    Order Number
                </p>

                <div class="mt-2 flex items-center justify-center gap-2">

                    <span
                        id="order-number"
                        class="text-lg font-bold text-[#1F6F5B]"
                    >
                        {{ $order['id'] }}
                    </span>

                    <button
                        type="button"
                        onclick="copyOrderNumber()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-[#EEF8F3] hover:text-[#1F6F5B]"
                        title="Copy order number"
                    >
                        <i
                            data-lucide="copy"
                            class="h-4 w-4"
                        ></i>
                    </button>

                </div>

                <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-[#DDF3EC] px-4 py-2">

                    <i
                        data-lucide="package-check"
                        class="h-4 w-4 text-[#1F6F5B]"
                    ></i>

                    <span class="text-sm font-medium text-[#1F6F5B]">
                        Order Placed
                    </span>

                </div>

            </div>


            {{-- ORDER DETAILS --}}
            <div class="px-5 py-6 sm:px-8">

                <h2 class="font-semibold text-gray-900">
                    Order Details
                </h2>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">


                    {{-- TOTAL --}}
                    <div class="rounded-xl border border-gray-100 bg-[#F8FAF8] p-4">

                        <div class="flex items-center gap-3">

                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white">
                                <i
                                    data-lucide="wallet"
                                    class="h-4 w-4 text-[#1F6F5B]"
                                ></i>
                            </div>

                            <div>

                                <p class="text-xs text-gray-500">
                                    Total Amount
                                </p>

                                <p class="mt-0.5 font-bold text-gray-900">
                                    ₱{{ number_format($order['total'], 2) }}
                                </p>

                            </div>

                        </div>

                    </div>


                    {{-- PAYMENT --}}
                    <div class="rounded-xl border border-gray-100 bg-[#F8FAF8] p-4">

                        <div class="flex items-center gap-3">

                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white">
                                <i
                                    data-lucide="credit-card"
                                    class="h-4 w-4 text-[#1F6F5B]"
                                ></i>
                            </div>

                            <div>

                                <p class="text-xs text-gray-500">
                                    Payment Method
                                </p>

                                <p class="mt-0.5 font-semibold text-gray-900">
                                    {{ $paymentMethod }}
                                </p>

                            </div>

                        </div>

                    </div>


                    {{-- SHIPPING --}}
                    <div class="rounded-xl border border-gray-100 bg-[#F8FAF8] p-4">

                        <div class="flex items-center gap-3">

                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white">
                                <i
                                    data-lucide="truck"
                                    class="h-4 w-4 text-[#1F6F5B]"
                                ></i>
                            </div>

                            <div>

                                <p class="text-xs text-gray-500">
                                    Shipping Method
                                </p>

                                <p class="mt-0.5 font-semibold text-gray-900">
                                    {{ $shippingMethod }}
                                </p>

                            </div>

                        </div>

                    </div>


                    {{-- STATUS --}}
                    <div class="rounded-xl border border-gray-100 bg-[#F8FAF8] p-4">

                        <div class="flex items-center gap-3">

                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white">
                                <i
                                    data-lucide="clock-3"
                                    class="h-4 w-4 text-[#1F6F5B]"
                                ></i>
                            </div>

                            <div>

                                <p class="text-xs text-gray-500">
                                    Current Status
                                </p>

                                <p class="mt-0.5 font-semibold text-[#1F6F5B]">
                                    Order Placed
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            {{-- WHAT HAPPENS NEXT --}}
            <div class="border-t border-gray-100 bg-[#EEF8F3] px-5 py-5 sm:px-8">

                <div class="flex items-start gap-3">

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white">
                        <i
                            data-lucide="info"
                            class="h-4 w-4 text-[#1F6F5B]"
                        ></i>
                    </div>

                    <div>

                        <p class="text-sm font-semibold text-gray-900">
                            What happens next?
                        </p>

                        <p class="mt-1 text-xs leading-5 text-gray-600">
                            The seller will start preparing your order. You can
                            monitor its progress anytime from your order details.
                        </p>

                    </div>

                </div>

            </div>


            {{-- ACTIONS --}}
            <div class="border-t border-gray-100 px-5 py-6 sm:px-8">

                <div class="grid gap-3 sm:grid-cols-2">


                    {{-- VIEW ORDER --}}
                    <a
                        href="{{ route('buyer.order-details', $order['id']) }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244]"
                    >

                        <i
                            data-lucide="package"
                            class="h-5 w-5"
                        ></i>

                        View Order Details

                    </a>


                    {{-- MY ORDERS --}}
                    <a
                        href="{{ route('buyer.my-orders') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                    >

                        <i
                            data-lucide="clipboard-list"
                            class="h-5 w-5"
                        ></i>

                        My Orders

                    </a>


                </div>


                {{-- CONTINUE SHOPPING --}}
                <a
                    href="{{ route('buyer.shop') }}"
                    class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                >

                    <i
                        data-lucide="shopping-bag"
                        class="h-5 w-5"
                    ></i>

                    Continue Shopping

                </a>

            </div>

        </div>


        {{-- FOOTNOTE --}}
        <p class="mt-5 text-center text-xs leading-5 text-gray-400">
            Keep your order number for future reference.
            You can track your order through
            <span class="font-medium text-gray-500">
                My Orders
            </span>.
        </p>

    </div>

</div>

@endsection


@push('scripts')

<script>

function copyOrderNumber() {

    const orderNumber =
        document.getElementById('order-number').textContent.trim();

    navigator.clipboard.writeText(orderNumber).then(function () {

        const button =
            document.querySelector('[onclick="copyOrderNumber()"]');

        const icon =
            button.querySelector('i');

        icon.setAttribute('data-lucide', 'check');

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        setTimeout(function () {

            icon.setAttribute('data-lucide', 'copy');

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

        }, 1500);

    });

}

</script>

@endpush