<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
Register - SUKI SHOP
</title>


@vite([
    'resources/css/app.css',
    'resources/js/app.js'
])


<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>


<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
rel="stylesheet"
>


</head>


<body class="min-h-screen bg-[#F8FAF8] font-[Poppins] text-[#1F2937]">


<div class="min-h-screen">



<header class="border-b border-gray-100 bg-white">

<div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-6">


<a href="{{ url('/') }}" class="flex items-center gap-3">


<div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#EEF8F3]">

<span class="font-bold text-[#1F6F5B]">
S
</span>

</div>


<div>

<h1 class="text-lg font-bold leading-tight text-[#173F35]">
SUKI SHOP
</h1>


<p class="text-xs text-gray-500">
Connected Marketplace.
</p>


</div>


</a>



<div class="text-sm text-gray-500">

Already have an account?

<a
href="{{ route('login') }}"
class="ml-1 font-semibold text-[#1F6F5B] transition hover:text-blue-600"
>
Log in
</a>

</div>


</div>

</header>





<main class="px-6 py-16">


<div class="mx-auto max-w-5xl">



<div class="mx-auto max-w-2xl text-center">


<div
class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#1F6F5B] text-white shadow-lg"
>


<svg
class="h-7 w-7"
fill="none"
stroke="currentColor"
viewBox="0 0 24 24"
>

<path
stroke-linecap="round"
stroke-linejoin="round"
stroke-width="1.8"
d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM5.5 21a6.5 6.5 0 0113 0M19 8v6m3-3h-6"
/>

</svg>


</div>



<h2 class="mt-6 text-3xl font-bold text-gray-900">

Create your SUKI SHOP account

</h2>



<p class="mt-3 text-sm text-gray-500">

Choose the account type you want to register.
Every registration is reviewed by SUKI SHOP administrator.

</p>


</div>





<!-- CARDS -->

<div class="mt-10 grid items-stretch gap-6 md:grid-cols-3">





<!-- BUYER -->

<div
class="group flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-3 hover:border-[#1F6F5B] hover:shadow-xl cursor-pointer"
>


<div
class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-2xl transition-all duration-300 group-hover:bg-blue-600 group-hover:text-white"
>
🛒
</div>



<div class="mt-6 flex items-center justify-between">


<h3 class="text-xl font-bold">
Buyer
</h3>


<span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-600">
SHOP
</span>


</div>



<p class="mt-3 min-h-[72px] text-sm text-gray-500">

Create an account to browse products,
place orders, track deliveries,
and interact with sellers.

</p>



<ul class="mt-6 min-h-[96px] space-y-3 text-sm">


<li>
✓ Browse and purchase products
</li>


<li>
✓ Track orders
</li>


<li>
✓ Rate and provide feedback
</li>


</ul>



<div class="mt-auto pt-8">


<a
href="{{ route('register.buyer') }}"
class="flex h-11 w-full items-center justify-center rounded-xl bg-[#1F6F5B] px-5 text-sm font-bold text-white transition-all duration-300 group-hover:bg-blue-600"
>

Register as Buyer →

</a>


</div>


</div>





<!-- SELLER -->


<div
class="group flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-3 hover:border-[#1F6F5B] hover:shadow-xl cursor-pointer"
>


<div
class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-2xl transition-all duration-300 group-hover:bg-blue-600 group-hover:text-white"
>
🏪
</div>



<div class="mt-6 flex items-center justify-between">


<h3 class="text-xl font-bold">
Seller
</h3>


<span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-600">
SELL
</span>


</div>



<p class="mt-3 min-h-[72px] text-sm text-gray-500">

Register your business,
list products, manage inventory,
and monitor sales.

</p>



<ul class="mt-6 min-h-[96px] space-y-3 text-sm">


<li>
✓ Manage products and inventory
</li>


<li>
✓ Manage customer orders
</li>


<li>
✓ Generate sales reports
</li>


</ul>



<div class="mt-auto pt-8">


<a
href="{{ route('seller.register') }}"
class="flex h-11 w-full items-center justify-center rounded-xl border-2 border-[#1F6F5B] bg-white px-5 text-sm font-bold text-[#1F6F5B] transition-all duration-300 group-hover:bg-[#1F6F5B] group-hover:text-white"
>

Register as Seller →

</a>


</div>


</div>

<!-- LOGISTICS -->


<div
class="group flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-7 shadow-sm transition-all duration-300 hover:-translate-y-3 hover:border-green-300 hover:shadow-xl cursor-pointer"
>


<div
class="flex h-14 w-14 items-center justify-center rounded-2xl bg-green-50 text-2xl transition-all duration-300 group-hover:bg-green-600 group-hover:text-white"
>
🚚
</div>



<div class="mt-6 flex items-center justify-between">


<h3 class="text-xl font-bold">
Logistics
</h3>


<span class="rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-600">
DELIVER
</span>


</div>



<p class="mt-3 min-h-[72px] text-sm text-gray-500">

Register your logistics company
to handle pickups, deliveries,
and shipments.

</p>



<ul class="mt-6 min-h-[96px] space-y-3 text-sm">


<li>
✓ Accept delivery requests
</li>


<li>
✓ Pick up and deliver orders
</li>


<li>
✓ Track delivery history
</li>


</ul>



<div class="mt-auto pt-8">


<a
href="/logistics/register"
class="flex h-11 w-full items-center justify-center rounded-xl border-2 border-green-600 bg-white px-5 text-sm font-bold text-green-600 transition-all duration-300 group-hover:bg-green-600 group-hover:text-white"
>

Register as Logistics →

</a>


</div>


</div>





</div>






<!-- NOTICE -->


<div class="mx-auto mt-8 max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">


<h3 class="text-sm font-bold text-gray-800">
Administrator approval required
</h3>



<p class="mt-1 text-sm text-gray-500">

All Buyer, Seller, and Logistics registrations
are reviewed by SUKI SHOP administrators.

</p>


</div>




</div>


</main>


</div>


</body>

</html>