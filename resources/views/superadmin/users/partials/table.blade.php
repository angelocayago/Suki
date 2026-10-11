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
    ">


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
            ">

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
    ">


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