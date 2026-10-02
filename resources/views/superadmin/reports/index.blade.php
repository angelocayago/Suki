@extends('superadmin.layout')


@section('title')

Reports

@endsection



@section('content')


<div class="w-full">



<!-- HEADER -->

<div class="
mb-7
flex
flex-col
lg:flex-row
lg:items-end
lg:justify-between
gap-5
">


<div>

<h1 class="
text-3xl
font-bold
text-[#173F35]
">

Reports

</h1>


<p class="
text-gray-500
mt-1
">

View sales performance and commission reports.

</p>


</div>






<!-- DATE FILTER -->


<form

method="GET"

class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
p-4
grid
grid-cols-1
md:grid-cols-[180px_180px_130px]
gap-3
"

>


<input

type="date"

name="from"

value="{{ request('from') }}"

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

type="date"

name="to"

value="{{ request('to') }}"

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
font-semibold
text-sm
hover:bg-[#155244]
transition
"

>

Generate

</button>


</form>


</div>









<!-- REPORT SUMMARY -->


<div class="
bg-[#FFFDF8]
rounded-2xl
border
border-[#EDE4D4]
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

Report Summary

</h2>



<div class="
grid
grid-cols-1
sm:grid-cols-2
xl:grid-cols-5
gap-6
">



<div>

<p class="text-sm text-gray-500">
Total Sales
</p>


<p class="
text-2xl
font-bold
text-[#173F35]
mt-2
">

₱{{ number_format($totalSales,2) }}

</p>

</div>





<div>

<p class="text-sm text-gray-500">
Total Orders
</p>


<p class="
text-2xl
font-bold
text-[#173F35]
mt-2
">

{{ $totalOrders }}

</p>

</div>





<div>

<p class="text-sm text-gray-500">
Completed Orders
</p>


<p class="
text-2xl
font-bold
text-[#173F35]
mt-2
">

{{ $completedOrders }}

</p>

</div>





<div>

<p class="text-sm text-gray-500">
Platform Commission
</p>


<p class="
text-2xl
font-bold
text-[#173F35]
mt-2
">

₱{{ number_format($commissionMinor / 100,2) }}

</p>

</div>





<div>

<p class="text-sm text-gray-500">
Seller Earnings
</p>


<p class="
text-2xl
font-bold
text-[#173F35]
mt-2
">

₱{{ number_format($sellerNetMinor / 100,2) }}

</p>

</div>



</div>


</div>









<!-- SALES PERFORMANCE -->


<div class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
overflow-hidden
mb-5
">


<div class="p-6">


<h2 class="
text-xl
font-bold
text-[#173F35]
">

Sales Performance

</h2>


<p class="
text-sm
text-gray-500
mt-1
">

Daily sales summary based on recorded orders.

</p>


</div>





<div class="overflow-x-auto">


<table class="
w-full
min-w-[700px]
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
Date
</th>


<th class="px-5 py-4 text-left">
Orders
</th>


<th class="px-5 py-4 text-left">
Sales
</th>


<th class="px-5 py-4 text-left">
Average Order Value
</th>

</tr>


</thead>




<tbody>


@forelse($salesPerformance as $row)


<tr class="
border-t
border-[#E7ECE9]
">


<td class="px-5 py-4 text-sm">

{{ \Carbon\Carbon::parse($row->date)->format('M d, Y') }}

</td>


<td class="px-5 py-4 text-sm">

{{ $row->orders }}

</td>


<td class="px-5 py-4 text-sm font-medium">

₱{{ number_format($row->sales,2) }}

</td>


<td class="px-5 py-4 text-sm">

₱{{ number_format($row->sales / $row->orders,2) }}

</td>


</tr>


@empty


<tr>

<td colspan="4"

class="
px-6
py-12
text-center
text-sm
text-gray-500
">

No sales data available.

</td>

</tr>


@endforelse



</tbody>


</table>


</div>


</div>









<!-- SELLER PERFORMANCE -->


<div class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
overflow-hidden
">


<div class="p-6">


<h2 class="
text-xl
font-bold
text-[#173F35]
">

Seller Performance

</h2>


<p class="
text-sm
text-gray-500
mt-1
">

Seller activity, sales, and commission performance.

</p>


</div>





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
Seller
</th>


<th class="px-5 py-4 text-left">
Owner
</th>


<th class="px-5 py-4 text-left">
Orders
</th>


<th class="px-5 py-4 text-left">
Sales
</th>


<th class="px-5 py-4 text-left">
Commission Rate
</th>


<th class="px-5 py-4 text-left">
Commission Earned
</th>


<th class="px-5 py-4 text-left">
Status
</th>


</tr>


</thead>





<tbody>


@forelse($sellerPerformance as $seller)


<tr class="
border-t
border-[#E7ECE9]
">


<td class="
px-5
py-4
text-sm
font-semibold
">

{{ $seller->name }}

</td>


<td class="
px-5
py-4
text-sm
">

{{ $seller->owner->name ?? '-' }}

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
text-sm
">

₱{{ number_format(($seller->sales_total_minor ?? 0) / 100,2) }}

</td>


<td class="
px-5
py-4
text-sm
">

{{ number_format($seller->commission_bps / 100,2) }}%

</td>


<td class="
px-5
py-4
text-sm
">

₱{{ number_format(($seller->commission_total_minor ?? 0) / 100,2) }}

</td>


<td class="
px-5
py-4
text-sm
">

{{ ucfirst($seller->status) }}

</td>


</tr>


@empty


<tr>

<td colspan="7"

class="
px-6
py-12
text-center
text-sm
text-gray-500
">

No seller data available.

</td>

</tr>


@endforelse



</tbody>


</table>


</div>


</div>







</div>


@endsection