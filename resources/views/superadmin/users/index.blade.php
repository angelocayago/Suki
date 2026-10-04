@extends('superadmin.layout')


@section('title')

User Management

@endsection



@section('content')


<div
class="w-full"

x-data="{

    showModal:false,

    selectedUserName:'',

    selectedStatus:'',

    selectedUrl:'',


    openConfirm(id,name,status,url){

        this.selectedUserName = name;

        this.selectedStatus = status;

        this.selectedUrl = url;

        this.showModal = true;

    }

}"

>



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



        <div class="
            grid
            grid-cols-1
            lg:grid-cols-[1fr_230px_230px_150px]
            gap-3
        ">



            <input

                type="text"

                id="userSearch"

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

                id="userRole"

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

                id="userStatus"

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

                type="button"

                id="userSearchButton"

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


                <thead class="
                    bg-[#F4F7F5]
                    text-[11px]
                    uppercase
                    tracking-wide
                    text-[#607169]
                ">


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
                <tbody id="userTable">

    @include('superadmin.users.partials.table')

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







<script>

let userSearchTimer = null;

let userRequest = null;



function loadUsers(){


    const search =
        document
        .getElementById('userSearch')
        .value;



    const role =
        document
        .getElementById('userRole')
        .value;



    const status =
        document
        .getElementById('userStatus')
        .value;





    if(userRequest){

        userRequest.abort();

    }





    const controller = new AbortController();


    userRequest = controller;





    fetch(

        "{{ route('superadmin.users.search') }}"
        +
        "?search="
        + encodeURIComponent(search)
        +
        "&role="
        + encodeURIComponent(role)
        +
        "&status="
        + encodeURIComponent(status),

        {
            signal: controller.signal
        }

    )



    .then(response => response.text())



    .then(html => {


        document
        .getElementById('userTable')
        .innerHTML = html;


    })



    .catch(error => {


        if(error.name !== 'AbortError'){

            console.error(error);

        }


    });



}








document
.getElementById('userSearch')
.addEventListener(
    'input',
    function(){


        clearTimeout(userSearchTimer);



        userSearchTimer = setTimeout(() => {


            loadUsers();


        },500);


    }
);








document
.getElementById('userRole')
.addEventListener(
    'change',
    function(){

        loadUsers();

    }
);








document
.getElementById('userStatus')
.addEventListener(
    'change',
    function(){

        loadUsers();

    }
);








document
.getElementById('userSearchButton')
.addEventListener(
    'click',
    function(){

        loadUsers();

    }
);



</script>





@endsection