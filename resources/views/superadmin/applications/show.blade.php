@extends('superadmin.layout')


@section('title')

Application Review

@endsection



@section('content')


<div class="w-full">



<!-- BACK -->

<a href="{{ route('superadmin.applications') }}"
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

← Back to Applications

</a>








<!-- HEADER -->

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

{{ strtoupper(substr($application->user->name ?? 'U',0,1)) }}

</div>





<div>


<h1 class="
text-3xl
font-bold
text-[#173F35]
">

{{ $application->user->name ?? 'Unknown' }}

</h1>


<p class="text-gray-500">

{{ $application->user->email ?? '-' }}

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

{{ ucfirst($application->application_type) }}

</span>



<span
class="
px-4
py-2
rounded-full
text-sm
font-medium

{{ $application->status === 'approved'
? 'bg-green-100 text-green-700'
:
(
$application->status === 'rejected'
? 'bg-red-100 text-red-700'
:
'bg-yellow-100 text-yellow-700'
)

}}
">

{{ ucfirst($application->status) }}

</span>



</div>


</div>


</div>








<!-- ACTIONS -->


@if($application->status === 'pending')


<div class="
flex
gap-3
items-start
">


<form method="POST"
action="{{ route('superadmin.applications.approve',$application) }}">

@csrf


<button
class="
px-5
py-3
rounded-xl
bg-[#1F6F5B]
text-white
text-sm
font-medium
hover:bg-[#155244]
">

Approve Application

</button>


</form>





<button
x-data
@click="$dispatch('open-reject')"
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

Reject

</button>


</div>


@endif



</div>


</div>









<!-- INFO GRID -->


<div class="
grid
md:grid-cols-2
gap-6
">







<!-- APPLICANT -->

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

Applicant Information

</h2>




<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Full Name
</p>

<p class="font-semibold">
{{ $application->user->name ?? '-' }}
</p>

</div>




<div>

<p class="text-sm text-gray-500">
Email
</p>

<p class="font-semibold">
{{ $application->user->email ?? '-' }}
</p>

</div>




<div>

<p class="text-sm text-gray-500">
Phone
</p>

<p class="font-semibold">
{{ $application->user->phone ?? '-' }}
</p>

</div>




<div>

<p class="text-sm text-gray-500">
Role Applied
</p>

<p class="font-semibold">
{{ ucfirst($application->application_type) }}
</p>

</div>



</div>


</div>









<!-- APPLICATION DETAILS -->


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

Application Details

</h2>



<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Application ID
</p>

<p class="font-semibold">
#APP-{{ str_pad($application->id,5,'0',STR_PAD_LEFT) }}
</p>

</div>




<div>

<p class="text-sm text-gray-500">
Submitted Date
</p>

<p class="font-semibold">
{{ $application->created_at->format('M d, Y') }}
</p>

</div>




<div>

<p class="text-sm text-gray-500">
Status
</p>

<p class="font-semibold">
{{ ucfirst($application->status) }}
</p>

</div>




<div>

<p class="text-sm text-gray-500">
Reviewed Date
</p>

<p class="font-semibold">

{{ $application->reviewed_at
? $application->reviewed_at->format('M d, Y')
: '-'
}}

</p>

</div>



</div>


</div>



</div>









<!-- SELLER INFORMATION -->


@if($application->application_type === 'seller' && $application->user->sellers->count())


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

Seller Information

</h2>




@foreach($application->user->sellers as $seller)


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
Seller Status
</p>

<p class="font-semibold">
{{ ucfirst($seller->status) }}
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


@endforeach



</div>


@endif




<!-- REJECTION REASON -->


@if($application->status === 'rejected')


<div
class="
mt-6
bg-red-50
border
border-red-200
rounded-3xl
p-8
">


<h2
class="
text-xl
font-bold
text-red-700
mb-4
">

Rejection Reason

</h2>



<p class="
text-red-600
font-medium
">

{{ $application->rejection_reason ?? 'No rejection reason provided.' }}

</p>



</div>


@endif






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
mb-5
">

Submitted Documents

</h2>




@if($application->user->government_id)


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
href="{{ asset('storage/'.$application->user->government_id) }}"
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
No documents submitted.
</p>


@endif



</div>









<!-- REJECT MODAL -->


<div
x-data="{
    open:false,
    reason:''
}"

@open-reject.window="open=true"

x-show="open"

x-cloak

x-transition

class="
fixed
inset-0
bg-black/40
z-50
flex
items-center
justify-center
px-4
"
>


<div
class="
bg-white
rounded-3xl
p-8
max-w-md
w-full
shadow-xl
">


<h2
class="
text-xl
font-bold
text-[#173F35]
">

Reject Application

</h2>



<form method="POST"
action="{{ route('superadmin.applications.reject',$application) }}"
class="mt-5"
>

@csrf



<select
name="rejection_reason"
x-model="reason"
required
class="
w-full
border
rounded-xl
p-4
focus:outline-none
focus:ring-2
focus:ring-[#1F6F5B]
">


<option value="">
Select rejection reason
</option>


<option value="Incomplete documents">
Incomplete documents
</option>


<option value="Invalid government ID">
Invalid government ID
</option>


<option value="Failed verification">
Failed verification
</option>


<option value="Duplicate application">
Duplicate application
</option>


<option value="Information mismatch">
Information mismatch
</option>


<option value="Requirements not met">
Requirements not met
</option>


<option value="Business details could not be verified">
Business details could not be verified
</option>


<option value="Suspicious or inaccurate information">
Suspicious or inaccurate information
</option>


<option value="Other">
Other
</option>


</select>





<div
x-show="reason === 'Other'"
x-transition
class="mt-4"
>


<label class="
text-sm
text-gray-500
">

Additional Reason

</label>


<textarea

name="custom_reason"

placeholder="Enter rejection reason..."

class="
mt-2
w-full
border
rounded-xl
p-4
h-28
focus:outline-none
focus:ring-2
focus:ring-[#1F6F5B]
"

></textarea>


</div>







<div class="
flex
justify-end
gap-3
mt-5
">


<button
type="button"

@click="open=false"

class="
px-5
py-2
rounded-xl
border
">

Cancel

</button>




<button

class="
px-5
py-2
rounded-xl
bg-red-600
text-white
">

Confirm Reject

</button>



</div>



</form>


</div>


</div>

@endsection