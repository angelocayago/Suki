@extends('superadmin.layout')


@section('title')
Dashboard
@endsection



@section('content')


@php

$hour = now()->hour;


$greeting =
    $hour < 12
        ? 'Good morning'
        : (
            $hour < 18
                ? 'Good afternoon'
                : 'Good evening'
        );


@endphp






<!-- HEADER -->

<div class="flex justify-between items-start mb-8">


<div>


<h1 class="text-3xl font-bold text-slate-800">

{{ $greeting }},
{{ auth()->user()->name }}

!

</h1>


<p class="text-gray-500 mt-2">

Here's what's happening with your platform today.

</p>


</div>





<div class="
bg-white
border
rounded-xl
px-5
py-3
shadow-sm
">


<p class="text-sm font-semibold">

{{ now()->format('M d, Y') }}

</p>


<p class="text-xs text-gray-500">

{{ now()->format('l') }}

</p>


</div>


</div>









<!-- KPI -->

<div class="
grid
grid-cols-1
md:grid-cols-2
xl:grid-cols-4
gap-5
mb-8
">





<div class="stat-card">

<p>
Total Users
</p>


<h2>

{{ $stats['users'] }}

</h2>


<span>
Platform users
</span>


</div>





<div class="stat-card">

<p>
Buyers
</p>


<h2>

{{ $stats['buyers'] }}

</h2>


<span>
Registered buyers
</span>


</div>





<div class="stat-card">

<p>
Sellers
</p>


<h2>

{{ $stats['sellers'] }}

</h2>


<span>
Active sellers
</span>


</div>





<div class="stat-card">

<p>
Orders
</p>


<h2>

{{ $stats['orders'] }}

</h2>


<span>
Total transactions
</span>


</div>





</div>









<!-- ANALYTICS -->


<div class="
grid
grid-cols-1
xl:grid-cols-2
gap-6
mb-8
">





<!-- USER GROWTH -->


<div class="panel">


<div class="flex justify-between">


<div>


<h3>

User Growth

</h3>


<p>

Total registered users

</p>


</div>



<button class="date-btn">

Last 7 Days

</button>


</div>





<div class="
h-64
mt-6
flex
items-end
gap-4
">


@for($i = 1; $i <= 7; $i++)

<div class="
flex-1
bg-green-100
rounded-t-xl
relative
"
style="height:{{20+$i*10}}%">
</div>

@endfor


</div>





<div class="
flex
justify-between
text-xs
text-gray-400
mt-3
">

<span>Mon</span>
<span>Tue</span>
<span>Wed</span>
<span>Thu</span>
<span>Fri</span>
<span>Sat</span>
<span>Sun</span>


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

Current order status

</p>


</div>


<button class="date-btn">

Today

</button>


</div>






<div class="
flex
items-center
justify-center
gap-10
mt-10
">



<div
class="
w-40
h-40
rounded-full
border-[18px]
border-green-500
flex
items-center
justify-center
">


<div class="text-center">


<h2 class="text-3xl font-bold">

{{ $stats['orders'] }}

</h2>


<p class="text-xs text-gray-500">

Orders

</p>


</div>


</div>





<div class="space-y-4 text-sm">


<div>
<span class="text-green-600">
●
</span>

Pending

</div>


<div>
<span class="text-blue-600">
●
</span>

Processing

</div>


<div>
<span class="text-gray-400">
●
</span>

Completed

</div>



</div>



</div>



</div>






</div>









<!-- BOTTOM -->

<div class="
grid
grid-cols-1
xl:grid-cols-2
gap-6
">





<div class="panel">


<div class="flex justify-between mb-5">


<h3>

Pending Accounts

</h3>


<button class="date-btn">

View All

</button>


</div>



<div class="
text-center
py-12
text-gray-400
">


<h2 class="
text-5xl
font-bold
text-green-600
">

{{ $stats['pending_users'] }}

</h2>


<p class="mt-2">

Pending approvals

</p>


</div>



</div>








<div class="panel">


<h3>

Recent Activity

</h3>



<div class="mt-6 space-y-5">


<div class="activity">

New buyer registered

<span>
Today
</span>

</div>



<div class="activity">

Order created

<span>
Today
</span>

</div>




<div class="activity">

Seller application submitted

<span>
Yesterday
</span>

</div>



</div>



</div>




</div>









<style>


.stat-card{

background:white;
border-radius:22px;
padding:24px;
box-shadow:0 5px 18px rgba(0,0,0,.04);

}


.stat-card p{

color:#64748b;
font-size:14px;

}


.stat-card h2{

font-size:38px;
font-weight:800;
margin-top:10px;

}


.stat-card span{

font-size:13px;
color:#94a3b8;

}



.panel{

background:white;
border-radius:24px;
padding:28px;
box-shadow:0 5px 18px rgba(0,0,0,.04);

}


.panel h3{

font-size:20px;
font-weight:700;

}


.panel p{

color:#64748b;

}



.date-btn{

background:#f1f5f9;
padding:8px 16px;
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
font-weight:400;
color:#94a3b8;

}



</style>



@endsection