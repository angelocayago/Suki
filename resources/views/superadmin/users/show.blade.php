@extends('superadmin.layout')


@section('title')

User Profile

@endsection



@section('content')


<div class="max-w-5xl">



<!-- BACK BUTTON -->

<a href="{{ url('/superadmin/users') }}"
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
">

← Back to Users

</a>







<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
">






<!-- PROFILE HEADER -->


<div class="
flex
flex-col
md:flex-row
md:items-center
justify-between
gap-5
">



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
">


{{ strtoupper(substr($user->name,0,1)) }}


</div>





<div>


<h1
class="
text-3xl
font-bold
text-[#173F35]
">

{{ $user->name }}

</h1>


<p class="text-gray-500">

{{ $user->email }}

</p>


</div>



</div>







<!-- STATUS -->


<span
class="
px-4
py-2
rounded-full
text-sm
font-medium

{{ $user->status === 'active'
? 'bg-green-100 text-green-700'
:
(
$user->status === 'suspended'
? 'bg-red-100 text-red-700'
:
'bg-gray-100 text-gray-700'
)
}}

">


{{ ucfirst($user->status) }}


</span>






</div>







<hr class="my-8">







<!-- INFORMATION -->


<div
class="
grid
md:grid-cols-2
gap-8
">






<div>


<p class="text-sm text-gray-500">
Role
</p>


<p class="font-semibold text-[#173F35]">

{{ ucfirst($user->role) }}

</p>


</div>







@if($user->phone)


<div>


<p class="text-sm text-gray-500">
Phone
</p>


<p class="font-semibold">

{{ $user->phone }}

</p>


</div>


@endif







@if($user->middle_initial)


<div>


<p class="text-sm text-gray-500">
Middle Initial
</p>


<p class="font-semibold">

{{ $user->middle_initial }}

</p>


</div>


@endif








@if($user->sex)


<div>


<p class="text-sm text-gray-500">
Sex
</p>


<p class="font-semibold">

{{ $user->sex }}

</p>


</div>


@endif








@if($user->birthday)


<div>


<p class="text-sm text-gray-500">
Birthday
</p>


<p class="font-semibold">

{{ \Carbon\Carbon::parse($user->birthday)->format('M d, Y') }}

</p>


</div>


@endif








<div>


<p class="text-sm text-gray-500">
Joined Date
</p>


<p class="font-semibold">

{{ $user->created_at->format('M d, Y') }}

</p>


</div>







</div>






</div>



</div>

<!-- DOCUMENTS -->

<div class="
mt-6
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
">


<h2 class="
text-xl
font-bold
text-[#173F35]
">

Documents

</h2>



<div class="mt-5">


@if($user->government_id)


<a
href="{{ asset('storage/'.$user->government_id) }}"
target="_blank"
class="
inline-flex
bg-[#1F6F5B]
text-white
px-5
py-3
rounded-xl
text-sm
font-medium
">

View Government ID

</a>


@else


<p class="text-gray-500">

No documents uploaded.

</p>


@endif



</div>



</div>




@endsection