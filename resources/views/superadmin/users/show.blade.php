@extends('superadmin.layout')


@section('title')
User Profile
@endsection



@section('content')


<div class="max-w-6xl">


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






<!-- PROFILE HEADER -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
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

{{ strtoupper(substr($user->name,0,1)) }}

</div>




<div>


<h1 class="
text-3xl
font-bold
text-[#173F35]
">

{{ $user->name }}

</h1>


<p class="text-gray-500">

{{ $user->email }}

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

{{ ucfirst($user->role) }}

</span>



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


</div>


</div>








<!-- ACTION BUTTONS -->

<div class="
flex
flex-wrap
gap-3
items-start
">


@if($user->status !== 'active')

<form method="POST"
action="{{ route('superadmin.users.status',$user) }}">

@csrf

<input type="hidden"
name="status"
value="active">


<button
class="
px-4
py-2
rounded-xl
bg-green-100
text-green-700
text-sm
font-medium
hover:bg-green-200
">

Activate

</button>

</form>

@endif






@if($user->status !== 'suspended')

<form method="POST"
action="{{ route('superadmin.users.status',$user) }}">

@csrf

<input type="hidden"
name="status"
value="suspended">


<button
class="
px-4
py-2
rounded-xl
bg-red-100
text-red-700
text-sm
font-medium
hover:bg-red-200
">

Suspend

</button>

</form>

@endif






@if($user->status !== 'inactive')

<form method="POST"
action="{{ route('superadmin.users.status',$user) }}">

@csrf

<input type="hidden"
name="status"
value="inactive">


<button
class="
px-4
py-2
rounded-xl
bg-gray-100
text-gray-700
text-sm
font-medium
hover:bg-gray-200
">

Deactivate

</button>

</form>

@endif



</div>



</div>


</div>










<!-- INFORMATION -->

<div class="
grid
md:grid-cols-2
gap-6
mt-6
">





<!-- PERSONAL INFORMATION -->

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

Personal Information

</h2>



<div class="space-y-5">


<div>
<p class="text-sm text-gray-500">
Full Name
</p>

<p class="font-semibold">
{{ $user->name }}
</p>
</div>



<div>
<p class="text-sm text-gray-500">
Middle Initial
</p>

<p class="font-semibold">
{{ $user->middle_initial ?? '-' }}
</p>
</div>



<div>
<p class="text-sm text-gray-500">
Sex
</p>

<p class="font-semibold">
{{ $user->sex ?? '-' }}
</p>
</div>



<div>
<p class="text-sm text-gray-500">
Birthday
</p>

<p class="font-semibold">

{{ $user->birthday
? \Carbon\Carbon::parse($user->birthday)->format('M d, Y')
: '-'
}}

</p>

</div>



<div>
<p class="text-sm text-gray-500">
Phone
</p>

<p class="font-semibold">
{{ $user->phone ?? '-' }}
</p>

</div>



</div>


</div>










<!-- ACCOUNT DETAILS -->

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

Account Details

</h2>



<div class="space-y-5">



<div>
<p class="text-sm text-gray-500">
User ID
</p>

<p class="font-semibold">
#USR-{{ str_pad($user->id,5,'0',STR_PAD_LEFT) }}
</p>
</div>




<div>
<p class="text-sm text-gray-500">
Role
</p>

<p class="font-semibold">
{{ ucfirst($user->role) }}
</p>
</div>




<div>
<p class="text-sm text-gray-500">
Status
</p>

<p class="font-semibold">
{{ ucfirst($user->status) }}
</p>
</div>




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
">

Documents

</h2>



<div class="mt-5">


@if($user->government_id)


<div
class="
flex
justify-between
items-center
bg-[#F8FAF9]
rounded-2xl
p-5
">


<div>

<p class="font-semibold text-[#173F35]">
Government ID
</p>


<p class="text-sm text-gray-500">
Uploaded document
</p>


</div>



<a
href="{{ asset('storage/'.$user->government_id) }}"
target="_blank"
class="
px-5
py-3
rounded-xl
bg-[#1F6F5B]
text-white
text-sm
font-medium
">

View Document

</a>



</div>


@else


<p class="text-gray-500">
No documents uploaded.
</p>


@endif



</div>


</div>










<!-- ACTIVITY -->

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
mb-5
">

Recent Activity

</h2>



<div class="text-gray-500 text-sm">

Activity logs are not available yet.

</div>



</div>







</div>


@endsection