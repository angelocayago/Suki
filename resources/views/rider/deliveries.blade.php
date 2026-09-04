@extends('layouts.rider')

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

        <div>
            <p class="text-sm font-medium text-[#1F6F5B] mb-1">
                SUKI Logistics
            </p>

            <h1 class="text-2xl sm:text-3xl font-bold text-[#1F2937]">
                My Deliveries
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Manage your assigned pickup and delivery orders.
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


    {{-- FLASH MESSAGES --}}
    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif


    {{-- DELIVERY STATS --}}
    @php
        $readyCount = collect($orders)->where('status', 'ready_for_pickup')->count();

        $pickedUpCount = collect($orders)->whereIn('status', [
            'picked_up',
            'at_sorting_center'
        ])->count();

        $deliveryCount = collect($orders)->whereIn('status', [
            'assigned_to_rider',
            'out_for_delivery'
        ])->count();

        $completedCount = collect($orders)->where('status', 'delivered')->count();
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-gray-500">Ready for Pickup</p>
                <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
                    <i data-lucide="package-check" class="w-4 h-4 text-amber-600"></i>
                </div>
            </div>

            <p class="text-2xl font-bold text-gray-900">
                {{ $readyCount }}
            </p>
        </div>


        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-gray-500">Picked Up</p>
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                    <i data-lucide="package" class="w-4 h-4 text-blue-600"></i>
                </div>
            </div>

            <p class="text-2xl font-bold text-gray-900">
                {{ $pickedUpCount }}
            </p>
        </div>


        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-gray-500">For Delivery</p>
                <div class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center">
                    <i data-lucide="truck" class="w-4 h-4 text-purple-600"></i>
                </div>
            </div>

            <p class="text-2xl font-bold text-gray-900">
                {{ $deliveryCount }}
            </p>
        </div>


        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-gray-500">Delivered</p>
                <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center">
                    <i data-lucide="circle-check" class="w-4 h-4 text-[#1F6F5B]"></i>
                </div>
            </div>

            <p class="text-2xl font-bold text-gray-900">
                {{ $completedCount }}
            </p>
        </div>

    </div>


    {{-- ORDERS --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

        <div class="px-5 sm:px-6 py-5 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">
                Delivery Orders
            </h2>

            <p class="text-sm text-gray-500 mt-1">
                Orders currently moving through the SUKI delivery process.
            </p>
        </div>


        @if(count($orders) > 0)

            <div class="divide-y divide-gray-100">

                @foreach($orders as $orderId => $order)

                    @php
                        $status = $order['status'] ?? 'placed';

                        $statusLabels = [
                            'ready_for_pickup' => 'Ready for Pickup',
                            'picked_up' => 'Picked Up',
                            'at_sorting_center' => 'At Sorting Center',
                            'sorted' => 'Sorted',
                            'assigned_to_rider' => 'Assigned to Rider',
                            'out_for_delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered',
                            'delivery_failed' => 'Delivery Failed',
                        ];

                        $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));

                        $statusClasses = [
                            'ready_for_pickup' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'picked_up' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'at_sorting_center' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'sorted' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'assigned_to_rider' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                            'out_for_delivery' => 'bg-orange-50 text-orange-700 border-orange-200',
                            'delivered' => 'bg-green-50 text-green-700 border-green-200',
                            'delivery_failed' => 'bg-red-50 text-red-700 border-red-200',
                        ];

                        $statusClass = $statusClasses[$status] ?? 'bg-gray-50 text-gray-600 border-gray-200';

                        $customerName =
                            $order['customer_name']
                            ?? $order['buyer_name']
                            ?? 'Customer';

                        $address =
                            $order['shipping_address']
                            ?? $order['address']
                            ?? 'Delivery address unavailable';

                        $total =
                            $order['total']
                            ?? $order['grand_total']
                            ?? 0;
                    @endphp


                    <div class="p-5 sm:p-6">

                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">

                            {{-- ORDER INFO --}}
                            <div class="flex-1">

                                <div class="flex flex-wrap items-center gap-2 mb-3">

                                    <span class="font-semibold text-gray-900">
                                        #{{ $orderId }}
                                    </span>

                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-medium {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>

                                </div>


                                <div class="space-y-2 text-sm">

                                    <div class="flex items-start gap-2 text-gray-600">
                                        <i data-lucide="user" class="w-4 h-4 mt-0.5 text-gray-400"></i>

                                        <span>
                                            {{ $customerName }}
                                        </span>
                                    </div>


                                    <div class="flex items-start gap-2 text-gray-600">
                                        <i data-lucide="map-pin" class="w-4 h-4 mt-0.5 text-gray-400"></i>

                                        <span>
                                            {{ $address }}
                                        </span>
                                    </div>


                                    <div class="flex items-center gap-2 text-gray-600">
                                        <i data-lucide="wallet" class="w-4 h-4 text-gray-400"></i>

                                        <span>
                                            Order Total:
                                            <strong class="text-gray-900">
                                                ₱{{ number_format((float) $total, 2) }}
                                            </strong>
                                        </span>
                                    </div>

                                </div>

                            </div>


                            {{-- ACTION --}}
                            <div class="w-full lg:w-auto">

                                @if($status === 'ready_for_pickup')

                                    <form
                                        method="POST"
                                        action="{{ route('rider.order.status', $orderId) }}"
                                    >
                                        @csrf

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="picked_up"
                                        >

                                        <button
                                            type="submit"
                                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                        >
                                            <i data-lucide="package-check" class="w-4 h-4"></i>
                                            Confirm Pickup
                                        </button>
                                    </form>


                                @elseif($status === 'picked_up')

                                    <form
                                        method="POST"
                                        action="{{ route('rider.order.status', $orderId) }}"
                                    >
                                        @csrf

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="at_sorting_center"
                                        >

                                        <button
                                            type="submit"
                                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                        >
                                            <i data-lucide="warehouse" class="w-4 h-4"></i>
                                            Arrived at Sorting Center
                                        </button>
                                    </form>


                                @elseif($status === 'assigned_to_rider')

                                    <form
                                        method="POST"
                                        action="{{ route('rider.order.status', $orderId) }}"
                                    >
                                        @csrf

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="out_for_delivery"
                                        >

                                        <button
                                            type="submit"
                                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                        >
                                            <i data-lucide="truck" class="w-4 h-4"></i>
                                            Start Delivery
                                        </button>
                                    </form>


                                @elseif($status === 'out_for_delivery')

                                    <form
                                        method="POST"
                                        action="{{ route('rider.order.status', $orderId) }}"
                                    >
                                        @csrf

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="delivered"
                                        >

                                        <button
                                            type="submit"
                                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                        >
                                            <i data-lucide="circle-check" class="w-4 h-4"></i>
                                            Mark as Delivered
                                        </button>
                                    </form>


                                @elseif($status === 'delivered')

                                    <span class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-green-50 text-green-700 border border-green-200 text-sm font-semibold">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                                        Delivery Completed
                                    </span>


                                @else

                                    <span class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-gray-50 text-gray-500 border border-gray-200 text-sm font-medium">
                                        No Action Required
                                    </span>

                                @endif

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        @else

            {{-- EMPTY STATE --}}
            <div class="px-6 py-16 text-center">

                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-50 flex items-center justify-center">
                    <i data-lucide="package-search" class="w-7 h-7 text-gray-400"></i>
                </div>

                <h3 class="text-base font-semibold text-gray-900 mb-1">
                    No delivery orders yet
                </h3>

                <p class="text-sm text-gray-500 max-w-md mx-auto">
                    Orders assigned for pickup or delivery will appear here.
                </p>

            </div>

        @endif

    </div>

</div>

@endsection