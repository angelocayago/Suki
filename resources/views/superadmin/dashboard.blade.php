@extends('superadmin.layout')


@section('title')

Dashboard Overview

@endsection



@section('content')


<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">



    <div class="bg-white rounded-xl shadow p-6">

        <p class="text-gray-500">
            Total Users
        </p>

        <h3 class="text-3xl font-bold mt-2">
            {{ $stats['users'] }}
        </h3>

    </div>




    <div class="bg-white rounded-xl shadow p-6">

        <p class="text-gray-500">
            Buyers
        </p>

        <h3 class="text-3xl font-bold mt-2">
            {{ $stats['buyers'] }}
        </h3>

    </div>




    <div class="bg-white rounded-xl shadow p-6">

        <p class="text-gray-500">
            Sellers
        </p>

        <h3 class="text-3xl font-bold mt-2">
            {{ $stats['sellers'] }}
        </h3>

    </div>




    <div class="bg-white rounded-xl shadow p-6">

        <p class="text-gray-500">
            Orders
        </p>

        <h3 class="text-3xl font-bold mt-2">
            {{ $stats['orders'] }}
        </h3>

    </div>



</div>




<div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">


    <div class="bg-white rounded-xl shadow p-6">


        <h3 class="font-semibold text-lg mb-4">

            Pending Accounts

        </h3>


        <div class="text-4xl font-bold text-yellow-500">

            {{ $stats['pending_users'] }}

        </div>


        <p class="text-gray-500 mt-2">

            Waiting for approval

        </p>


    </div>




    <div class="bg-white rounded-xl shadow p-6">


        <h3 class="font-semibold text-lg mb-4">

            Platform Monitoring

        </h3>


        <p class="text-gray-600">

            Monitor users, sellers, orders,
            compliance, reports and commissions.

        </p>


    </div>



</div>



@endsection