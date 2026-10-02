@extends('superadmin.layout')


@section('title')

User Management

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

User Management

</h1>


<p class="
text-gray-500
mt-1
">

Manage SUKI platform accounts and access.

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
Total Users
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
Buyers
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['buyers'] }}

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
Sellers
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['sellers'] }}

</h2>


</div>







<div class="
bg-[#FFF0F0]
rounded-2xl
border
border-[#F6D4D4]
p-6
">


<p class="text-sm text-gray-500">
Suspended
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['suspended'] }}

</h2>


</div>



</div>









<!-- FILTER BAR -->


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
lg:grid-cols-[1fr_230px_230px_150px]
gap-3
"

>


<input

type="text"

name="search"

value="{{ request('search') }}"

placeholder="Search users..."

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

name="role"

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
All Roles
</option>


<option value="buyer">
Buyer
</option>


<option value="seller">
Seller
</option>


</select>







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


<option value="active">
Active
</option>


<option value="suspended">
Suspended
</option>


<option value="inactive">
Inactive
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





<!-- USER TABLE -->


<div class="
bg-white
rounded-2xl
border
border-[#DCE5E0]
shadow-sm
overflow-hidden
">


<div class="overflow-x-auto">


<table class="
w-full
min-w-[850px]
">


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
User
</th>


<th class="px-5 py-4 text-left">
Role
</th>


<th class="px-5 py-4 text-left">
Status
</th>


<th class="px-5 py-4 text-center">
Action
</th>


</tr>


</thead>







<tbody>


@forelse($users as $user)



<tr class="
border-t
border-[#E7ECE9]
hover:bg-[#FBFCFB]
transition
">





<td class="
px-5
py-4
"
>


<div class="
flex
items-center
gap-3
">


<div class="
w-10
h-10
rounded-full
bg-[#DDF3EC]
text-[#1F6F5B]
flex
items-center
justify-center
font-bold
"
>

{{ strtoupper(substr($user->name,0,1)) }}

</div>





<div>


<p class="
font-semibold
text-[#253831]
text-sm
">

{{ $user->name }}

</p>


<p class="
text-xs
text-gray-500
">

{{ $user->email }}

</p>


</div>


</div>


</td>








<td class="px-5 py-4">


<span class="
inline-flex
px-3
py-1
rounded-full
text-xs
font-medium
bg-[#DDF3EC]
text-[#1F6F5B]
">

{{ ucfirst($user->role) }}

</span>


</td>








<td class="px-5 py-4">


<span

class="
inline-flex
px-3
py-1
rounded-full
text-xs
font-medium

{{ $user->status === 'active'
    ? 'bg-green-100 text-green-700'
    :
    (
        $user->status === 'suspended'
        ? 'bg-red-100 text-red-700'
        :
        'bg-yellow-100 text-yellow-700'
    )
}}

"

>

{{ ucfirst($user->status) }}


</span>


</td>








<td class="
px-5
py-4
text-center
"
>


<div
x-data="{open:false}"
class="relative inline-block"
>


<button

@click="open=!open"

class="
w-9
h-9
rounded-lg
border
border-[#DCE5E0]
hover:bg-gray-50
font-bold
text-lg
"

>

⋮

</button>






<div

x-show="open"

@click.outside="open=false"

x-transition

class="
absolute
right-0
mt-2
w-48
bg-white
border
border-[#E3EAE6]
rounded-xl
shadow-lg
z-30
overflow-hidden
"

>


<a

href="{{ route('superadmin.users.show',$user) }}"

class="
block
px-4
py-3
text-sm
hover:bg-[#F4F7F5]
text-left
"

>

View Profile

</a>






<button

@click="
openConfirm(
'{{ $user->id }}',
'{{ $user->name }}',
'active',
'{{ route('superadmin.users.status',$user) }}'
)
"

class="
block
w-full
text-left
px-4
py-3
text-sm
text-green-700
hover:bg-green-50
"

>

Activate

</button>







<button

@click="
openConfirm(
'{{ $user->id }}',
'{{ $user->name }}',
'suspended',
'{{ route('superadmin.users.status',$user) }}'
)
"

class="
block
w-full
text-left
px-4
py-3
text-sm
text-red-600
hover:bg-red-50
"

>

Suspend

</button>







<button

@click="
openConfirm(
'{{ $user->id }}',
'{{ $user->name }}',
'inactive',
'{{ route('superadmin.users.status',$user) }}'
)
"

class="
block
w-full
text-left
px-4
py-3
text-sm
hover:bg-gray-50
"

>

Deactivate

</button>



</div>



</div>


</td>






</tr>



@empty



<tr>


<td

colspan="4"

class="
px-6
py-12
text-center
text-sm
text-gray-500
"

>

No users found.

</td>


</tr>



@endforelse



</tbody>



</table>


</div>


</div>









<!-- PAGINATION -->


<div class="mt-6">

{{ $users->links() }}

</div>







<!-- CONFIRM MODAL -->


<div

x-show="showModal"

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
"

>


<h2 class="
text-xl
font-bold
text-[#173F35]
">

Confirm Action

</h2>





<p class="
text-gray-500
mt-3
">

Are you sure you want to change

<strong x-text="selectedUserName"></strong>

status to

<strong x-text="selectedStatus"></strong>

?

</p>







<form

method="POST"

x-bind:action="selectedUrl"

class="mt-6"

>

@csrf


<input

type="hidden"

name="status"

x-bind:value="selectedStatus"

>




<div class="
flex
justify-end
gap-3
">


<button

type="button"

@click="showModal=false"

class="
px-5
py-2
rounded-xl
border
"

>

Cancel

</button>






<button

class="
px-5
py-2
rounded-xl
bg-[#1F6F5B]
text-white
font-medium
"

>

Confirm

</button>



</div>



</form>



</div>


</div>







</div>


@endsection