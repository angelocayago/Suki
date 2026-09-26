@extends('superadmin.layout')


@section('title')
User Management
@endsection



@section('content')


<div class="bg-white rounded-xl shadow p-6">


    <div class="flex justify-between mb-6">

        <h2 class="text-xl font-semibold">
            Users
        </h2>

    </div>



    <form method="GET"
          class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">


        <input
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search users..."
            class="border rounded-lg px-4 py-2">


        <select name="role"
                class="border rounded-lg px-4 py-2">

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



        <select name="status"
                class="border rounded-lg px-4 py-2">

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
            class="bg-green-600 text-white rounded-lg px-4 py-2">

            Filter

        </button>


    </form>





    <table class="w-full">

        <thead>

            <tr class="border-b">


                <th class="text-left p-3">
                    Name
                </th>


                <th class="text-left p-3">
                    Email
                </th>


                <th class="text-left p-3">
                    Role
                </th>


                <th class="text-left p-3">
                    Status
                </th>


                <th class="text-left p-3">
                    Action
                </th>


            </tr>

        </thead>


        <tbody>


        @foreach($users as $user)


            <tr class="border-b">


                <td class="p-3">
                    {{ $user->name }}
                </td>


                <td class="p-3">
                    {{ $user->email }}
                </td>


                <td class="p-3">
                    {{ ucfirst($user->role) }}
                </td>


                <td class="p-3">

                    <span class="px-3 py-1 rounded-full bg-gray-100">

                        {{ $user->status }}

                    </span>

                </td>


                <td class="p-3">


                    <form method="POST"
                    action="{{ route('superadmin.users.status',$user) }}">


                        @csrf


                        <select
                        name="status"
                        onchange="this.form.submit()"
                        class="border rounded px-2 py-1">


                            <option>
                                Change
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


                </td>


            </tr>


        @endforeach


        </tbody>


    </table>



    <div class="mt-5">

        {{ $users->links() }}

    </div>



</div>


@endsection