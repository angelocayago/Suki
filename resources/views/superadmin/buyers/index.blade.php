@extends('superadmin.layout')


@section('title')

Buyer Management

@endsection



@section('content')


<div class="w-full">



    <!-- HEADER -->


    <div class="mb-7">


        <h1 class="
            text-3xl
            font-bold
            text-[#173F35]
        ">

            Buyer Management

        </h1>


        <p class="
            text-gray-500
            mt-1
        ">

            Manage buyer accounts and monitor buyer activity.

        </p>


    </div>









    <!-- STAT CARDS -->


    <div class="
        grid
        grid-cols-1
        sm:grid-cols-2
        xl:grid-cols-4
        gap-4
        mb-5
    ">



        <div class="
            bg-[#FFF7E8]
            rounded-2xl
            border
            border-[#F3E4C2]
            p-6
        ">

            <p class="text-sm text-gray-500">

                Total Buyers

            </p>


            <h2 class="
                text-3xl
                font-bold
                text-[#173F35]
                mt-2
            ">

                {{ $stats['total'] }}

            </h2>


        </div>







        <div class="
            bg-[#EAFBF3]
            rounded-2xl
            border
            border-[#D3F1E1]
            p-6
        ">


            <p class="text-sm text-gray-500">

                Active Buyers

            </p>


            <h2 class="
                text-3xl
                font-bold
                text-[#173F35]
                mt-2
            ">

                {{ $stats['active'] }}

            </h2>


        </div>







        <div class="
            bg-[#FFF0F0]
            rounded-2xl
            border
            border-[#F6D4D4]
            p-6
        ">


            <p class="text-sm text-gray-500">

                Suspended Buyers

            </p>


            <h2 class="
                text-3xl
                font-bold
                text-[#173F35]
                mt-2
            ">

                {{ $stats['suspended'] }}

            </h2>


        </div>







        <div class="
            bg-[#EEF6FF]
            rounded-2xl
            border
            border-[#D8E9FA]
            p-6
        ">


            <p class="text-sm text-gray-500">

                Buyers With Orders

            </p>


            <h2 class="
                text-3xl
                font-bold
                text-[#173F35]
                mt-2
            ">

                {{ $stats['with_orders'] }}

            </h2>


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

            class="
                grid
                grid-cols-1
                lg:grid-cols-[1fr_230px_150px]
                gap-3
            "

        >



            <input

                type="text"

                name="search"

                value="{{ request('search') }}"

                placeholder="Search buyer..."

                class="
                    border
                    border-[#D6DFDA]
                    rounded-xl
                    px-4
                    py-3
                    text-sm
                    focus:outline-none
                    focus:border-[#1F6F5B]
                "

            >






            <select

                name="status"

                class="
                    border
                    border-[#D6DFDA]
                    rounded-xl
                    px-4
                    py-3
                    text-sm
                    bg-white
                "

            >


                <option value="">
                    All Status
                </option>


                <option value="active">
                    Active
                </option>


                <option value="inactive">
                    Inactive
                </option>


                <option value="suspended">
                    Suspended
                </option>


            </select>







            <button

                type="submit"

                class="
                    bg-[#1F6F5B]
                    text-white
                    rounded-xl
                    font-semibold
                    text-sm
                    hover:bg-[#155244]
                    transition
                "

            >

                Search

            </button>



        </form>


    </div>
        <!-- BUYER TABLE -->


    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        overflow-hidden
    ">



        <div class="overflow-x-auto">


            <table class="
                w-full
                min-w-[1000px]
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
                            Buyer
                        </th>


                        <th class="px-5 py-4 text-left">
                            Email
                        </th>


                        <th class="px-5 py-4 text-left">
                            Phone
                        </th>


                        <th class="px-5 py-4 text-left">
                            Orders
                        </th>


                        <th class="px-5 py-4 text-left">
                            Total Spent
                        </th>


                        <th class="px-5 py-4 text-left">
                            Joined
                        </th>


                        <th class="px-5 py-4 text-left">
                            Status
                        </th>


                        <th class="px-5 py-4 text-center">
                            Action
                        </th>


                    </tr>


                </thead>







                <tbody>


                    @forelse($buyers as $buyer)



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

                                {{ $buyer->name }}

                            </td>







                            <td class="
                                px-5
                                py-4
                                text-sm
                            ">

                                {{ $buyer->email }}

                            </td>







                            <td class="
                                px-5
                                py-4
                                text-sm
                            ">

                                {{ $buyer->phone ?? '-' }}

                            </td>







                            <td class="
                                px-5
                                py-4
                                text-sm
                            ">

                                {{ $buyer->buyer_orders_count }}

                            </td>







                            <td class="
                                px-5
                                py-4
                                text-sm
                            ">

                                ₱{{ number_format($buyer->buyer_orders_sum_total_amount ?? 0, 2) }}

                            </td>







                            <td class="
                                px-5
                                py-4
                                text-sm
                            ">

                                {{ $buyer->created_at->format('M d, Y') }}

                            </td>







                            <td class="px-5 py-4">


                                <span class="
                                    inline-flex
                                    px-3
                                    py-1
                                    rounded-full
                                    text-xs
                                    font-medium

                                    {{ $buyer->status === 'active'
                                        ? 'bg-green-100 text-green-700'
                                        :
                                        (
                                            $buyer->status === 'suspended'
                                            ? 'bg-red-100 text-red-700'
                                            :
                                            'bg-yellow-100 text-yellow-700'
                                        )
                                    }}

                                ">

                                    {{ ucfirst($buyer->status) }}

                                </span>


                            </td>








                            <td class="
                                px-5
                                py-4
                            ">


                                <form

                                    method="POST"

                                    action="{{ route('superadmin.buyers.status', $buyer) }}"

                                    class="
                                        flex
                                        justify-center
                                        items-center
                                        gap-2
                                    "

                                >

                                    @csrf





                                    <select

                                        name="status"

                                        class="
                                            w-[120px]
                                            border
                                            border-[#D6DFDA]
                                            rounded-lg
                                            px-3
                                            py-2
                                            text-xs
                                            bg-white
                                        "

                                    >



                                        @if($buyer->status !== 'active')

                                            <option value="active">
                                                Activate
                                            </option>

                                        @endif





                                        @if($buyer->status !== 'inactive')

                                            <option value="inactive">
                                                Set Inactive
                                            </option>

                                        @endif





                                        @if($buyer->status !== 'suspended')

                                            <option value="suspended">
                                                Suspend
                                            </option>

                                        @endif



                                    </select>







                                    <button

                                        type="submit"

                                        class="
                                            text-xs
                                            font-semibold
                                            text-[#1F6F5B]
                                            hover:text-[#155244]
                                            transition
                                        "

                                    >

                                        Update

                                    </button>



                                </form>


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

                                No buyer data available.

                            </td>


                        </tr>



                    @endforelse



                </tbody>


            </table>


        </div>


    </div>







    <div class="mt-6">

        {{ $buyers->links() }}

    </div>





</div>


@endsection