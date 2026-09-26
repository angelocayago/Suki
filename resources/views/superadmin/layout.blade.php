<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'SUKI Super Admin')
    </title>


    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

</head>


<body class="suki-dashboard-shell">


<div
x-data="{

date:'',
time:'',

init(){

this.updateClock();

setInterval(()=>{

this.updateClock();

},1000);

},


updateClock(){

let now = new Date();


this.date =
now.toLocaleDateString(
'en-US',
{
weekday:'short',
month:'short',
day:'numeric',
year:'numeric'
}
);



this.time =
now.toLocaleTimeString(
'en-US',
{
hour:'2-digit',
minute:'2-digit',
second:'2-digit'
}
);


}

}"
class="min-h-screen flex">






<!-- SIDEBAR -->

<aside
class="
fixed
left-0
top-0
bottom-0
w-[245px]
bg-[#064e3b]
text-white
flex
flex-col
">





<!-- HEADER -->

<div
class="
px-6
py-7
border-b
border-white/10
">


<h1
class="
font-bold
text-lg
">

Super Admin Portal

</h1>


<p
class="
text-xs
text-green-200
mt-1
">

Platform Control Center

</p>


</div>








<!-- NAVIGATION -->


<nav
class="
flex-1
px-4
py-6
space-y-2
">


<a
href="{{route('superadmin.dashboard')}}"
class="
block
px-4
py-3
rounded-xl
bg-[#10b981]
font-medium
text-sm
">

Dashboard

</a>




<a
href="{{route('superadmin.users')}}"
class="
block
px-4
py-3
rounded-xl
text-green-100
hover:bg-[#047857]
transition
text-sm
">

Users

</a>




<a
href="#"
class="
block
px-4
py-3
rounded-xl
text-green-100
hover:bg-[#047857]
transition
text-sm
">

Applications

</a>




<a
href="#"
class="
block
px-4
py-3
rounded-xl
text-green-100
hover:bg-[#047857]
transition
text-sm
">

Seller Management

</a>




<a
href="#"
class="
block
px-4
py-3
rounded-xl
text-green-100
hover:bg-[#047857]
transition
text-sm
">

Orders

</a>




<a
href="#"
class="
block
px-4
py-3
rounded-xl
text-green-100
hover:bg-[#047857]
transition
text-sm
">

Commission

</a>




<a
href="#"
class="
block
px-4
py-3
rounded-xl
text-green-100
hover:bg-[#047857]
transition
text-sm
">

Reports

</a>




<a
href="#"
class="
block
px-4
py-3
rounded-xl
text-green-100
hover:bg-[#047857]
transition
text-sm
">

Settings

</a>



</nav>







<!-- PROFILE -->


<div
class="
border-t
border-white/10
p-5
">


<div
class="
flex
items-center
gap-3
">


<div
class="
w-11
h-11
rounded-full
bg-green-400
text-green-900
flex
items-center
justify-center
font-bold
">

{{strtoupper(substr(auth()->user()->name,0,1))}}

</div>




<div>


<p
class="
font-semibold
text-sm
">

{{auth()->user()->name}}

</p>


<p
class="
text-xs
text-green-200
">

Super Administrator

</p>


</div>


</div>





<form method="POST" action="{{route('logout')}}" class="mt-5">

@csrf


<button
class="
text-sm
text-green-100
hover:text-white
">

Sign Out

</button>


</form>



</div>




</aside>









<!-- MAIN -->

<div
class="
ml-[245px]
flex-1
">






<!-- TOP BAR -->


<header
class="
h-20
bg-white
border-b
flex
items-center
justify-between
px-8
">





<!-- SEARCH -->


<div
class="
bg-gray-100
rounded-xl
px-5
py-3
w-[330px]
text-sm
text-gray-400
">

Search users, orders, sellers...

</div>







<!-- RIGHT -->

<div
class="
flex
items-center
gap-6
">


<div
class="
text-right
">


<p
class="
font-semibold
text-sm
"
x-text="date">
</p>


<p
class="
text-xs
text-gray-500
"
x-text="time">
</p>


</div>





<button
class="
w-10
h-10
rounded-xl
hover:bg-gray-100
">

🔔

</button>






<div
class="
w-11
h-11
rounded-full
bg-green-600
text-white
flex
items-center
justify-center
font-bold
">

{{strtoupper(substr(auth()->user()->name,0,1))}}

</div>




</div>


</header>








<main class="p-8">


@if(session('success'))

<div
x-data="{show:true}"
x-show="show"
x-transition
class="
mb-6
bg-green-50
border
border-green-200
text-green-700
px-5
py-4
rounded-xl
flex
justify-between
">


<span>

{{ session('success') }}

</span>


<button
@click="show=false">

×


</button>


</div>

@endif



@yield('content')


</main>





</div>



</div>



</body>

</html>