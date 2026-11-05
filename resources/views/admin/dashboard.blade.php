@extends('admin.layout')


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


<div class="mb-8">

<p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-[#6B7D75]">
Admin Dashboard
</p>


<h1
class="
text-3xl
font-bold
text-[#173F35]
">

{{ $greeting }}, {{ auth()->user()->name }}

</h1>


<p
class="
text-[#66736D]
mt-2
">

Review marketplace activity, pending approvals, and operational status from one place.

</p>


</div>








<!-- STAT CARDS -->


<div
class="
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









<!-- CHART SECTION -->


<div
class="
grid
grid-cols-1
xl:grid-cols-2
gap-6
mb-8
">





<!-- USER GROWTH -->


<div class="dashboard-panel">


<div
class="
flex
justify-between
items-start
mb-6
">


<div>


<h3>

User Growth

</h3>


<p>

Registered users trend

</p>


</div>



<button
class="
bg-[#F4F7F5]
px-4
py-2
rounded-xl
text-sm
text-[#66736D]
">

Last 7 Days

</button>


</div>





@php
    $growthValues = collect($userGrowth)->pluck('count')->map(fn ($value) => (int) $value)->all();
    $growthMax = max(1, ...$growthValues);
    $growthPoints = collect($growthValues)->map(function ($value, $index) use ($growthMax, $growthValues) {
        $x = count($growthValues) > 1 ? 16 + ($index * 268 / (count($growthValues) - 1)) : 150;
        $y = 100 - ($value / $growthMax * 78);
        return round($x, 1).','.round($y, 1);
    })->implode(' ');
@endphp
@if(array_sum($growthValues) > 0)
    <div class="admin-chart-scroll" role="img" aria-label="User registrations over the last seven days">
        <svg viewBox="0 0 300 130" preserveAspectRatio="none" class="admin-growth-chart"><title>User growth over the last seven days</title><path d="M16 22H284 M16 61H284 M16 100H284" class="admin-chart-grid"/><polyline points="{{ $growthPoints }}" class="admin-growth-line"/>
            @foreach($userGrowth as $index => $point)
                @php $cx = count($userGrowth) > 1 ? 16 + ($index * 268 / (count($userGrowth) - 1)) : 150; $cy = 100 - ((($point['count'] ?? 0) / $growthMax) * 78); @endphp
                <circle cx="{{ round($cx, 1) }}" cy="{{ round($cy, 1) }}" r="3.5" class="admin-growth-point"><title>{{ $point['date'] }}: {{ $point['count'] }} users</title></circle>
            @endforeach
        </svg>
    </div>
    <div class="admin-chart-labels">@foreach($userGrowth as $point)<span>{{ $point['date'] }}</span>@endforeach</div>
@else
    <div class="admin-chart-empty">No new users in the last 7 days.</div>
@endif




</div>









<!-- ORDER OVERVIEW -->


<div class="dashboard-panel">


<div
class="
flex
justify-between
items-start
mb-6
">


<div>


<h3>

Order Overview

</h3>


<p>

Current order status

</p>


</div>



<button
class="
bg-[#F4F7F5]
px-4
py-2
rounded-xl
text-sm
text-[#66736D]
">

Today

</button>


</div>





<div class="max-w-[280px] mx-auto">


@php
    $orderTotal = array_sum($orderOverview);
    $orderColors = ['pending' => '#7b8c83', 'processing' => '#82a995', 'delivered' => '#1f6f5b', 'cancelled' => '#bd806d'];
    $orderMax = max(1, ...array_values($orderOverview));
@endphp
@if($orderTotal > 0)
    <div class="admin-order-chart" aria-label="Order status distribution">
        @foreach($orderOverview as $status => $count)
            <div class="admin-order-row"><div class="admin-order-label"><span><i style="background:{{ $orderColors[$status] }}"></i>{{ ucfirst($status) }}</span><strong>{{ number_format($count) }}</strong></div><div class="admin-order-track"><span style="width:{{ round($count / $orderMax * 100, 1) }}%;background:{{ $orderColors[$status] }}"></span></div></div>
        @endforeach
    </div>
@else
    <div class="admin-chart-empty">No orders recorded yet.</div>
@endif


</div>




</div>






</div>









<!-- LOWER SECTION -->


<div
class="
grid
grid-cols-1
xl:grid-cols-2
gap-6
">








<!-- PENDING -->


<div class="dashboard-panel">


<div
class="
flex
justify-between
items-center
mb-5
">


<div>


<h3>

Pending Accounts

</h3>


<p>

Accounts waiting for approval

</p>


</div>




<button
class="
bg-[#F4F7F5]
px-4
py-2
rounded-xl
text-sm
">

View All

</button>


</div>





<div
class="
text-center
py-10
">


<h2
class="
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


<div
class="
flex
justify-between
items-center
mb-5
">


<div>


<h3>

Recent Activity

</h3>


<p>

Latest platform updates

</p>


</div>



<button
class="
bg-[#F4F7F5]
px-4
py-2
rounded-xl
text-sm
">

View All

</button>


</div>






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

if (typeof Chart === 'undefined') return;



new Chart(
document.getElementById('userGrowthChart'),
{


type:'line',


data:{


labels:@json(
collect($userGrowth)->pluck('date')
),



datasets:[{

label:'Users',


data:@json(
collect($userGrowth)->pluck('count')
),



borderColor:'#1F6F5B',

backgroundColor:'rgba(31,111,91,.12)',

fill:true,

tension:.4,


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


responsive:true,


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

border-radius:8px;

padding:20px;

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



    .stat-box, .dashboard-panel { min-width:0; border-radius:8px; background:#fffefa; box-shadow:0 3px 12px rgba(23,63,53,.045); }
    .stat-box { padding:18px 20px; }
    .stat-box p { font-size:12px; font-weight:500; }
    .stat-box h2 { margin-top:10px; font-size:clamp(1.7rem,3vw,2rem); font-weight:700; letter-spacing:-.04em; line-height:1.1; }
    .stat-box span { font-size:11px; }
    .dashboard-panel { padding:20px; }
    .dashboard-panel h3 { font-size:15px; font-weight:600; letter-spacing:-.015em; }
    .dashboard-panel p { font-size:11px; }
    .dashboard-panel button { min-height:34px; border:1px solid #e3eae6; border-radius:6px; background:#f8faf8; padding:7px 11px; color:#596c62; font-size:11px; }
    .dashboard-panel button:focus-visible { outline:2px solid #1f6f5b; outline-offset:2px; }
    .dashboard-panel canvas { max-height:270px; }
    .activity-item { border-left-width:2px; padding-left:12px; font-size:12px; font-weight:500; }
    .activity-item span { font-size:11px; }
    @media (max-width:640px) { .dashboard-panel { padding:16px; } .stat-box { padding:16px; } }

</style>





@endsection
