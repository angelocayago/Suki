@extends('superadmin.layout')


@section('title')

Commission Management

@endsection



@section('content')


<div
    class="w-full"
    x-data="{
        showRateModal: false,
        selectedSeller: '',
        selectedRate: '',
        selectedUrl: '',

        openRateModal(name, rate, url) {
            this.selectedSeller = name;
            this.selectedRate = rate;
            this.selectedUrl = url;
            this.showRateModal = true;
        }
    }"
>



    <!-- HEADER -->

    <div class="mb-7">

        <h1 class="
            text-3xl
            font-bold
            text-[#173F35]
        ">
            Commission Management
        </h1>

        <p class="
            text-gray-500
            mt-1
        ">
            Monitor seller sales, platform commissions, and seller commission rates.
        </p>

    </div>





    <!-- SUCCESS -->

    @if(session('success'))

        <div class="
            mb-6
            rounded-2xl
            border
            border-green-200
            bg-green-50
            px-5
            py-4
            text-sm
            font-medium
            text-green-700
        ">
            {{ session('success') }}
        </div>

    @endif





    <!-- STAT CARDS -->

    <div class="
        grid
        grid-cols-1
        sm:grid-cols-2
        xl:grid-cols-4
        gap-4
        mb-5
    ">



        <!-- TOTAL SALES -->

        <div class="
            relative
            overflow-hidden
            bg-white
            rounded-2xl
            border
            border-[#DCE5E0]
            shadow-sm
            p-6
        ">

            <div class="
                absolute
                left-0
                top-0
                bottom-0
                w-1
                bg-[#15966A]
            "></div>


            <p class="
                text-sm
                text-gray-500
            ">
                Total Seller Sales
            </p>


            <p class="
                text-2xl
                font-bold
                text-[#132E28]
                mt-2
            ">
                ₱{{ number_format(
                    $stats['total_sales_minor'] / 100,
                    2
                ) }}
            </p>


            <div class="
                mt-2
                text-xs
            ">

                @if($stats['sales_trend'] !== null)

                    <span class="
                        font-medium
                        {{ $stats['sales_trend'] >= 0
                            ? 'text-green-600'
                            : 'text-red-600'
                        }}
                    ">
                        {{ $stats['sales_trend'] >= 0 ? '↑' : '↓' }}
                        {{ number_format(abs($stats['sales_trend']), 1) }}%
                    </span>

                    <span class="text-gray-400 ml-2">
                        this month vs. last month
                    </span>

                @else

                    <span class="text-gray-400">
                        No prior month data
                    </span>

                @endif

            </div>

        </div>





        <!-- PLATFORM COMMISSION -->

        <div class="
            relative
            overflow-hidden
            bg-white
            rounded-2xl
            border
            border-[#DCE5E0]
            shadow-sm
            p-6
        ">

            <div class="
                absolute
                left-0
                top-0
                bottom-0
                w-1
                bg-[#15966A]
            "></div>


            <p class="text-sm text-gray-500">
                Platform Commission
            </p>


            <p class="
                text-2xl
                font-bold
                text-[#132E28]
                mt-2
            ">
                ₱{{ number_format(
                    $stats['platform_commission_minor'] / 100,
                    2
                ) }}
            </p>


            <div class="
                mt-2
                text-xs
            ">

                @if($stats['commission_trend'] !== null)

                    <span class="
                        font-medium
                        {{ $stats['commission_trend'] >= 0
                            ? 'text-green-600'
                            : 'text-red-600'
                        }}
                    ">
                        {{ $stats['commission_trend'] >= 0 ? '↑' : '↓' }}
                        {{ number_format(abs($stats['commission_trend']), 1) }}%
                    </span>

                    <span class="text-gray-400 ml-2">
                        this month vs. last month
                    </span>

                @else

                    <span class="text-gray-400">
                        No prior month data
                    </span>

                @endif

            </div>

        </div>





        <!-- NET EARNINGS -->

        <div class="
            relative
            overflow-hidden
            bg-white
            rounded-2xl
            border
            border-[#DCE5E0]
            shadow-sm
            p-6
        ">

            <div class="
                absolute
                left-0
                top-0
                bottom-0
                w-1
                bg-[#15966A]
            "></div>


            <p class="text-sm text-gray-500">
                Seller Net Earnings
            </p>


            <p class="
                text-2xl
                font-bold
                text-[#132E28]
                mt-2
            ">
                ₱{{ number_format(
                    $stats['seller_net_minor'] / 100,
                    2
                ) }}
            </p>


            <div class="
                mt-2
                text-xs
            ">

                @if($stats['net_trend'] !== null)

                    <span class="
                        font-medium
                        {{ $stats['net_trend'] >= 0
                            ? 'text-green-600'
                            : 'text-red-600'
                        }}
                    ">
                        {{ $stats['net_trend'] >= 0 ? '↑' : '↓' }}
                        {{ number_format(abs($stats['net_trend']), 1) }}%
                    </span>

                    <span class="text-gray-400 ml-2">
                        this month vs. last month
                    </span>

                @else

                    <span class="text-gray-400">
                        No prior month data
                    </span>

                @endif

            </div>

        </div>





        <!-- RECORDS -->

        <div class="
            relative
            overflow-hidden
            bg-white
            rounded-2xl
            border
            border-[#DCE5E0]
            shadow-sm
            p-6
        ">

            <div class="
                absolute
                left-0
                top-0
                bottom-0
                w-1
                bg-[#15966A]
            "></div>


            <p class="text-sm text-gray-500">
                Commission Records
            </p>


            <p class="
                text-2xl
                font-bold
                text-[#132E28]
                mt-2
            ">
                {{ $stats['records'] }}
            </p>


            <div class="
                mt-2
                text-xs
            ">

                @if($stats['records_trend'] !== null)

                    <span class="
                        font-medium
                        {{ $stats['records_trend'] >= 0
                            ? 'text-green-600'
                            : 'text-red-600'
                        }}
                    ">
                        {{ $stats['records_trend'] >= 0 ? '↑' : '↓' }}
                        {{ number_format(abs($stats['records_trend']), 1) }}%
                    </span>

                    <span class="text-gray-400 ml-2">
                        this month vs. last month
                    </span>

                @else

                    <span class="text-gray-400">
                        No prior month data
                    </span>

                @endif

            </div>

        </div>


    </div>





    <!-- FILTER BAR -->

    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        p-4
        mb-5
    ">

        <form
            method="GET"
            action="{{ route('superadmin.commission') }}"
            class="
                grid
                grid-cols-1
                lg:grid-cols-[1fr_230px_155px]
                gap-3
            "
        >


            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search order or seller..."
                class="
                    w-full
                    border
                    border-[#D6DFDA]
                    rounded-xl
                    px-4
                    py-3
                    text-sm
                    focus:outline-none
                    focus:ring-2
                    focus:ring-[#1F6F5B]/20
                    focus:border-[#1F6F5B]
                "
            >



            <select
                name="status"
                class="
                    w-full
                    border
                    border-[#D6DFDA]
                    rounded-xl
                    px-4
                    py-3
                    text-sm
                    bg-white
                    focus:outline-none
                    focus:border-[#1F6F5B]
                "
            >

                <option value="">
                    All Status
                </option>


                @foreach($statuses as $status)

                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ ucwords(str_replace('_', ' ', $status)) }}
                    </option>

                @endforeach

            </select>



            <button
                type="submit"
                class="
                    rounded-xl
                    bg-[#1F6F5B]
                    text-white
                    px-6
                    py-3
                    text-sm
                    font-semibold
                    hover:bg-[#155244]
                    transition
                "
            >
                Search
            </button>


        </form>

    </div>





    <!-- COMMISSION RECORDS -->

    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        overflow-hidden
        mb-5
    ">


        <div class="
            px-6
            pt-5
            pb-4
        ">

            <h2 class="
                text-xl
                font-bold
                text-[#173F35]
            ">
                Commission Records
            </h2>


            <p class="
                text-sm
                text-gray-500
                mt-1
            ">
                Recorded seller-order sales and commission amounts.
            </p>

        </div>



        <div class="overflow-x-auto">


            <table class="
                w-full
                min-w-[1050px]
            ">


                <thead class="
                    bg-[#F4F7F5]
                    text-[11px]
                    uppercase
                    tracking-wide
                    text-[#607169]
                ">

                    <tr>

                        <th class="px-5 py-4 text-left">
                            Order
                        </th>

                        <th class="px-5 py-4 text-left">
                            Seller
                        </th>

                        <th class="px-5 py-4 text-left">
                            Seller Sales
                        </th>

                        <th class="px-5 py-4 text-left">
                            Commission
                        </th>

                        <th class="px-5 py-4 text-left">
                            Seller Earnings
                        </th>

                        <th class="px-5 py-4 text-left">
                            Status
                        </th>

                        <th class="px-5 py-4 text-left">
                            Date
                        </th>

                        <th class="px-5 py-4 text-center">
                            Action
                        </th>

                    </tr>

                </thead>



                <tbody>


                    @forelse($commissionRecords as $record)


                        @php

                            $sellerEarningsMinor =
                                $record->subtotal_minor
                                - $record->commission_minor;

                        @endphp


                        <tr class="
                            border-t
                            border-[#E7ECE9]
                            hover:bg-[#FBFCFB]
                            transition
                        ">


                            <td class="
                                px-5
                                py-4
                                text-sm
                                font-semibold
                                text-[#176B55]
                            ">

                                #{{ $record->order->order_number }}

                            </td>



                            <td class="px-5 py-4">

                                <p class="
                                    text-sm
                                    font-medium
                                    text-[#253831]
                                ">
                                    {{ $record->seller->name }}
                                </p>

                                @if($record->seller->owner)

                                    <p class="
                                        text-xs
                                        text-gray-400
                                        mt-1
                                    ">
                                        {{ $record->seller->owner->name }}
                                    </p>

                                @endif

                            </td>



                            <td class="px-5 py-4 text-sm">

                                ₱{{ number_format(
                                    $record->subtotal_minor / 100,
                                    2
                                ) }}

                            </td>



                            <td class="px-5 py-4 text-sm">

                                ₱{{ number_format(
                                    $record->commission_minor / 100,
                                    2
                                ) }}

                            </td>



                            <td class="px-5 py-4 text-sm">

                                ₱{{ number_format(
                                    $sellerEarningsMinor / 100,
                                    2
                                ) }}

                            </td>



                            <td class="px-5 py-4">

                                <span class="
                                    inline-flex
                                    rounded-full
                                    px-3
                                    py-1
                                    text-xs
                                    font-medium

                                    {{ $record->status === 'completed'
                                        ? 'bg-green-100 text-green-700'
                                        : (
                                            $record->status === 'cancelled'
                                            ? 'bg-red-100 text-red-700'
                                            : (
                                                in_array(
                                                    $record->status,
                                                    [
                                                        'shipped',
                                                        'delivered'
                                                    ]
                                                )
                                                ? 'bg-blue-100 text-blue-700'
                                                : 'bg-yellow-100 text-yellow-700'
                                            )
                                        )
                                    }}
                                ">

                                    {{ ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $record->status
                                        )
                                    ) }}

                                </span>

                            </td>



                            <td class="
                                px-5
                                py-4
                                text-sm
                                text-gray-600
                            ">

                                {{ $record->created_at->format('M d, Y') }}

                            </td>



                            <td class="
                                px-5
                                py-4
                                text-center
                            ">

                                <a
                                    href="{{ route(
                                        'superadmin.orders.show',
                                        $record->order
                                    ) }}"
                                    class="
                                        inline-flex
                                        justify-center
                                        min-w-[90px]
                                        border
                                        border-[#D8E1DC]
                                        rounded-lg
                                        px-4
                                        py-2
                                        text-xs
                                        font-medium
                                        text-[#176B55]
                                        hover:bg-[#176B55]
                                        hover:text-white
                                        hover:border-[#176B55]
                                        transition
                                    "
                                >
                                    View
                                </a>

                            </td>


                        </tr>


                    @empty


                        <tr>

                            <td
                                colspan="8"
                                class="
                                    px-6
                                    py-12
                                    text-center
                                    text-sm
                                    text-gray-500
                                "
                            >
                                No commission records found.
                            </td>

                        </tr>


                    @endforelse


                </tbody>


            </table>


        </div>



        @if($commissionRecords->hasPages())

            <div class="
                border-t
                border-[#E7ECE9]
                px-6
                py-4
            ">

                {{ $commissionRecords->links() }}

            </div>

        @endif


    </div>





    <!-- SELLER COMMISSION RATES -->

    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        overflow-hidden
    ">


        <div class="
            px-6
            pt-5
            pb-4
        ">

            <h2 class="
                text-xl
                font-bold
                text-[#173F35]
            ">
                Seller Commission Rates
            </h2>


            <p class="
                text-sm
                text-gray-500
                mt-1
            ">
                Current commission rates applied to sellers in the platform.
            </p>

        </div>



        <div class="overflow-x-auto">


            <table class="
                w-full
                min-w-[850px]
            ">


                <thead class="
                    bg-[#F4F7F5]
                    text-[11px]
                    uppercase
                    tracking-wide
                    text-[#607169]
                ">

                    <tr>

                        <th class="px-5 py-4 text-left">
                            Seller Name
                        </th>

                        <th class="px-5 py-4 text-left">
                            Owner
                        </th>

                        <th class="px-5 py-4 text-left">
                            Commission Rate
                        </th>

                        <th class="px-5 py-4 text-left">
                            Status
                        </th>

                        <th class="px-5 py-4 text-left">
                            Orders
                        </th>

                        <th class="px-5 py-4 text-center">
                            Action
                        </th>

                    </tr>

                </thead>



                <tbody>


                    @forelse($sellers as $seller)


                        <tr class="
                            border-t
                            border-[#E7ECE9]
                            hover:bg-[#FBFCFB]
                            transition
                        ">


                            <td class="
                                px-5
                                py-4
                                text-sm
                                font-semibold
                                text-[#253831]
                            ">

                                {{ $seller->name }}

                            </td>



                            <td class="
                                px-5
                                py-4
                            ">

                                <p class="
                                    text-sm
                                    text-[#253831]
                                ">
                                    {{ $seller->owner->name ?? '-' }}
                                </p>

                                @if($seller->owner?->email)

                                    <p class="
                                        text-xs
                                        text-gray-400
                                        mt-1
                                    ">
                                        {{ $seller->owner->email }}
                                    </p>

                                @endif

                            </td>



                            <td class="
                                px-5
                                py-4
                                text-sm
                                font-semibold
                                text-[#173F35]
                            ">

                                {{ number_format(
                                    $seller->commission_bps / 100,
                                    2
                                ) }}%

                            </td>



                            <td class="px-5 py-4">

                                <span class="
                                    inline-flex
                                    rounded-full
                                    px-3
                                    py-1
                                    text-xs
                                    font-medium

                                    {{ $seller->status === 'approved'
                                        ? 'bg-green-100 text-green-700'
                                        : (
                                            $seller->status === 'suspended'
                                            ? 'bg-red-100 text-red-700'
                                            : (
                                                $seller->status === 'rejected'
                                                ? 'bg-red-100 text-red-700'
                                                : 'bg-yellow-100 text-yellow-700'
                                            )
                                        )
                                    }}
                                ">

                                    {{ $seller->status === 'approved'
                                        ? 'Active'
                                        : ucfirst($seller->status)
                                    }}

                                </span>

                            </td>



                            <td class="
                                px-5
                                py-4
                                text-sm
                            ">

                                {{ $seller->orders_count }}

                            </td>



                            <td class="
                                px-5
                                py-4
                                text-center
                            ">

                                <button
                                    type="button"
                                    @click="openRateModal(
                                        @js($seller->name),
                                        @js(
                                            number_format(
                                                $seller->commission_bps / 100,
                                                2,
                                                '.',
                                                ''
                                            )
                                        ),
                                        @js(
                                            route(
                                                'superadmin.commission.rate',
                                                $seller
                                            )
                                        )
                                    )"
                                    class="
                                        min-w-[90px]
                                        border
                                        border-[#D8E1DC]
                                        rounded-lg
                                        px-4
                                        py-2
                                        text-xs
                                        font-medium
                                        text-[#176B55]
                                        hover:bg-[#176B55]
                                        hover:text-white
                                        hover:border-[#176B55]
                                        transition
                                    "
                                >
                                    Edit
                                </button>

                            </td>


                        </tr>


                    @empty


                        <tr>

                            <td
                                colspan="6"
                                class="
                                    px-6
                                    py-12
                                    text-center
                                    text-sm
                                    text-gray-500
                                "
                            >
                                No sellers found.
                            </td>

                        </tr>


                    @endforelse


                </tbody>


            </table>


        </div>



        @if($sellers->hasPages())

            <div class="
                border-t
                border-[#E7ECE9]
                px-6
                py-4
            ">

                {{ $sellers->links() }}

            </div>

        @endif


    </div>





    <!-- EDIT RATE MODAL -->

    <div
        x-cloak
        x-show="showRateModal"
        x-transition.opacity
        @keydown.escape.window="showRateModal = false"
        class="
            fixed
            inset-0
            z-50
            flex
            items-center
            justify-center
            bg-black/40
            px-4
        "
    >


        <div
            @click.outside="showRateModal = false"
            class="
                w-full
                max-w-md
                rounded-3xl
                bg-white
                p-8
                shadow-xl
            "
        >


            <h2 class="
                text-xl
                font-bold
                text-[#173F35]
            ">
                Update Commission Rate
            </h2>


            <p class="
                mt-2
                text-sm
                text-gray-500
            ">
                Seller:
                <span
                    class="font-medium text-gray-700"
                    x-text="selectedSeller"
                ></span>
            </p>



            <form
                method="POST"
                :action="selectedUrl"
                class="mt-6"
            >

                @csrf


                <label class="
                    block
                    mb-2
                    text-sm
                    font-medium
                    text-gray-700
                ">
                    Commission Rate
                </label>


                <div class="relative">

                    <input
                        type="number"
                        name="commission_rate"
                        x-model="selectedRate"
                        min="0"
                        max="100"
                        step="0.01"
                        required
                        class="
                            w-full
                            rounded-xl
                            border
                            border-gray-300
                            px-4
                            py-3
                            pr-12
                            focus:outline-none
                            focus:ring-2
                            focus:ring-[#1F6F5B]/20
                            focus:border-[#1F6F5B]
                        "
                    >

                    <span class="
                        absolute
                        right-4
                        top-1/2
                        -translate-y-1/2
                        text-gray-500
                    ">
                        %
                    </span>

                </div>



                @error('commission_rate')

                    <p class="
                        mt-2
                        text-sm
                        text-red-600
                    ">
                        {{ $message }}
                    </p>

                @enderror



                <p class="
                    mt-3
                    text-xs
                    leading-relaxed
                    text-gray-500
                ">
                    This changes the seller's current commission rate only.
                    Existing commission records will not be recalculated.
                </p>



                <div class="
                    mt-7
                    flex
                    justify-end
                    gap-3
                ">


                    <button
                        type="button"
                        @click="showRateModal = false"
                        class="
                            rounded-xl
                            border
                            border-gray-300
                            px-5
                            py-2.5
                            font-medium
                            text-gray-700
                            hover:bg-gray-50
                        "
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="
                            rounded-xl
                            bg-[#1F6F5B]
                            px-5
                            py-2.5
                            font-medium
                            text-white
                            hover:bg-[#155244]
                            transition
                        "
                    >
                        Save Rate
                    </button>


                </div>


            </form>


        </div>


    </div>



</div>


<style>
    [x-cloak] {
        display: none !important;
    }
</style>


@endsection