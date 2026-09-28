@extends('superadmin.layout')


@section('title')

Seller Details

@endsection



@section('content')


<div class="w-full">



<!-- BACK -->

<a href="{{ route('superadmin.sellers') }}"
class="
inline-flex
items-center
mb-6
text-sm
font-medium
text-[#1F6F5B]
hover:text-[#155244]
hover:underline
">

← Back to Sellers

</a>









<!-- SELLER HEADER -->


<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
mb-6
">


<div class="
flex
flex-col
md:flex-row
justify-between
gap-6
">



<div class="flex items-center gap-5">


<div
class="
w-24
h-24
rounded-full
bg-[#DDF3EC]
text-[#1F6F5B]
flex
items-center
justify-center
text-4xl
font-bold
">

{{ strtoupper(substr($seller->owner->name ?? 'S',0,1)) }}

</div>





<div>


<h1
class="
text-3xl
font-bold
text-[#173F35]
">

{{ $seller->owner->name ?? '-' }}

</h1>



<p class="text-gray-500">

{{ $seller->owner->email ?? '-' }}

</p>




<div class="flex gap-3 mt-3">


<span
class="
px-4
py-2
rounded-full
bg-[#DDF3EC]
text-[#1F6F5B]
text-sm
font-medium
">

Seller

</span>





<span
class="
px-4
py-2
rounded-full
text-sm
font-medium

{{ $seller->status === 'approved'
? 'bg-green-100 text-green-700'
:
(
$seller->status === 'suspended'
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



</div>



</div>


</div>








<!-- ACTION BUTTONS -->


<div class="flex gap-3">


@if($seller->status === 'approved')


<form method="POST"
action="{{ route('superadmin.sellers.status',$seller) }}">

@csrf


<input
type="hidden"
name="status"
value="suspended"
>


<button
class="
px-5
py-3
rounded-xl
bg-red-100
text-red-700
text-sm
font-medium
hover:bg-red-200
">

Suspend Seller

</button>


</form>


@elseif($seller->status === 'suspended')


<form method="POST"
action="{{ route('superadmin.sellers.status',$seller) }}">

@csrf


<input
type="hidden"
name="status"
value="approved"
>


<button
class="
px-5
py-3
rounded-xl
bg-green-100
text-green-700
text-sm
font-medium
hover:bg-green-200
">

Activate Seller

</button>


</form>


@endif



</div>




</div>


</div>









<!-- INFORMATION -->


<div
class="
grid
md:grid-cols-2
gap-6
">








<!-- OWNER INFORMATION -->


<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
">


<h2
class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Owner Information

</h2>




<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Full Name
</p>

<p class="font-semibold">

{{ $seller->owner->name ?? '-' }}

</p>

</div>




<div>

<p class="text-sm text-gray-500">
Email
</p>

<p class="font-semibold">

{{ $seller->owner->email ?? '-' }}

</p>

</div>




<div>

<p class="text-sm text-gray-500">
Phone
</p>

<p class="font-semibold">

{{ $seller->owner->phone ?? '-' }}

</p>

</div>



</div>



</div>









<!-- SHOP INFORMATION -->


<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
">


<h2
class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Shop Information

</h2>




<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Shop Name
</p>

<p class="font-semibold">

{{ $seller->name }}

</p>

</div>




<div>

<p class="text-sm text-gray-500">
Description
</p>

<p class="font-semibold">

{{ $seller->description ?? '-' }}

</p>

</div>




<div>

<p class="text-sm text-gray-500">
Commission
</p>

<p class="font-semibold">

{{ $seller->commission_bps }} bps

</p>

</div>



</div>



</div>






</div>









<!-- STORE SUMMARY -->


<div
class="
mt-6
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
">


<h2
class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Store Summary

</h2>




<div class="
grid
md:grid-cols-3
gap-5
">



<div
class="
bg-[#F8FAF9]
rounded-2xl
p-5
">

<p class="text-sm text-gray-500">
Products
</p>


<p class="
text-3xl
font-bold
text-[#173F35]
">

{{ $seller->products->count() }}

</p>


</div>





<div
class="
bg-[#F8FAF9]
rounded-2xl
p-5
">

<p class="text-sm text-gray-500">
Orders
</p>


<p class="
text-3xl
font-bold
text-[#173F35]
">

{{ $seller->orders->count() }}

</p>


</div>





<div
class="
bg-[#F8FAF9]
rounded-2xl
p-5
">

<p class="text-sm text-gray-500">
Joined Date
</p>


<p class="
font-bold
text-[#173F35]
">

{{ $seller->created_at->format('M d, Y') }}

</p>


</div>



</div>



</div>







</div>


@endsection