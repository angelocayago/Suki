@extends('superadmin.layout')


@section('title')

Orders Management

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

Orders Management

</h1>


<p class="
text-gray-500
mt-1
">

Monitor and review customer orders.

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
Total Orders
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
bg-[#EEF6FF]
rounded-2xl
border
border-[#D8E9FA]
p-6
">

<p class="text-sm text-gray-500">
Placed Orders
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['placed'] }}

</h2>


</div>







<div class="
bg-[#FFF7E8]
rounded-2xl
border
border-[#F3E4C2]
p-6
">

<p class="text-sm text-gray-500">
Processing Orders
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['processing'] }}

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
Completed Orders
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['completed'] }}

</h2>


</div>



</div>









<!-- FILTER -->


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
">


<input

type="text"

name="search"

value="{{ request('search') }}"

placeholder="Search order, buyer, seller..."

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


<option value="PLACED">
Placed
</option>


<option value="CONFIRMED">
Confirmed
</option>


<option value="PREPARING">
Preparing
</option>


<option value="DELIVERED">
Delivered
</option>


<option value="COMPLETED">
Completed
</option>


<option value="CANCELLED">
Cancelled
</option>


</select>







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

Search

</button>



</form>


</div>









<!-- TABLE -->


<div class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
overflow-hidden
">


<div class="overflow-x-auto">


<table class="w-full min-w-[950px]">


<thead

class="
bg-[#F4F7F5]
text-[11px]
uppercase
tracking-wide
text-[#607169]
"

>


<tr>


<th class="px-5 py-4 text-left">
Order ID
</th>


<th class="px-5 py-4 text-left">
Buyer
</th>


<th class="px-5 py-4 text-left">
Seller
</th>


<th class="px-5 py-4 text-left">
Amount
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


@forelse($orders as $order)


<tr class="
border-t
border-[#E7ECE9]
hover:bg-[#FBFCFB]
transition
">


<td class="
px-5
py-4
font-semibold
text-[#176B55]
text-sm
">

#{{ $order->order_number }}

</td>






<td class="px-5 py-4 text-sm">

{{ $order->buyer->name ?? '-' }}

</td>






<td class="px-5 py-4 text-sm">

{{ $order->seller->name ?? '-' }}

</td>






<td class="
px-5
py-4
text-sm
font-medium
">

₱{{ number_format($order->total_amount,2) }}

</td>







<td class="px-5 py-4">


<span class="
inline-flex
px-3
py-1
rounded-full
text-xs
font-medium

{{ in_array($order->status,['COMPLETED','DELIVERED'])
?
'bg-green-100 text-green-700'
:
(
in_array($order->status,['CANCELLED','RETURNED'])
?
'bg-red-100 text-red-700'
:
'bg-yellow-100 text-yellow-700'
)
}}

">

{{ ucfirst(strtolower($order->status)) }}

</span>


</td>







<td class="
px-5
py-4
text-sm
">

{{ $order->created_at->format('M d, Y') }}

</td>







<td class="
px-5
py-4
text-center
">


<a

href="{{ route('superadmin.orders.show',$order) }}"

class="
inline-flex
justify-center
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
transition
"

>

View Details

</a>


</td>





</tr>



@empty


<tr>


<td

colspan="7"

class="
px-6
py-12
text-center
text-sm
text-gray-500
"

>

No orders found.

</td>


</tr>


@endforelse



</tbody>



</table>


</div>



</div>







<div class="mt-6">

{{ $orders->links() }}

</div>






</div>


@endsection