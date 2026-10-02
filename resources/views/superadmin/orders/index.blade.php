@extends('superadmin.layout')


@section('title')

Orders Management

@endsection



@section('content')


<div class="w-full">



<!-- HEADER -->


<div class="mb-8">


<h1 class="
text-3xl
font-bold
text-[#173F35]
">

Orders Management

</h1>


<p class="text-gray-500 mt-2">

Monitor and review customer orders.

</p>


</div>









<!-- STAT CARDS -->


<div class="
grid
grid-cols-1
md:grid-cols-4
gap-5
mb-8
">



<div
class="
bg-[#FFF7E8]
rounded-3xl
border
border-[#F3E4C2]
p-6
">

<p class="text-sm text-gray-500">
Total Orders
</p>


<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['total'] }}

</h2>


</div>







<div
class="
bg-[#EEF6FF]
rounded-3xl
border
border-[#D8E9FA]
p-6
">

<p class="text-sm text-gray-500">
Placed Orders
</p>


<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['placed'] }}

</h2>


</div>







<div
class="
bg-[#FFF7E8]
rounded-3xl
border
border-[#F3E4C2]
p-6
">

<p class="text-sm text-gray-500">
Processing Orders
</p>


<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['processing'] }}

</h2>


</div>







<div
class="
bg-[#EAFBF3]
rounded-3xl
border
border-[#D3F1E1]
p-6
">

<p class="text-sm text-gray-500">
Completed Orders
</p>


<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['completed'] }}

</h2>


</div>



</div>









<!-- FILTER -->


<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-6
mb-6
">


<form method="GET"

class="
grid
md:grid-cols-3
gap-4
">


<input

type="text"

name="search"

value="{{ request('search') }}"

placeholder="Search order, buyer, seller..."

class="
border
rounded-xl
px-4
py-3
focus:outline-none
focus:ring-2
focus:ring-[#1F6F5B]
"

>




<select

name="status"

class="
border
rounded-xl
px-4
py-3
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


</select>






<button

class="
bg-[#1F6F5B]
text-white
rounded-xl
font-medium
hover:bg-[#155244]
"

>

Search

</button>


</form>


</div>









<!-- TABLE -->


<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
overflow-hidden
">


<table class="w-full">


<thead

class="
bg-[#F8FAF9]
text-sm
text-gray-500
"

>


<tr>


<th class="p-5 text-left">
Order ID
</th>


<th class="p-5 text-left">
Buyer
</th>


<th class="p-5 text-left">
Seller
</th>


<th class="p-5 text-left">
Amount
</th>


<th class="p-5 text-left">
Status
</th>


<th class="p-5 text-left">
Date
</th>


<th class="p-5 text-left">
Action
</th>


</tr>


</thead>







<tbody>


@forelse($orders as $order)


<tr

class="
border-t
border-[#E3EAE6]
"

>


<td class="p-5">


<p class="
font-semibold
text-[#173F35]
">

#{{ $order->order_number }}

</p>


</td>







<td class="p-5">

{{ $order->buyer->name ?? '-' }}

</td>







<td class="p-5">

{{ $order->seller->name ?? '-' }}

</td>







<td class="p-5">

₱{{ number_format($order->total_amount,2) }}

</td>







<td class="p-5">


<span

class="
px-3
py-1
rounded-full
text-sm

{{ in_array($order->status,['COMPLETED','DELIVERED'])
? 'bg-green-100 text-green-700'
:
(
in_array($order->status,['CANCELLED','RETURNED'])
? 'bg-red-100 text-red-700'
:
'bg-yellow-100 text-yellow-700'
)

}}

"

>

{{ $order->status }}

</span>


</td>







<td class="p-5">

{{ $order->created_at->format('M d, Y') }}

</td>







<td class="p-5">


<a

href="{{ route('superadmin.orders.show',$order) }}"

class="
px-4
py-2
rounded-xl
border
border-[#1F6F5B]
text-[#1F6F5B]
text-sm
font-medium
hover:bg-[#1F6F5B]
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

<td colspan="7"

class="
p-8
text-center
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







<div class="mt-6">

{{ $orders->links() }}

</div>







</div>


@endsection