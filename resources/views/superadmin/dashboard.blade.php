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

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-5 mb-8">


<div>

<h1 class="text-3xl font-bold text-[#173F35]">

{{ $greeting }}, {{ auth()->user()->name }} 👋

</h1>


<p class="text-[#66736D] mt-2">

Here's what's happening with your platform today.

</p>


</div>




<div class="
bg-white
border
border-[#E3EAE6]
rounded-2xl
px-5
py-3
shadow-sm
">


<p class="font-semibold text-[#173F35]">

{{ now()->format('M d, Y') }}

</p>


<p class="text-sm text-[#66736D]">

{{ now()->format('l') }}

</p>


</div>


</div>









<!-- STATS -->


<div class="
grid
grid-cols-1
sm:grid-cols-2
xl:grid-cols-4
gap-6
mb-8
">





<div class="stat-box">


<p>
Total Users
</p>


<h2>

{{ $stats['users'] }}

</h2>


<span>
Registered accounts
</span>


</div>





<div class="stat-box">


<p>
Buyers
</p>


<h2>

{{ $stats['buyers'] }}

</h2>


<span>
Active buyers
</span>


</div>






<div class="stat-box">


<p>
Sellers
</p>


<h2>

{{ $stats['sellers'] }}

</h2>


<span>
Seller accounts
</span>


</div>







<div class="stat-box">


<p>
Orders
</p>


<h2>

{{ $stats['orders'] }}

</h2>


<span>
Platform orders
</span>


</div>






</div>









<!-- CHARTS -->


<div class="
grid
grid-cols-1
xl:grid-cols-2
gap-6
mb-8
">





<!-- USER GROWTH -->


<div class="dashboard-panel">


<div class="mb-5">


<h3>
User Growth
</h3>


<p>
Registered users trend
</p>


</div>



<canvas id="userGrowthChart"
height="120">
</canvas>



</div>








<!-- ORDER OVERVIEW -->


<div class="dashboard-panel">


<div class="mb-5">


<h3>
Order Overview
</h3>


<p>
Current order status
</p>


</div>




<div class="max-w-[280px] mx-auto">

<canvas id="orderChart"></canvas>

</div>



</div>







</div>









<!-- LOWER SECTION -->


<div class="
grid
grid-cols-1
xl:grid-cols-2
gap-6
">





<!-- PENDING -->


<div class="dashboard-panel">


<h3>

Pending Accounts

</h3>


<p class="mb-6">

Waiting for approval

</p>



<div class="
text-center
py-8
">


<h2 class="
text-5xl
font-bold
text-[#1F6F5B]
">

{{ $stats['pending_users'] }}

</h2>


<p class="text-[#66736D] mt-2">

Pending requests

</p>


</div>


</div>









<!-- ACTIVITY -->


<div class="dashboard-panel">


<h3>

Recent Activity

</h3>


<p class="mb-6">

Latest platform updates

</p>




<div class="space-y-5">


<div class="activity-item">

New user registered

<span>
Today
</span>

</div>



<div class="activity-item">

New order created

<span>
Today
</span>

</div>



<div class="activity-item">

Seller application submitted

<span>
Yesterday
</span>

</div>



</div>



</div>





</div>








<script>

document.addEventListener(
'DOMContentLoaded',
()=>{


new Chart(
document.getElementById('userGrowthChart'),
{


type:'line',


data:{


labels:@json(
collect($userGrowth)
->pluck('date')
),



datasets:[{


label:'Users',


data:@json(
collect($userGrowth)
->pluck('count')
),



borderColor:'#1F6F5B',


backgroundColor:'rgba(31,111,91,.15)',


fill:true,


tension:.4


}]


},


options:{


responsive:true,


plugins:{


legend:{


display:false


}


},


scales:{


y:{


beginAtZero:true


}


}


}


}

);







new Chart(

document.getElementById('orderChart'),

{


type:'doughnut',


data:{


labels:[

'Pending',
'Processing',
'Delivered',
'Cancelled'

],


datasets:[{


data:@json(
array_values($orderOverview)
),


backgroundColor:[

'#1F6F5B',
'#86EFAC',
'#34D399',
'#CBD5E1'

]


}]


},


options:{


cutout:'70%',


plugins:{


legend:{


position:'bottom'


}


}


}


}

);



}

);


</script>








<style>


.stat-box{


background:white;

border:1px solid #E3EAE6;

border-radius:22px;

padding:25px;

box-shadow:
0 10px 30px rgba(23,63,53,.055);


}


.stat-box p{

color:#66736D;

font-size:14px;

}


.stat-box h2{

font-size:38px;

font-weight:800;

color:#173F35;

margin-top:12px;

}


.stat-box span{

font-size:13px;

color:#728078;

}




.dashboard-panel{


background:white;

border:1px solid #E3EAE6;

border-radius:24px;

padding:28px;

box-shadow:
0 10px 30px rgba(23,63,53,.055);


}


.dashboard-panel h3{


font-size:20px;

font-weight:700;

color:#173F35;


}


.dashboard-panel p{

color:#66736D;

font-size:14px;

}



.activity-item{


border-left:3px solid #1F6F5B;

padding-left:15px;

font-weight:600;


}


.activity-item span{


display:block;

font-size:12px;

font-weight:400;

color:#728078;


}



</style>



@endsection