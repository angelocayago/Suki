@extends('superadmin.layout')


@section('title')

Seller Management

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

Seller Management

</h1>


<p class="text-gray-500 mt-2">

Manage seller accounts, shops, and seller status.

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



<div class="
bg-[#FFF7E8]
rounded-3xl
border
border-[#F3E4C2]
p-6
">

<p class="text-sm text-gray-500">
Total Sellers
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






<div class="
bg-[#EAFBF3]
rounded-3xl
border
border-[#D3F1E1]
p-6
">

<p class="text-sm text-gray-500">
Active Sellers
</p>

<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['active'] }}

</h2>

</div>






<div class="
bg-[#EEF6FF]
rounded-3xl
border
border-[#D8E9FA]
p-6
">

<p class="text-sm text-gray-500">
Pending Sellers
</p>

<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['pending'] }}

</h2>

</div>






<div class="
bg-[#FFF0F0]
rounded-3xl
border
border-[#F6D4D4]
p-6
">

<p class="text-sm text-gray-500">
Suspended Sellers
</p>

<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['suspended'] }}

</h2>

</div>



</div>









<!-- FILTER -->

<div class="
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
placeholder="Search seller or shop..."
class="
border
rounded-xl
px-4
py-3
focus:outline-none
focus:ring-2
focus:ring-[#1F6F5B]
">





<select
name="status"
class="
border
rounded-xl
px-4
py-3
">


<option value="">
All Status
</option>


<option value="active"
{{ request('status') === 'active' ? 'selected' : '' }}
>
Active
</option>


<option value="pending"
{{ request('status') === 'pending' ? 'selected' : '' }}
>
Pending
</option>


<option value="suspended"
{{ request('status') === 'suspended' ? 'selected' : '' }}
>
Suspended
</option>


</select>






<button
class="
bg-[#1F6F5B]
text-white
rounded-xl
font-medium
hover:bg-[#155244]
">

Search

</button>



</form>


</div>









<!-- SELLER TABLE -->


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
">


<tr>


<th class="p-5 text-left">
Seller
</th>


<th class="p-5 text-left">
Shop Name
</th>


<th class="p-5 text-left">
Email
</th>


<th class="p-5 text-left">
Status
</th>


<th class="p-5 text-left">
Products
</th>


<th class="p-5 text-left">
Joined
</th>


<th class="p-5 text-left">
Action
</th>


</tr>


</thead>







<tbody>


@forelse($sellers as $seller)


<tr class="
border-t
border-[#E3EAE6]
">



<td class="p-5">


<p class="
font-semibold
text-[#173F35]
">

{{ $seller->owner->name ?? '-' }}

</p>


</td>






<td class="p-5">

{{ $seller->name }}

</td>






<td class="p-5">

{{ $seller->owner->email ?? '-' }}

</td>






<td class="p-5">


<span
class="
px-3
py-1
rounded-full
text-sm

{{ $seller->status === 'approved'
? 'bg-green-100 text-green-700'
:
(
$seller->status === 'suspended'
|| $seller->status === 'rejected'
? 'bg-red-100 text-red-700'
:
'bg-yellow-100 text-yellow-700'
)

}}
">

{{ $seller->status === 'approved'
    ? 'Active'
    : ucfirst($seller->status)
}}

</span>


</td>






<td class="p-5">

{{ $seller->products->count() }}

</td>






<td class="p-5">

{{ $seller->created_at->format('M d, Y') }}

</td>






<td class="p-5">


<a
href="{{ route('superadmin.sellers.show',$seller) }}"
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
">

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
">

No sellers found.

</td>

</tr>


@endforelse



</tbody>


</table>


</div>







<div class="mt-6">

{{ $sellers->links() }}

</div>







</div>


@endsection