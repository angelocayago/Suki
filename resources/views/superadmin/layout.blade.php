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


<body class="bg-[#f8fafc] text-slate-800">


<div
x-data="{
    time:'',
    date:'',
    greeting:'',

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


        let hour = now.getHours();


        if(hour < 12){

            this.greeting='Good morning';

        }
        else if(hour < 18){

            this.greeting='Good afternoon';

        }
        else{

            this.greeting='Good evening';

        }


    },


    init(){

        this.updateClock();

        setInterval(()=>{

            this.updateClock();

        },1000)

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
w-[260px]
bg-[#064e3b]
text-white
flex
flex-col
">





<!-- BRAND -->


<div
class="
px-6
py-5
border-b
border-white/10
">


<div
class="
flex
items-center
gap-3
">


<img
src="{{asset('images/suki-logo.png')}}"
class="
w-12
h-12
object-contain
bg-white
rounded-xl
p-1
">


<div>


<h1 class="
font-bold
text-base
">

Super Admin Portal

</h1>


<p class="
text-xs
text-green-200
">

Platform Control Center

</p>


</div>


</div>


</div>







<!-- MENU -->


<nav
class="
flex-1
px-4
py-5
space-y-2
">


<a
href="{{route('superadmin.dashboard')}}"
class="
flex
items-center
gap-3
px-4
py-3
rounded-xl
bg-[#10b981]
text-white
font-medium
text-sm
">


<!-- dashboard icon -->

<svg
class="w-5 h-5"
fill="none"
stroke="currentColor"
viewBox="0 0 24 24">

<path
stroke-width="2"
d="M3 12l9-9 9 9v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>

</svg>


Dashboard


</a>







<a
href="{{route('superadmin.users')}}"
class="
menu-link
">


<svg
class="w-5 h-5"
fill="none"
stroke="currentColor"
viewBox="0 0 24 24">

<path
stroke-width="2"
d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>

</svg>


Users


</a>







<a class="menu-link">

<svg
class="w-5 h-5"
fill="none"
stroke="currentColor"
viewBox="0 0 24 24">

<path
stroke-width="2"
d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5l5 5v11a2 2 0 01-2 2z"/>

</svg>


Applications


</a>







<a class="menu-link">


<svg
class="w-5 h-5"
fill="none"
stroke="currentColor"
viewBox="0 0 24 24">

<path
stroke-width="2"
d="M3 7h18M5 7l1 13h12l1-13M9 7V4h6v3"/>

</svg>


Seller Management


</a>







<a class="menu-link">


<svg
class="w-5 h-5"
fill="none"
stroke="currentColor"
viewBox="0 0 24 24">

<path
stroke-width="2"
d="M3 3h18v18H3z"/>

</svg>


Orders


</a>






<a class="menu-link">


₱

Commission


</a>






<a class="menu-link">


<svg
class="w-5 h-5"
fill="none"
stroke="currentColor"
viewBox="0 0 24 24">

<path
stroke-width="2"
d="M3 3v18h18"/>

</svg>


Reports


</a>






<a class="menu-link">


⚙

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

<p class="
font-semibold
text-sm
">

{{auth()->user()->name}}

</p>


<p class="
text-xs
text-green-200
">

Super Administrator

</p>


</div>


</div>



</div>



</aside>









<!-- MAIN -->



<div
class="
ml-[260px]
flex-1
">





<!-- TOPBAR -->


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


<div
class="
bg-gray-100
rounded-xl
px-5
py-3
w-[320px]
text-sm
text-gray-400
">

⌕ Search users, orders, sellers...

</div>





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

S

</div>




</div>


</header>







<main
class="p-8"
>


@yield('content')


</main>




</div>







<style>

.menu-link{

display:flex;
align-items:center;
gap:12px;
padding:12px 16px;
border-radius:14px;
font-size:14px;
color:#d1fae5;
transition:.2s;

}


.menu-link:hover{

background:#047857;
color:white;

}


</style>




</body>
</html>