@extends('superadmin.layout')


@section('title')

Applications

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

Applications

</h1>


<p class="text-gray-500 mt-2">

Review and manage platform access applications.

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





<!-- TOTAL -->


<div class="
bg-[#FFF7E8]
rounded-3xl
border
border-[#F3E4C2]
p-6
">

<p class="text-sm text-gray-500">
Total Applications
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








<!-- PENDING -->


<div class="
bg-[#EEF6FF]
rounded-3xl
border
border-[#D8E9FA]
p-6
">


<p class="text-sm text-gray-500">
Pending
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








<!-- APPROVED -->


<div class="
bg-[#EAFBF3]
rounded-3xl
border
border-[#D3F1E1]
p-6
">


<p class="text-sm text-gray-500">
Approved
</p>


<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['approved'] }}

</h2>


</div>








<!-- REJECTED -->


<div class="
bg-[#FFF0F0]
rounded-3xl
border
border-[#F6D4D4]
p-6
">


<p class="text-sm text-gray-500">
Rejected
</p>


<h2 class="
text-4xl
font-bold
text-[#173F35]
mt-3
">

{{ $stats['rejected'] }}

</h2>


</div>




</div>









<!-- FILTERS -->


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
md:grid-cols-4
gap-4
">



<input
type="text"
name="search"
value="{{ request('search') }}"
placeholder="Search applicant..."
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
name="application_type"
class="
border
rounded-xl
px-4
py-3
">

<option value="">
All Roles
</option>


<option value="buyer"
{{ request('application_type')=='buyer'?'selected':'' }}
>
Buyer
</option>


<option value="seller"
{{ request('application_type')=='seller'?'selected':'' }}
>
Seller
</option>


<option value="courier"
{{ request('application_type')=='courier'?'selected':'' }}
>
Courier
</option>


<option value="logistics"
{{ request('application_type')=='logistics'?'selected':'' }}
>
Logistics
</option>


</select>







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


<option value="pending"
{{ request('status')=='pending'?'selected':'' }}
>
Pending
</option>


<option value="approved"
{{ request('status')=='approved'?'selected':'' }}
>
Approved
</option>


<option value="rejected"
{{ request('status')=='rejected'?'selected':'' }}
>
Rejected
</option>


</select>







<button
class="
bg-[#1F6F5B]
text-white
rounded-xl
font-medium
hover:bg-[#155244]
transition
">

Search

</button>



</form>


</div>









<!-- TABLE -->


<div class="
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
Applicant
</th>


<th class="p-5 text-left">
Role
</th>


<th class="p-5 text-left">
Email
</th>


<th class="p-5 text-left">
Submitted
</th>


<th class="p-5 text-left">
Status
</th>


<th class="p-5 text-left">
Action
</th>


</tr>

</thead>





<tbody>


@forelse($applications as $application)


<tr class="
border-t
border-[#E3EAE6]
">



<td class="p-5">


<div class="font-semibold text-[#173F35]">

{{ $application->user->name ?? 'Unknown' }}

</div>


</td>





<td class="p-5">


<span class="
px-3
py-1
rounded-full
bg-[#DDF3EC]
text-[#1F6F5B]
text-sm
">

{{ ucfirst($application->application_type) }}

</span>


</td>







<td class="p-5">

{{ $application->user->email ?? '-' }}

</td>







<td class="p-5">

{{ $application->created_at->format('M d, Y') }}

</td>







<td class="p-5">


<span class="

px-3
py-1
rounded-full
text-sm


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


</td>







<td class="p-5">


<a
href="{{ route('superadmin.applications.show',$application) }}"
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

<td colspan="6"
class="
p-8
text-center
text-gray-500
">

No applications found.

</td>

</tr>


@endforelse



</tbody>


</table>


</div>









<!-- PAGINATION -->


<div class="mt-6">

{{ $applications->links() }}

</div>






</div>


@endsection