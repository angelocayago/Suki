@extends('superadmin.layout')


@section('title')

User Management

@endsection



@section('content')


<div
x-data="{

    showModal:false,

    selectedUserId:'',

    selectedUserName:'',

    selectedStatus:'',

    selectedUrl:'',


    openConfirm(id,name,status,url){

        this.selectedUserId=id;

        this.selectedUserName=name;

        this.selectedStatus=status;

        this.selectedUrl=url;

        this.showModal=true;

    }

}"
>





<!-- HEADER -->

<div class="mb-8">

<h1 class="text-3xl font-bold text-[#173F35]">

User Management

</h1>


<p class="text-[#66736D] mt-2">

Manage SUKI platform accounts and access.

</p>


</div>








<!-- STATS -->


<div class="
grid
grid-cols-1
sm:grid-cols-2
xl:grid-cols-4
gap-5
mb-8
">


<div class="user-stat">

<p>Total Users</p>

<h2>
{{ $stats['total'] }}
</h2>

</div>




<div class="user-stat">

<p>Buyers</p>

<h2>
{{ $stats['buyers'] }}
</h2>

</div>




<div class="user-stat">

<p>Sellers</p>

<h2>
{{ $stats['sellers'] }}
</h2>

</div>




<div class="user-stat">

<p>Suspended</p>

<h2>
{{ $stats['suspended'] }}
</h2>

</div>


</div>








<!-- FILTER -->

<div class="
bg-white
border
border-[#E3EAE6]
rounded-2xl
p-6
mb-6
">


<form method="GET"
class="
grid
grid-cols-1
md:grid-cols-4
gap-4
">


<input
name="search"
value="{{ request('search') }}"
placeholder="Search users..."
class="
border
rounded-xl
px-4
py-3
">





<select
name="role"
class="
border
rounded-xl
px-4
py-3
">


<option value="">
All Roles
</option>


<option value="buyer">
Buyer
</option>


<option value="seller">
Seller
</option>


<option value="admin">
Admin
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


<option value="active">
Active
</option>


<option value="pending">
Pending
</option>


<option value="suspended">
Suspended
</option>


</select>






<button
class="
bg-[#1F6F5B]
text-white
rounded-xl
px-5
py-3
">

Search

</button>



</form>


</div>







<!-- DESKTOP TABLE -->


<div class="
hidden
lg:block
bg-white
border
border-[#E3EAE6]
rounded-2xl
overflow-hidden
">


<table class="w-full">


<thead class="bg-[#F4F7F5]">


<tr>

<th class="head">
User
</th>


<th class="head">
Role
</th>


<th class="head">
Status
</th>


<th class="head">
Action
</th>


</tr>


</thead>



<tbody>


@foreach($users as $user)


<tr class="
border-t
hover:bg-[#FAFCFB]
transition
">


<td class="p-5">


<div class="flex items-center gap-3">


<div class="
w-11
h-11
rounded-full
bg-[#DDF3EC]
text-[#1F6F5B]
flex
items-center
justify-center
font-bold
">


{{ strtoupper(substr($user->name,0,1)) }}


</div>



<div>

<p class="font-semibold">

{{ $user->name }}

</p>


<p class="text-sm text-gray-500">

{{ $user->email }}

</p>


</div>


</div>


</td>

<td class="p-5">


<span class="badge">

{{ ucfirst($user->role) }}

</span>


</td>





<td class="p-5">


<span class="status">

{{ ucfirst($user->status) }}

</span>


</td>






<td class="p-5">


<div
x-data="{
    userMenu:false
}"
class="relative"
>


<button

@click="userMenu=!userMenu"

class="
w-9
h-9
rounded-lg
hover:bg-gray-100
text-xl
font-bold
"
>

⋮

</button>



<div
x-show="userMenu"
@click.outside="userMenu=false"
x-transition
class="
absolute
right-0
mt-2
w-48
bg-white
border
rounded-xl
shadow-lg
z-30
overflow-hidden
">


<a
@click="userMenu=false"
href="{{ route('superadmin.users.show',$user) }}"
class="
block
px-4
py-3
text-sm
hover:bg-[#F4F7F5]
">

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
">

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
">

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
">

Deactivate

</button>



</div>


</div>


</td>



</tr>


@endforeach


</tbody>


</table>


</div>









<!-- MOBILE -->


<div class="
lg:hidden
space-y-4
">


@foreach($users as $user)


<div class="
bg-white
border
rounded-2xl
p-5
">


<h3 class="font-bold">

{{ $user->name }}

</h3>


<p class="text-sm text-gray-500">

{{ $user->email }}

</p>




<div class="
mt-4
flex
justify-between
items-center
">


<span class="status">

{{ ucfirst($user->status) }}

</span>




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
text-red-600
text-sm
font-medium
">

Suspend

</button>


</div>


</div>


@endforeach


</div>








<!-- PAGINATION -->


<div class="mt-6">

{{ $users->links() }}

</div>









<!-- CONFIRM MODAL -->


<div

x-cloak

x-show="showModal"

x-transition.opacity

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
">

Cancel

</button>





<button

class="
px-5
py-2
rounded-xl
bg-[#1F6F5B]
text-white
">

Confirm

</button>



</div>



</form>


</div>


</div>









<style>


.user-stat{

background:white;

border:1px solid #E3EAE6;

border-radius:22px;

padding:24px;

box-shadow:
0 10px 30px rgba(23,63,53,.055);

}



.user-stat p{

color:#66736D;

font-size:14px;

}



.user-stat h2{

font-size:36px;

font-weight:800;

color:#173F35;

margin-top:8px;

}





.head{

padding:18px;

text-align:left;

font-size:12px;

text-transform:uppercase;

letter-spacing:.05em;

color:#66736D;

}





.badge{

background:#DDF3EC;

color:#1F6F5B;

padding:6px 12px;

border-radius:999px;

font-size:12px;

font-weight:500;

}





.status{

background:#F1F5F9;

padding:6px 12px;

border-radius:999px;

font-size:12px;

}

[x-cloak] {
    display: none !important;
}



</style>





@endsection