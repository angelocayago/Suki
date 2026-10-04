@extends('superadmin.layout')


@section('title')

Seller Management

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

Seller Management

</h1>


<p class="
text-gray-500
mt-1
">

Manage seller accounts, shops, and seller status.

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
Total Sellers
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
bg-[#EAFBF3]
rounded-2xl
border
border-[#D3F1E1]
p-6
">


<p class="text-sm text-gray-500">
Active Sellers
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['active'] }}

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
Pending Sellers
</p>


<h2 class="
text-3xl
font-bold
text-[#173F35]
mt-2
">

{{ $stats['pending'] }}

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
Suspended Sellers
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
lg:grid-cols-[1fr_230px_150px]
gap-3
"

>


<input

type="text"

id="sellerSearch"

placeholder="Search seller..."

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

id="sellerStatus"

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


<option value="approved">

Active

</option>


<option value="pending">

Pending

</option>


<option value="suspended">

Suspended

</option>


<option value="rejected">

Rejected

</option>


</select>






<button

type="button"

id="sellerSearchButton"

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





<!-- SELLER TABLE -->


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
min-w-[950px]
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
Seller
</th>


<th class="px-5 py-4 text-left">
Shop Name
</th>


<th class="px-5 py-4 text-left">
Email
</th>


<th class="px-5 py-4 text-left">
Status
</th>


<th class="px-5 py-4 text-left">
Products
</th>


<th class="px-5 py-4 text-left">
Joined
</th>


<th class="px-5 py-4 text-center">
Action
</th>


</tr>


</thead>


<tbody id="sellerTable">

    @include('superadmin.sellers.partials.table')

</tbody>



</table>


</div>




</div>







<!-- PAGINATION -->


<div class="mt-6">


{{ $sellers->links() }}


</div>






</div>
<script>

let sellerSearchTimer = null;

let sellerRequest = null;



function loadSellers(){


    const search =
        document
        .getElementById('sellerSearch')
        .value;



    const status =
        document
        .getElementById('sellerStatus')
        .value;





    if(sellerRequest){

        sellerRequest.abort();

    }




    const controller = new AbortController();


    sellerRequest = controller;





    fetch(

        "{{ route('superadmin.sellers.search') }}"
        +
        "?search="
        +
        encodeURIComponent(search)
        +
        "&status="
        +
        encodeURIComponent(status),

        {
            signal: controller.signal
        }

    )



    .then(response => response.text())



    .then(html => {


        document
        .getElementById('sellerTable')
        .innerHTML = html;


    })



    .catch(error => {


        if(error.name !== 'AbortError'){

            console.error(error);

        }


    });



}







document
.getElementById('sellerSearch')
.addEventListener(
    'input',
    function(){


        clearTimeout(sellerSearchTimer);



        sellerSearchTimer = setTimeout(() => {


            loadSellers();


        },500);


    }
);






document
.getElementById('sellerStatus')
.addEventListener(
    'change',
    function(){

        loadSellers();

    }
);







document
.getElementById('sellerSearchButton')
.addEventListener(
    'click',
    function(){

        loadSellers();

    }
);



</script>


@endsection