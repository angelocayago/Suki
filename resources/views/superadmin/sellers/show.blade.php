@extends('superadmin.layout')


@section('title')

Seller Details

@endsection



@section('content')


<div class="w-full">



<!-- BACK -->

<a
href="{{ route('superadmin.sellers') }}"
class="
inline-flex
items-center
mb-6
text-sm
font-medium
text-[#1F6F5B]
hover:text-[#155244]
hover:underline
transition
"
>

← Back to Sellers

</a>







<!-- PROFILE HEADER -->


<div
class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
p-6
mb-5
"
>


<div class="
flex
flex-col
md:flex-row
md:items-center
justify-between
gap-5
"
>



<div class="flex items-center gap-5">



<div
class="
w-20
h-20
rounded-full
bg-[#DDF3EC]
text-[#1F6F5B]
flex
items-center
justify-center
text-3xl
font-bold
"
>

{{ strtoupper(substr($seller->owner->name ?? 'S',0,1)) }}

</div>






<div>


<h1 class="
text-3xl
font-bold
text-[#173F35]
">

{{ $seller->owner->name ?? '-' }}

</h1>



<p class="
text-gray-500
mt-1
">

{{ $seller->owner->email ?? '-' }}

</p>



<div class="
flex
gap-3
mt-3
">


<span
class="
px-3
py-1
rounded-full
text-xs
font-medium
bg-[#DDF3EC]
text-[#1F6F5B]
"
>

Seller

</span>






<span
class="
px-3
py-1
rounded-full
text-xs
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

"
>

{{ $seller->status === 'approved'
    ? 'Active'
    : ucfirst($seller->status)
}}

</span>



</div>



</div>


</div>








<!-- ACTION -->

<div>


@if($seller->status === 'approved')

<a
href="{{ route('superadmin.compliance.create', ['seller_id' => $seller->id]) }}"
class="inline-block px-5 py-3 rounded-xl bg-red-100 text-red-700 text-sm font-semibold hover:bg-red-200 transition"
>
Open Compliance Case to Suspend
</a>




@elseif($seller->status === 'suspended')


<form
method="POST"
action="{{ route('superadmin.sellers.status',$seller) }}"
>


@csrf
@method('PATCH')


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
font-semibold
hover:bg-green-200
transition
"
>

Activate Seller

</button>


</form>


@endif



</div>



<div class="mt-5 rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-[#173F35]">Seller Compliance</h2>
            <p class="mt-1 text-sm text-gray-500">Review this seller's violation and warning history.</p>
        </div>
        <a href="{{ route('superadmin.compliance.create', ['seller_id' => $seller->id]) }}"
           class="rounded-xl bg-[#173F35] px-5 py-2.5 text-center text-sm font-semibold text-white">
            Create compliance case
        </a>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <a href="{{ route('superadmin.compliance.index', ['seller_id' => $seller->id]) }}" class="rounded-xl bg-[#F6F9F7] p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">All cases</div>
            <div class="mt-2 text-2xl font-bold text-[#173F35]">{{ $seller->violations_count }}</div>
        </a>
        <a href="{{ route('superadmin.compliance.index', ['seller_id' => $seller->id, 'status' => 'OPEN']) }}" class="rounded-xl bg-amber-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Open cases</div>
            <div class="mt-2 text-2xl font-bold text-amber-800">{{ $seller->open_violations_count }}</div>
        </a>
        <div class="rounded-xl bg-[#F6F9F7] p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Warnings</div>
            <div class="mt-2 text-2xl font-bold text-[#173F35]">{{ $seller->warnings_count }}</div>
        </div>
    </div>
</div>





</div>


</div>









<!-- INFORMATION GRID -->


<div
class="
grid
grid-cols-1
lg:grid-cols-2
gap-5
"
>




<!-- OWNER -->

<div
class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
p-6
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-5
">

Owner Information

</h2>



<div class="space-y-4">


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








<!-- SHOP -->


<div
class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
p-6
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-5
">

Shop Information

</h2>



<div class="space-y-4">


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
Commission Rate
</p>

<p class="font-semibold">

{{ number_format($seller->commission_bps / 100,2) }}%

</p>

</div>



</div>



</div>








<!-- STORE SUMMARY -->


<div
class="
mt-5
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
p-6
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-5
">

Store Summary

</h2>




<div class="
grid
grid-cols-1
md:grid-cols-3
gap-4
">





<div
class="
bg-[#FFF7E8]
rounded-xl
border
border-[#F3E4C2]
p-5
"
>

<p class="text-sm text-gray-500">

Products

</p>


<p class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $seller->products->count() }}

</p>


</div>








<div
class="
bg-[#EEF6FF]
rounded-xl
border
border-[#D8E9FA]
p-5
"
>

<p class="text-sm text-gray-500">

Orders

</p>


<p class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $seller->orders->count() }}

</p>


</div>








<div
class="
bg-[#EAFBF3]
rounded-xl
border
border-[#D3F1E1]
p-5
"
>

<p class="text-sm text-gray-500">

Joined Date

</p>


<p class="
text-lg
font-bold
text-[#173F35]
mt-2
">

{{ $seller->created_at->format('M d, Y') }}

</p>


</div>





</div>



</div>









<!-- SELLER STATUS -->


<div
class="
mt-5
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
p-6
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-5
">

Account Status

</h2>




<div class="space-y-4">



<div>

<p class="text-sm text-gray-500">

Current Status

</p>


<p class="font-semibold">

{{ $seller->status === 'approved'
    ? 'Active'
    : ucfirst($seller->status)
}}

</p>


</div>





@if($seller->rejection_reason)


<div>

<p class="text-sm text-gray-500">

Reason

</p>


<p class="font-semibold text-red-600">

{{ $seller->rejection_reason }}

</p>


</div>


@endif





<div>

<p class="text-sm text-gray-500">

Seller ID

</p>


<p class="font-semibold">

#SELLER-{{ str_pad($seller->id,5,'0',STR_PAD_LEFT) }}

</p>


</div>



</div>



</div>





<div class="mt-5 rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
    <h2 class="text-xl font-bold text-[#173F35]">Allowed Product Categories</h2>
    <p class="mt-1 text-sm text-gray-500">Explicitly select categories this seller is approved to list. No registration category is mapped automatically.</p>

    <form method="POST" action="{{ route('superadmin.sellers.allowed-categories.update', $seller) }}" class="mt-5">
        @csrf
        @method('PUT')
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($categories as $category)
                <label class="flex items-center gap-3 rounded-xl border border-[#E3EAE6] p-3 text-sm">
                    <input type="checkbox" name="categories[]" value="{{ $category->id }}"
                           @checked($seller->allowedCategories->contains($category->id))
                           class="rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]">
                    <span>{{ $category->name }}</span>
                </label>
            @endforeach
        </div>
        <button class="mt-4 rounded-xl bg-[#173F35] px-5 py-2.5 text-sm font-semibold text-white">Save allowed categories</button>
    </form>
</div>





</div>


@endsection
