@extends('layouts.logistics')

@section('title', 'Parcel Sorting')
@section('page-heading', 'Parcel Sorting')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | PARCEL SORTING WORKSPACE
    |--------------------------------------------------------------------------
    |
    | ERP FLOW:
    |
    | AT_SORTING_CENTER
    |        ↓
    | Scan Parcel
    |        ↓
    | Determine Area
    |        ↓
    | SORTED
    |        ↓
    | ASSIGNED_TO_RIDER
    |
    */

    $orders = collect(session('orders', []));

    $sortingParcels = $orders
        ->filter(function ($order) {
            return in_array(
                strtolower($order['status'] ?? ''),
                [
                    'at_sorting_center',
                    'sorted',
                    'assigned_to_rider'
                ]
            );
        })
        ->values();


    $atSortingCenter = $sortingParcels
        ->where('status', 'at_sorting_center')
        ->count();


    $sortedParcels = $sortingParcels
        ->where('status', 'sorted')
        ->count();


    $assignedParcels = $sortingParcels
        ->where('status', 'assigned_to_rider')
        ->count();


    $areas = [
        'Area A',
        'Area B',
        'Area C'
    ];


    $riders = [
        [
            'id' => 1,
            'name' => 'Rider 01',
            'area' => 'Area A'
        ],
        [
            'id' => 2,
            'name' => 'Rider 02',
            'area' => 'Area B'
        ],
        [
            'id' => 3,
            'name' => 'Rider 03',
            'area' => 'Area C'
        ],
    ];


    $statusLabels = [

        'at_sorting_center'
            => 'At Sorting Center',

        'sorted'
            => 'Sorted',

        'assigned_to_rider'
            => 'Assigned to Rider',

    ];


    $statusClasses = [

        'at_sorting_center'
            => 'border-sky-200 bg-sky-50 text-sky-700',

        'sorted'
            => 'border-teal-200 bg-teal-50 text-teal-700',

        'assigned_to_rider'
            => 'border-violet-200 bg-violet-50 text-violet-700',

    ];

@endphp


{{-- HEADER --}}

<div
    class="mb-7
           flex flex-col gap-4
           lg:flex-row
           lg:items-end
           lg:justify-between"
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
                href="{{ route('logistics.dashboard') }}"
                class="hover:text-[#1F6F5B]"
            >
                Dashboard
            </a>

            <span>
                /
            </span>

            <span class="text-[#52635B]">
                Parcel Sorting
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Parcel Sorting Workspace
        </h2>


        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Sort incoming parcels according to destination
            area and prepare them for rider assignment.
        </p>

    </div>


    <div
        class="inline-flex
               h-10
               items-center gap-2
               rounded-xl
               border border-[#DDE6E1]
               bg-white
               px-4
               text-[11px]
               font-semibold
               text-[#52635B]"
    >

        <i
            data-lucide="scan-line"
            class="h-4 w-4 text-[#1F6F5B]"
        ></i>

        Sorting Center

    </div>

</div>



{{-- SUMMARY CARDS --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>


    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.12em]
                   text-[#839189]"
        >
            Incoming Parcels
        </p>

        <p
            class="mt-3
                   text-2xl
                   font-semibold
                   text-[#24312C]"
        >
            {{ $atSortingCenter }}
        </p>

    </div>



    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.12em]
                   text-[#839189]"
        >
            Sorted Parcels
        </p>

        <p
            class="mt-3
                   text-2xl
                   font-semibold
                   text-[#24312C]"
        >
            {{ $sortedParcels }}
        </p>

    </div>



    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.12em]
                   text-[#839189]"
        >
            Assigned Rider
        </p>

        <p
            class="mt-3
                   text-2xl
                   font-semibold
                   text-[#24312C]"
        >
            {{ $assignedParcels }}
        </p>

    </div>



    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <p
            class="text-[9px]
                   font-semibold
                   uppercase
                   tracking-[0.12em]
                   text-[#839189]"
        >
            Total Queue
        </p>

        <p
            class="mt-3
                   text-2xl
                   font-semibold
                   text-[#24312C]"
        >
            {{ $sortingParcels->count() }}
        </p>

    </div>


</div>
{{-- SORTING QUEUE --}}

<section
    class="overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>


    <div
        class="flex flex-col gap-3
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
                Parcel Sorting Queue
            </h3>

            <p
                class="mt-1
                       text-[11px]
                       text-[#7C8983]"
            >
                Scan parcels, determine destination area,
                and assign riders.
            </p>

        </div>


        <div
            class="inline-flex
                   items-center gap-2
                   text-[10px]
                   font-semibold
                   text-[#1F6F5B]"
        >

            <span
                class="h-2 w-2
                       rounded-full
                       bg-[#1F6F5B]"
            ></span>

            {{ $sortingParcels->count() }} parcel(s)

        </div>


    </div>



    @if($sortingParcels->count())


    <div
        class="overflow-x-auto"
    >

        <table
            class="w-full"
        >

            <thead>

                <tr
                    class="border-b
                           border-[#EDF1EF]
                           bg-[#F7F9F8]"
                >

                    <th
                        class="px-5 py-3
                               text-left
                               text-[10px]
                               font-semibold
                               uppercase
                               tracking-[0.1em]
                               text-[#839189]"
                    >
                        Parcel
                    </th>


                    <th
                        class="px-5 py-3
                               text-left
                               text-[10px]
                               font-semibold
                               uppercase
                               tracking-[0.1em]
                               text-[#839189]"
                    >
                        Destination
                    </th>


                    <th
                        class="px-5 py-3
                               text-left
                               text-[10px]
                               font-semibold
                               uppercase
                               tracking-[0.1em]
                               text-[#839189]"
                    >
                        Area
                    </th>


                    <th
                        class="px-5 py-3
                               text-left
                               text-[10px]
                               font-semibold
                               uppercase
                               tracking-[0.1em]
                               text-[#839189]"
                    >
                        Status
                    </th>


                    <th
                        class="px-5 py-3
                               text-right
                               text-[10px]
                               font-semibold
                               uppercase
                               tracking-[0.1em]
                               text-[#839189]"
                    >
                        Action
                    </th>


                </tr>

            </thead>



            <tbody>


            @foreach($sortingParcels as $parcel)


                @php

                    $status =
                        strtolower(
                            $parcel['status'] ?? ''
                        );


                    $statusLabel =
                        $statusLabels[$status]
                        ?? ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $status
                            )
                        );


                    $statusClass =
                        $statusClasses[$status]
                        ?? 'border-gray-200 bg-gray-100 text-gray-600';


                    $parcelId =
                        $parcel['id']
                        ?? 'N/A';


                    $address =
                        $parcel['shipping_address']['address']
                        ?? $parcel['shipping_address']['city']
                        ?? 'Delivery Address';


                    $area =
                        $parcel['delivery_area']
                        ?? 'Unassigned';


                @endphp



                <tr
                    class="border-b
                           border-[#F0F3F1]
                           hover:bg-[#FAFCFB]"
                >


                    <td
                        class="px-5 py-5"
                    >

                        <p
                            class="text-xs
                                   font-semibold
                                   text-[#24312C]"
                        >
                            #{{ $parcelId }}
                        </p>


                        <p
                            class="mt-1
                                   text-[10px]
                                   text-[#8A9791]"
                        >
                            Parcel Received
                        </p>


                    </td>



                    <td
                        class="px-5 py-5"
                    >

                        <div
                            class="flex
                                   items-center
                                   gap-2"
                        >

                            <div
                                class="flex h-8 w-8
                                       items-center
                                       justify-center
                                       rounded-lg
                                       bg-[#EEF5F1]
                                       text-[#1F6F5B]"
                            >

                                <i
                                    data-lucide="map-pin"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <p
                                class="max-w-[220px]
                                       text-xs
                                       text-[#52635B]"
                            >
                                {{ $address }}
                            </p>

                        </div>


                    </td>



                    <td
                        class="px-5 py-5"
                    >

                        <span
                            class="inline-flex
                                   rounded-full
                                   border border-[#DDE6E1]
                                   bg-[#F7F9F8]
                                   px-3 py-1
                                   text-[10px]
                                   font-semibold
                                   text-[#52635B]"
                        >

                            {{ $area }}

                        </span>


                    </td>



                    <td
                        class="px-5 py-5"
                    >

                        <span
                            class="inline-flex
                                   rounded-full
                                   border
                                   px-3 py-1
                                   text-[10px]
                                   font-semibold
                                   {{ $statusClass }}"
                        >

                            {{ $statusLabel }}

                        </span>


                    </td>



                    <td
                        class="px-5 py-5"
                    >

                        <div
                            class="flex
                                   justify-end"
                        >


                        @if($status === 'at_sorting_center')


                            <form
                                method="POST"
                                action="#"
                            >

                                @csrf

                                <button
                                    type="button"
                                    class="inline-flex
                                           h-9
                                           items-center
                                           gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           px-4
                                           text-[10px]
                                           font-semibold
                                           text-white
                                           hover:bg-[#1F6F5B]"
                                >

                                    <i
                                        data-lucide="scan-line"
                                        class="h-3.5 w-3.5"
                                    ></i>

                                    Scan Parcel

                                </button>

                            </form>



                        @elseif($status === 'sorted')


                            <button
                                type="button"
                                class="inline-flex
                                       h-9
                                       items-center
                                       gap-2
                                       rounded-xl
                                       bg-[#173F35]
                                       px-4
                                       text-[10px]
                                       font-semibold
                                       text-white
                                       hover:bg-[#1F6F5B]"
                            >

                                <i
                                    data-lucide="user-plus"
                                    class="h-3.5 w-3.5"
                                ></i>

                                Assign Rider

                            </button>



                        @elseif($status === 'assigned_to_rider')


                            <span
                                class="inline-flex
                                       items-center
                                       gap-2
                                       text-[10px]
                                       font-semibold
                                       text-violet-700"
                            >

                                <i
                                    data-lucide="check-circle"
                                    class="h-3.5 w-3.5"
                                ></i>

                                Ready for Delivery

                            </span>


                        @endif


                        </div>

                    </td>


                </tr>


            @endforeach


            </tbody>


        </table>


    </div>


    @else



    <div
        class="px-6 py-16
               text-center"
    >

        <div
            class="mx-auto
                   flex h-14 w-14
                   items-center
                   justify-center
                   rounded-2xl
                   bg-[#EEF5F1]
                   text-[#1F6F5B]"
        >

            <i
                data-lucide="package-search"
                class="h-6 w-6"
            ></i>


        </div>


        <h3
            class="mt-4
                   text-sm
                   font-semibold
                   text-[#34483F]"
        >
            No parcels waiting for sorting
        </h3>


        <p
            class="mt-1
                   text-xs
                   text-[#849089]"
        >
            Incoming parcels will appear here
            once received by the sorting center.
        </p>


    </div>


    @endif


</section>
{{-- PROCESS GUIDE + ASSIGNMENT PANEL --}}

<div
    class="mt-6
           grid grid-cols-1
           gap-6
           xl:grid-cols-2"
>


    {{-- SORTING PROCESS --}}

    <section
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="mb-5"
        >

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Sorting Process
            </h3>

            <p
                class="mt-1
                       text-[11px]
                       text-[#7C8983]"
            >
                Follow the standard logistics parcel workflow.
            </p>

        </div>



        <div
            class="space-y-4"
        >


            @php

                $steps = [

                    [
                        'title' => 'Receive Parcel',
                        'desc' => 'Parcel arrives at sorting center',
                        'icon' => 'package'
                    ],

                    [
                        'title' => 'Scan Parcel',
                        'desc' => 'Verify parcel information',
                        'icon' => 'scan-line'
                    ],

                    [
                        'title' => 'Determine Area',
                        'desc' => 'Identify destination area',
                        'icon' => 'map'
                    ],

                    [
                        'title' => 'Sort Parcel',
                        'desc' => 'Arrange according to destination',
                        'icon' => 'boxes'
                    ],

                    [
                        'title' => 'Assign Rider',
                        'desc' => 'Match rider with delivery area',
                        'icon' => 'user-check'
                    ],

                ];

            @endphp



            @foreach($steps as $index => $step)


            <div
                class="flex
                       items-center
                       gap-4"
            >


                <div
                    class="flex h-10 w-10
                           shrink-0
                           items-center
                           justify-center
                           rounded-xl
                           bg-[#EEF5F1]
                           text-[#1F6F5B]"
                >

                    <i
                        data-lucide="{{ $step['icon'] }}"
                        class="h-4 w-4"
                    ></i>

                </div>



                <div>

                    <p
                        class="text-xs
                               font-semibold
                               text-[#34483F]"
                    >

                        {{ $index + 1 }}.
                        {{ $step['title'] }}

                    </p>


                    <p
                        class="mt-0.5
                               text-[10px]
                               text-[#849089]"
                    >

                        {{ $step['desc'] }}

                    </p>

                </div>


            </div>


            @endforeach


        </div>


    </section>





    {{-- RIDER ASSIGNMENT --}}

    <section
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >


        <div
            class="mb-5"
        >

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Rider Area Assignment
            </h3>


            <p
                class="mt-1
                       text-[11px]
                       text-[#7C8983]"
            >
                Available riders based on delivery area.
            </p>


        </div>




        <div
            class="space-y-3"
        >


            @foreach($riders as $rider)


            <div
                class="flex
                       items-center
                       justify-between
                       rounded-xl
                       border border-[#EDF1EF]
                       bg-[#FAFCFB]
                       p-4"
            >


                <div
                    class="flex
                           items-center
                           gap-3"
                >


                    <div
                        class="flex h-10 w-10
                               items-center
                               justify-center
                               rounded-xl
                               bg-[#173F35]
                               text-white"
                    >

                        <i
                            data-lucide="bike"
                            class="h-4 w-4"
                        ></i>


                    </div>



                    <div>

                        <p
                            class="text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >

                            {{ $rider['name'] }}

                        </p>


                        <p
                            class="mt-1
                                   text-[10px]
                                   text-[#849089]"
                        >

                            Assigned Area:
                            {{ $rider['area'] }}

                        </p>


                    </div>


                </div>



                <span
                    class="rounded-full
                           border border-emerald-200
                           bg-emerald-50
                           px-3 py-1
                           text-[9px]
                           font-semibold
                           text-emerald-700"
                >

                    Available

                </span>


            </div>


            @endforeach


        </div>


    </section>


</div>




{{-- STATUS LEGEND --}}

<section
    class="mt-6
           rounded-2xl
           border border-[#E1E8E4]
           bg-white
           p-5"
>


    <h3
        class="text-sm
               font-semibold
               text-[#24312C]"
    >
        Parcel Status Flow
    </h3>


    <div
        class="mt-4
               flex
               flex-wrap
               items-center
               gap-2"
    >


        @foreach([
            'AT_SORTING_CENTER',
            'SORTED',
            'ASSIGNED_TO_RIDER'
        ] as $status)


        <span
            class="inline-flex
                   items-center
                   rounded-full
                   border border-[#DDE6E1]
                   bg-[#F7F9F8]
                   px-3 py-1.5
                   text-[10px]
                   font-semibold
                   text-[#52635B]"
        >

            {{ $status }}

        </span>


        @if(!$loop->last)

        <i
            data-lucide="arrow-right"
            class="h-3.5 w-3.5
                   text-[#839189]"
        ></i>

        @endif


        @endforeach


    </div>


</section>



@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        if(
            typeof lucide !== 'undefined'
            &&
            typeof lucide.createIcons === 'function'
        ){

            lucide.createIcons();

        }

    }
);

</script>

@endpush


@endsection