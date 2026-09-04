@extends('layouts.rider')

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-[#1F2937]">
                Earnings
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Track your delivery earnings and completed deliveries.
            </p>
        </div>

        <a
            href="{{ route('rider.dashboard') }}"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
        >
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Back to Dashboard
        </a>

    </div>


    {{-- EARNINGS SUMMARY --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

        {{-- TOTAL EARNINGS --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6">

            <div class="flex items-center justify-between mb-4">

                <div>
                    <p class="text-sm text-gray-500">
                        Total Earnings
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-1">
                        ₱{{ number_format((float) $totalEarnings, 2) }}
                    </p>
                </div>

                <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center">
                    <i data-lucide="wallet" class="w-5 h-5 text-[#1F6F5B]"></i>
                </div>

            </div>

            <p class="text-xs text-gray-400">
                Based on completed deliveries
            </p>

        </div>


        {{-- COMPLETED DELIVERIES --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6">

            <div class="flex items-center justify-between mb-4">

                <div>
                    <p class="text-sm text-gray-500">
                        Completed Deliveries
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-1">
                        {{ $totalDeliveries }}
                    </p>
                </div>

                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                    <i data-lucide="package-check" class="w-5 h-5 text-blue-600"></i>
                </div>

            </div>

            <p class="text-xs text-gray-400">
                Delivered orders
            </p>

        </div>


        {{-- DELIVERY RATE --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6">

            <div class="flex items-center justify-between mb-4">

                <div>
                    <p class="text-sm text-gray-500">
                        Rate per Delivery
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-1">
                        ₱{{ number_format((float) $deliveryFee, 2) }}
                    </p>
                </div>

                <div class="w-11 h-11 rounded-xl bg-purple-50 flex items-center justify-center">
                    <i data-lucide="banknote" class="w-5 h-5 text-purple-600"></i>
                </div>

            </div>

            <p class="text-xs text-gray-400">
                Current demo delivery rate
            </p>

        </div>

    </div>


    {{-- DELIVERY HISTORY --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

        <div class="px-5 sm:px-6 py-5 border-b border-gray-100">

            <h2 class="text-lg font-semibold text-gray-900">
                Earnings History
            </h2>

            <p class="text-sm text-gray-500 mt-1">
                Your completed delivery transactions.
            </p>

        </div>


        @if(count($orders) > 0)

            <div class="divide-y divide-gray-100">

                @foreach($orders as $orderId => $order)

                    @php
                        $customerName =
                            $order['customer_name']
                            ?? $order['buyer_name']
                            ?? 'Customer';

                        $deliveredAt =
                            $order['delivered_at']
                            ?? $order['updated_at']
                            ?? null;
                    @endphp

                    <div class="p-5 sm:p-6">

                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                            {{-- ORDER --}}
                            <div class="flex items-start gap-3">

                                <div class="w-10 h-10 shrink-0 rounded-lg bg-green-50 flex items-center justify-center">
                                    <i
                                        data-lucide="package-check"
                                        class="w-5 h-5 text-[#1F6F5B]"
                                    ></i>
                                </div>

                                <div>

                                    <div class="flex flex-wrap items-center gap-2">

                                        <h3 class="font-semibold text-gray-900">
                                            Order #{{ $orderId }}
                                        </h3>

                                        <span class="px-2.5 py-1 rounded-full bg-green-50 text-green-700 border border-green-200 text-xs font-medium">
                                            Delivered
                                        </span>

                                    </div>

                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ $customerName }}
                                    </p>

                                    @if($deliveredAt)
                                        <p class="text-xs text-gray-400 mt-1">
                                            Delivered:
                                            {{ $deliveredAt }}
                                        </p>
                                    @endif

                                </div>

                            </div>


                            {{-- EARNING --}}
                            <div class="text-left sm:text-right">

                                <p class="text-xs text-gray-400 mb-1">
                                    Delivery Earnings
                                </p>

                                <p class="text-lg font-bold text-[#1F6F5B]">
                                    +₱{{ number_format((float) $deliveryFee, 2) }}
                                </p>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        @else

            {{-- EMPTY STATE --}}
            <div class="px-6 py-16 text-center">

                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-50 flex items-center justify-center">
                    <i
                        data-lucide="wallet-cards"
                        class="w-7 h-7 text-gray-400"
                    ></i>
                </div>

                <h3 class="text-base font-semibold text-gray-900 mb-1">
                    No earnings yet
                </h3>

                <p class="text-sm text-gray-500 max-w-md mx-auto">
                    Your earnings will appear here once you complete deliveries.
                </p>

            </div>

        @endif

    </div>

</div>

@endsection