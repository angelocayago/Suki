@extends('layouts.seller')

@section('title', 'Store Profile')

@section('page-title', 'Store Profile')


@section('content')

<div class="space-y-6">


    {{-- HEADER --}}
    <div>
        <h2 class="text-2xl font-bold text-[#173F35]">
            Store Profile
        </h2>

        <p class="mt-1 text-sm text-gray-500">
            Manage your store information and seller details.
        </p>
    </div>



    {{-- STORE INFORMATION --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">

        <h3 class="text-lg font-semibold text-gray-900 mb-5">
            Store Information
        </h3>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


            <div>

                <label class="text-sm font-medium text-gray-700">
                    Business Name
                </label>

                <input
                    type="text"
                    value="{{ session('seller_profile.shop_name') ?? '' }}"
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-[#1F6F5B] focus:ring-[#1F6F5B]/10"
                >

            </div>



            <div>

                <label class="text-sm font-medium text-gray-700">
                    Business Category
                </label>

                <input
                    type="text"
                    placeholder="Example: Food, Clothing, Electronics"
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm"
                >

            </div>



            <div class="md:col-span-2">

                <label class="text-sm font-medium text-gray-700">
                    Store Description
                </label>

                <textarea
                    rows="4"
                    placeholder="Describe your store..."
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm"
                ></textarea>

            </div>


        </div>

    </div>





    {{-- OWNER INFORMATION --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">


        <h3 class="text-lg font-semibold text-gray-900 mb-5">
            Owner Information
        </h3>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


            <div>

                <label class="text-sm font-medium text-gray-700">
                    Seller Name
                </label>

                <input
                    type="text"
                    value="{{ session('seller_profile.seller_name') ?? '' }}"
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm"
                >

            </div>



            <div>

                <label class="text-sm font-medium text-gray-700">
                    Email
                </label>

                <input
                    type="email"
                    value="{{ session('seller_profile.email') ?? '' }}"
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm"
                >

            </div>



            <div>

                <label class="text-sm font-medium text-gray-700">
                    Contact Number
                </label>

                <input
                    type="text"
                    value="{{ session('seller_profile.phone') ?? '' }}"
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm"
                >

            </div>


        </div>


    </div>






    {{-- ADDRESS --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">


        <h3 class="text-lg font-semibold text-gray-900 mb-5">
            Business Address
        </h3>


        <textarea
            rows="3"
            placeholder="Complete business address"
            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm"
        ></textarea>


    </div>





    {{-- BUTTON --}}
    <div class="flex justify-end">

        <button
            class="rounded-xl bg-[#1F6F5B] px-6 py-3 text-sm font-semibold text-white hover:bg-[#155244] transition"
        >

            Save Changes

        </button>

    </div>



</div>


@endsection