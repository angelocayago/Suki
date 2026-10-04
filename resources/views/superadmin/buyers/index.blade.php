@extends('superadmin.layout')


@section('title')
Buyer Management
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
            Buyer Management
        </h1>


        <p class="
            text-gray-500
            mt-1
        ">
            Manage buyer accounts and monitor buyer activity.
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
                Total Buyers
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
                Active Buyers
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
            bg-[#FFF0F0]
            rounded-2xl
            border
            border-[#F6D4D4]
            p-6
        ">

            <p class="text-sm text-gray-500">
                Suspended Buyers
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





        <div class="
            bg-[#EEF6FF]
            rounded-2xl
            border
            border-[#D8E9FA]
            p-6
        ">

            <p class="text-sm text-gray-500">
                Buyers With Orders
            </p>


            <h2 class="
                text-3xl
                font-bold
                text-[#173F35]
                mt-2
            ">
                {{ $stats['with_orders'] }}
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


        <div class="
            grid
            grid-cols-1
            lg:grid-cols-[1fr_230px_150px]
            gap-3
        ">



            <input

                type="text"

                id="buyerSearch"

                placeholder="Search buyer..."

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

                id="buyerStatus"

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


                <option value="inactive">
                    Inactive
                </option>


                <option value="suspended">
                    Suspended
                </option>


            </select>





            <button

                type="button"

                id="buyerSearchButton"

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



        </div>


    </div>







    <!-- BUYER TABLE -->


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
                min-w-[1000px]
            ">


                <thead class="
                    bg-[#F4F7F5]
                    text-[11px]
                    uppercase
                    tracking-wide
                    text-[#607169]
                ">


                    <tr>


                        <th class="px-5 py-4 text-left">
                            Buyer
                        </th>


                        <th class="px-5 py-4 text-left">
                            Email
                        </th>


                        <th class="px-5 py-4 text-left">
                            Phone
                        </th>


                        <th class="px-5 py-4 text-left">
                            Orders
                        </th>


                        <th class="px-5 py-4 text-left">
                            Total Spent
                        </th>


                        <th class="px-5 py-4 text-left">
                            Joined
                        </th>


                        <th class="px-5 py-4 text-left">
                            Status
                        </th>


                        <th class="px-5 py-4 text-center">
                            Action
                        </th>


                    </tr>


                </thead>
<tbody id="buyerTable">

    @include('superadmin.buyers.partials.table')

</tbody>


            </table>


        </div>


    </div>







    <div class="mt-6">

        {{ $buyers->links() }}

    </div>





</div>







<script>

let searchTimer = null;

let activeRequest = null;



function loadBuyers(){


    const search =
        document
        .getElementById('buyerSearch')
        .value;



    const status =
        document
        .getElementById('buyerStatus')
        .value;




    if(activeRequest){

        activeRequest.abort();

    }




    const controller = new AbortController();


    activeRequest = controller;





    fetch(
        "{{ route('superadmin.buyers.search') }}"
        + "?search="
        + encodeURIComponent(search)
        + "&status="
        + encodeURIComponent(status),
        {
            signal: controller.signal
        }
    )



    .then(response => response.text())



    .then(html => {


        document
            .getElementById('buyerTable')
            .innerHTML = html;


    })



    .catch(error => {


        if(error.name !== 'AbortError'){

            console.error(error);

        }


    });



}







document
.getElementById('buyerSearch')
.addEventListener(
    'input',
    function(){


        clearTimeout(searchTimer);



        searchTimer = setTimeout(() => {


            loadBuyers();


        },500);



    }
);








document
.getElementById('buyerStatus')
.addEventListener(
    'change',
    function(){


        loadBuyers();


    }
);







document
.getElementById('buyerSearchButton')
.addEventListener(
    'click',
    function(){


        loadBuyers();


    }
);



</script>




@endsection