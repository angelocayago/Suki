@extends('superadmin.layout')


@section('title')
User Management
@endsection



@section('content')


<div
x-data="{

    open:false,

    selectedUserId:'',

    selectedUserName:'',

    selectedStatus:'',


    confirmAction(id,name,status){

        this.selectedUserId=id;

        this.selectedUserName=name;

        this.selectedStatus=status;

        this.open=true;

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
value="{{request('search')}}"
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


<tr
class="
border-t
hover:bg-[#FAFCFB]
">


<td class="p-5">


<div class="flex items-center gap-3">


<div
class="
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


{{strtoupper(substr($user->name,0,1))}}


</div>



<div>

<p class="font-semibold">

{{$user->name}}

</p>


<p class="text-sm text-gray-500">

{{$user->email}}

</p>


</div>


</div>


</td>






<td class="p-5">


<span class="badge">

{{ucfirst($user->role)}}

</span>


</td>






<td class="p-5">


<span class="
status
">


{{ucfirst($user->status)}}


</span>


</td>





<td class="p-5">


<select
class="border rounded-lg px-3 py-2 text-sm"
x-on:change="
if($event.target.value)
confirmAction(
'{{$user->id}}',
'{{$user->name}}',
$event.target.value
)
"
>


<option>
Manage
</option>


<option value="active">
Activate
</option>


<option value="suspended">
Suspend
</option>


<option value="inactive">
Deactivate
</option>


</select>



</td>


</tr>


@endforeach


</tbody>


</table>



</div>









<!-- MOBILE CARDS -->


<div class="
lg:hidden
space-y-4
">


@foreach($users as $user)


<div
class="
bg-white
rounded-2xl
border
p-5
">


<div class="flex justify-between">


<div>


<h3 class="font-bold">

{{$user->name}}

</h3>


<p class="text-sm text-gray-500">

{{$user->email}}

</p>


</div>


<span class="badge">

{{$user->role}}

</span>


</div>



<div class="mt-4 flex justify-between">


<span class="status">

{{$user->status}}

</span>




<form
method="POST"
action="{{route('superadmin.users.status',$user)}}">


@csrf


<select
name="status"
onchange="this.form.submit()"
class="border rounded-lg px-3 py-2">


<option>
Manage
</option>


<option value="active">
Activate
</option>


<option value="suspended">
Suspend
</option>


<option value="inactive">
Deactivate
</option>


</select>


</form>


</div>


</div>


@endforeach


</div>









<!-- PAGINATION -->

<div class="mt-6">

{{$users->links()}}

</div>









<!-- MODAL -->


<div
x-show="open"
class="
fixed
inset-0
bg-black/40
flex
items-center
justify-center
z-50
">


<div
class="
bg-white
rounded-2xl
p-6
w-full
max-w-md
">


<h2 class="text-xl font-bold">

Confirm Action

</h2>



<p class="text-gray-500 mt-3">


Change status of

<strong x-text="selectedUserName"></strong>


?


</p>





<form
method="POST"
x-bind:action="'/superadmin/users/'+selectedUserId+'/status'"
class="mt-5">


@csrf


<input
type="hidden"
name="status"
x-bind:value="selectedStatus"
>




<div class="flex justify-end gap-3">


<button
type="button"
@click="open=false"
class="px-4 py-2">

Cancel

</button>



<button
class="
bg-[#1F6F5B]
text-white
px-5
py-2
rounded-xl
">

Confirm

</button>


</div>


</form>


</div>


</div>



</div>







<style>


.user-stat{

background:white;
border:1px solid #E3EAE6;
border-radius:22px;
padding:24px;

}


.user-stat p{

color:#66736D;

}


.user-stat h2{

font-size:36px;
font-weight:800;
color:#173F35;

}




.head{

padding:18px;
text-align:left;
font-size:12px;
color:#66736D;
text-transform:uppercase;

}



.badge{

background:#DDF3EC;
color:#1F6F5B;
padding:6px 12px;
border-radius:999px;
font-size:12px;

}



.status{

background:#F1F5F9;
padding:6px 12px;
border-radius:999px;
font-size:12px;

}


</style>




@endsection