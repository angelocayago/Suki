@extends('superadmin.layout')


@section('title')

Dashboard

@endsection



@section('content')


@php

$hour = now()->hour;


$greeting = match(true){

    $hour < 12 =>
        'Good morning',

    $hour < 18 =>
        'Good afternoon',

    default =>
        'Good evening'

};


@endphp






<!-- HEADER -->

<div class="flex justify-between items-start mb-8">


<div>


<h1 class="text-3xl font-bold text-slate-800">

{{ $greeting }}, {{ auth()->user()->name }} 👋

</h1>


<p class="text-gray-500 mt-2">

Here's what's happening with your platform today.

</p>


</div>





<!-- DATE -->

<div class="
bg-white
rounded-xl
border
px-5
py-3
shadow-sm
">


<p class="font-semibold text-sm">

{{ now()->format('M d, Y') }}

</p>


<p class="text-xs text-gray-500">

{{ now()->format('l') }}
</p>


</div>



</div>








<!-- KPI CARDS -->


<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">





<div class="dashboard-card">

<p>Total Users</p>

<h2>
{{ $stats['users'] }}
</h2>

<span>
↑ +0% vs last month
</span>

</div>





<div class="dashboard-card">

<p>Buyers</p>

<h2>
{{ $stats['buyers'] }}
</h2>

<span>
↑ +0% vs last month
</span>

</div>





<div class="dashboard-card">

<p>Sellers</p>

<h2>
{{ $stats['sellers'] }}
</h2>

<span>
↑ +0% vs last month
</span>

</div>





<div class="dashboard-card">

<p>Orders</p>

<h2>
{{ $stats['orders'] }}
</h2>

<span>
↑ +0% vs last month
</span>

</div>




</div>









<!-- CHART AREA -->


<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">





<!-- USER GROWTH -->

<div class="panel">


<div class="flex justify-between">


<div>

<h3>

User Growth

</h3>


<p>

Total platform users over time

</p>


</div>



<button class="filter-btn">

Last 7 Days ▾

</button>


</div>




<div class="h-64 flex items-center justify-center">


<div class="w-full">


<div class="
h-40
rounded-xl
bg-gradient-to-t
from-green-100
to-transparent
relative
">


<div class="
absolute
bottom-5
left-5
right-5
border-b-2
border-green-500
">


</div>


</div>


<div class="flex justify-between text-xs text-gray-400 mt-3">

<span>Sep 18</span>
<span>Sep 19</span>
<span>Sep 20</span>
<span>Sep 21</span>
<span>Sep 22</span>
<span>Sep 23</span>
<span>Sep 24</span>


</div>


</div>


</div>



</div>









<!-- ORDER OVERVIEW -->


<div class="panel">


<div class="flex justify-between">


<div>

<h3>

Order Overview

</h3>


<p>

Total orders and status breakdown

</p>


</div>



<button class="filter-btn">

Last 7 Days ▾

</button>


</div>






<div class="flex items-center justify-center gap-10 mt-8">


<div class="
w-36
h-36
rounded-full
bg-green-500
flex
items-center
justify-center
">


<div class="
bg-white
w-24
h-24
rounded-full
flex
flex-col
items-center
justify-center
">


<h2 class="text-2xl font-bold">

{{ $stats['orders'] }}

</h2>


<p class="text-xs">

Orders

</p>


</div>


</div>





<div class="space-y-3 text-sm">


<div>
🟢 Pending
<span class="ml-10 font-bold">2</span>
</div>


<div>
🟢 Confirmed
<span class="ml-10 font-bold">0</span>
</div>


<div>
🟡 Preparing
<span class="ml-10 font-bold">0</span>
</div>


<div>
🔵 Delivered
<span class="ml-10 font-bold">0</span>
</div>


<div>
🔴 Cancelled
<span class="ml-10 font-bold">0</span>
</div>


</div>


</div>


</div>



</div>









<!-- BOTTOM -->

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">





<!-- PENDING -->

<div class="panel">


<div class="flex justify-between">


<div>

<h3>

Pending Accounts

</h3>


<p>

Accounts waiting for approval

</p>


</div>


<button class="filter-btn">

View All

</button>


</div>





<div class="
h-40
flex
flex-col
items-center
justify-center
text-gray-500
">


<h2 class="text-4xl font-bold text-green-600">

{{ $stats['pending_users'] }}

</h2>


<p>

No pending accounts

</p>


</div>


</div>









<!-- ACTIVITY -->


<div class="panel">


<div class="flex justify-between">


<div>

<h3>

Recent Activity

</h3>


<p>

Latest platform activities

</p>


</div>


<button class="filter-btn">

View All

</button>


</div>





<div class="mt-6 space-y-5">


<div class="activity">

New order placed

<span>
2 hours ago
</span>

</div>



<div class="activity">

New user registered

<span>
4 hours ago
</span>

</div>



<div class="activity">

Seller account reviewed

<span>
5 hours ago
</span>

</div>



</div>



</div>





</div>







<style>


.dashboard-card{

background:white;
border-radius:22px;
padding:25px;
box-shadow:0 5px 20px rgba(0,0,0,.04);

}


.dashboard-card p{

color:#64748b;
font-size:14px;

}


.dashboard-card h2{

font-size:36px;
font-weight:700;
margin-top:10px;

}


.dashboard-card span{

font-size:13px;
color:#16a34a;

}




.panel{

background:white;
border-radius:24px;
padding:28px;
box-shadow:0 5px 20px rgba(0,0,0,.04);

}


.panel h3{

font-size:20px;
font-weight:700;

}


.panel p{

color:#64748b;
font-size:14px;

}



.filter-btn{

background:#f1f5f9;
padding:8px 15px;
border-radius:12px;
font-size:13px;

}



.activity{

border-left:3px solid #10b981;
padding-left:15px;
font-weight:600;

}


.activity span{

display:block;
font-size:12px;
color:#94a3b8;
font-weight:400;

}



</style>



@endsection