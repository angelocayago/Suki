@extends('admin.layout')


@section('title')
Settings
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
            Settings
        </h1>


        <p class="
            text-gray-500
            mt-1
        ">
            Manage platform configuration and policies.
        </p>

    </div>




    @if(session('success'))

        <div class="
            mb-5
            rounded-xl
            border
            border-green-200
            bg-green-50
            px-5
            py-3
            text-sm
            text-green-700
        ">

            {{ session('success') }}

        </div>

    @endif






    <!-- PLATFORM INFORMATION -->


    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        p-6
        mb-5
    ">


        <h2 class="
            text-xl
            font-bold
            text-[#173F35]
            mb-6
        ">
            Platform Information
        </h2>




        <form

            method="POST"

            action="{{ route('admin.settings.platform') }}"

        >

            @csrf
            @method('PATCH')



            <div class="
                grid
                grid-cols-1
                lg:grid-cols-2
                gap-x-8
                gap-y-5
            ">



                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-gray-600
                        mb-2
                    ">
                        Platform Name
                    </label>


                    <input

                        type="text"

                        name="platform_name"

                        value="{{ $settings['platform_name'] ?? 'SUKI Marketplace' }}"

                        class="
                            w-full
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

                </div>






                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-gray-600
                        mb-2
                    ">
                        Support Email
                    </label>


                    <input

                        type="email"

                        name="support_email"

                        value="{{ $settings['support_email'] ?? '' }}"

                        class="
                            w-full
                            border
                            border-[#D6DFDA]
                            rounded-xl
                            px-4
                            py-3
                            text-sm
                        "

                    >

                </div>






                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-gray-600
                        mb-2
                    ">
                        Contact Number
                    </label>


                    <input

                        type="text"

                        name="contact_number"

                        value="{{ $settings['contact_number'] ?? '' }}"

                        class="
                            w-full
                            border
                            border-[#D6DFDA]
                            rounded-xl
                            px-4
                            py-3
                            text-sm
                        "

                    >

                </div>


            </div>






            <div class="mt-5">


                <button

                    class="
                        bg-[#1F6F5B]
                        text-white
                        rounded-xl
                        px-5
                        py-3
                        text-sm
                        font-semibold
                        hover:bg-[#155244]
                        transition
                    "

                >

                    Save Changes

                </button>


            </div>


        </form>


    </div>









    {{-- Operational marketplace rules are deliberately not editable through content settings. --}}
    {{-- <!-- MARKETPLACE SETTINGS -->


    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        p-6
        mb-5
    ">


        <h2 class="
            text-xl
            font-bold
            text-[#173F35]
            mb-6
        ">
            Marketplace Settings
        </h2>





        <form

            method="POST"

            action="{{ route('admin.settings.marketplace') }}"

        >

            @csrf
            @method('PATCH')





            <div class="
                grid
                grid-cols-1
                lg:grid-cols-2
                gap-5
            ">




                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-gray-600
                        mb-2
                    ">
                        Default Commission Rate
                    </label>



                    <div class="relative">


                        <input

                            type="number"

                            name="default_commission_rate"

                            value="{{ old('default_commission_rate', $settings['default_commission_rate'] ?? 10) }}"

                            step="0.01"

                            class="
                                w-full
                                border
                                border-[#D6DFDA]
                                rounded-xl
                                px-4
                                py-3
                                pr-10
                                text-sm
                                focus:outline-none
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


                </div>








                <div>

                    <label class="
                        block
                        text-sm
                        font-medium
                        text-gray-600
                        mb-2
                    ">
                        Seller Applications
                    </label>



                    <select

                        name="allow_seller_applications"

                        class="
                            w-full
                            border
                            border-[#D6DFDA]
                            rounded-xl
                            px-4
                            py-3
                            text-sm
                        "

                    >


                        <option

                            value="enabled"

                            {{ ($settings['allow_seller_applications'] ?? 'enabled') == 'enabled' ? 'selected' : '' }}

                        >

                            Enabled

                        </option>



                        <option

                            value="disabled"

                            {{ ($settings['allow_seller_applications'] ?? '') == 'disabled' ? 'selected' : '' }}

                        >

                            Disabled

                        </option>


                    </select>


                </div>



            </div>






            <div class="mt-5">


                <button

                    class="
                        bg-[#1F6F5B]
                        text-white
                        rounded-xl
                        px-5
                        py-3
                        text-sm
                        font-semibold
                        hover:bg-[#155244]
                        transition
                    "

                >

                    Save Settings

                </button>


            </div>




        </form>


    </div> --}}
        <!-- ANNOUNCEMENTS -->


    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        p-6
        mb-5
    ">


        <div class="
            flex
            justify-between
            items-center
            mb-6
        ">


            <h2 class="
                text-xl
                font-bold
                text-[#173F35]
            ">
                Announcements
            </h2>


        </div>





        <form

            method="POST"

            action="{{ route('admin.settings.announcement.store') }}"

            class="
                grid
                grid-cols-1
                lg:grid-cols-[280px_1fr_100px]
                gap-3
                mb-6
            "

        >

            @csrf



            <input

                type="text"

                name="title"

                placeholder="Announcement title"

                class="
                    border
                    border-[#D6DFDA]
                    rounded-xl
                    px-4
                    py-3
                    text-sm
                "

            >





            <input

                type="text"

                name="message"

                placeholder="Announcement message"

                class="
                    border
                    border-[#D6DFDA]
                    rounded-xl
                    px-4
                    py-3
                    text-sm
                "

            >





            <button

                class="
                    bg-[#1F6F5B]
                    text-white
                    rounded-xl
                    px-5
                    py-3
                    text-sm
                    font-semibold
                    hover:bg-[#155244]
                    transition
                "

            >

                Add

            </button>


        </form>








        <div class="overflow-x-auto">


            <table class="
                w-full
                min-w-[800px]
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
                            Title
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


                    @forelse($announcements as $announcement)


                        <tr class="
                            border-t
                            border-[#E7ECE9]
                        ">



                            <td class="
                                px-5
                                py-4
                                text-sm
                                font-medium
                            ">

                                {{ $announcement->title }}

                                <details class="mt-2 text-xs font-normal">
                                    <summary class="cursor-pointer text-[#1F6F5B]">Edit announcement</summary>
                                    <form method="POST" action="{{ route('admin.settings.announcement.update', $announcement) }}" class="mt-2 space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <input required name="title" value="{{ $announcement->title }}" class="w-full rounded border border-[#D6DFDA] px-2 py-1">
                                        <textarea required name="message" rows="2" class="w-full rounded border border-[#D6DFDA] px-2 py-1">{{ $announcement->message }}</textarea>
                                        <select name="status" class="rounded border border-[#D6DFDA] px-2 py-1"><option value="active" @selected($announcement->status === 'active')>Active</option><option value="inactive" @selected($announcement->status === 'inactive')>Inactive</option></select>
                                        <button class="rounded border border-[#1F6F5B] px-2 py-1 text-[#1F6F5B]">Save</button>
                                    </form>
                                </details>

                            </td>





                            <td class="px-5 py-4">


                                <span class="
                                    px-3
                                    py-1
                                    rounded-full
                                    text-xs
                                    bg-green-100
                                    text-green-700
                                ">

                                    {{ ucfirst($announcement->status) }}

                                </span>


                            </td>





                            <td class="
                                px-5
                                py-4
                                text-sm
                            ">

                                {{ $announcement->created_at->format('M d, Y') }}

                            </td>





                            <td class="
                                px-5
                                py-4
                                text-center
                            ">


                                <form

                                    method="POST"

                                    action="{{ route('admin.settings.announcement.deactivate',$announcement) }}"

                                >

                                    @csrf

                                    @method('PATCH')



                                    <button

                                        class="
                                            px-3
                                            py-2
                                            rounded-lg
                                            text-xs
                                            text-red-600
                                            border
                                            border-red-200
                                            hover:bg-red-50
                                            transition
                                        "

                                    >

                                        Deactivate

                                    </button>


                                </form>


                            </td>



                        </tr>



                    @empty


                        <tr>

                            <td

                                colspan="4"

                                class="
                                    px-6
                                    py-10
                                    text-center
                                    text-gray-500
                                "

                            >

                                No announcements found.

                            </td>


                        </tr>


                    @endforelse



                </tbody>


            </table>


        </div>


    </div>









    <!-- POLICIES -->


    <div class="
        bg-white
        rounded-2xl
        border
        border-[#DCE5E0]
        shadow-sm
        p-6
    ">


        <h2 class="
            text-xl
            font-bold
            text-[#173F35]
            mb-6
        ">
            Policies
        </h2>





        <form

            method="POST"

            action="{{ route('admin.settings.policies') }}"

        >

            @csrf
            @method('PATCH')





            <div class="
                grid
                grid-cols-1
                lg:grid-cols-2
                gap-5
            ">




                <div>


                    <label class="
                        block
                        text-sm
                        font-medium
                        text-gray-600
                        mb-2
                    ">

                        Terms of Service

                    </label>



                    <textarea

                        name="terms_of_service"

                        rows="5"

                        class="
                            w-full
                            border
                            border-[#D6DFDA]
                            rounded-xl
                            px-4
                            py-3
                            text-sm
                        "

                    >{{ $settings['terms_of_service'] ?? '' }}</textarea>


                </div>








                <div>


                    <label class="
                        block
                        text-sm
                        font-medium
                        text-gray-600
                        mb-2
                    ">

                        Privacy Policy

                    </label>



                    <textarea

                        name="privacy_policy"

                        rows="5"

                        class="
                            w-full
                            border
                            border-[#D6DFDA]
                            rounded-xl
                            px-4
                            py-3
                            text-sm
                        "

                    >{{ $settings['privacy_policy'] ?? '' }}</textarea>


                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Shipping Policy</label>
                    <textarea name="shipping_policy" rows="5" class="w-full border border-[#D6DFDA] rounded-xl px-4 py-3 text-sm">{{ $settings['shipping_policy'] ?? '' }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Returns / Refund Policy</label>
                    <textarea name="returns_refund_policy" rows="5" class="w-full border border-[#D6DFDA] rounded-xl px-4 py-3 text-sm">{{ $settings['returns_refund_policy'] ?? '' }}</textarea>
                </div>



            </div>






            <div class="mt-5">


                <button

                    class="
                        bg-[#1F6F5B]
                        text-white
                        rounded-xl
                        px-5
                        py-3
                        text-sm
                        font-semibold
                    "

                >

                    Save Policies

                </button>


            </div>



        </form>


    </div>





</div>


@endsection
