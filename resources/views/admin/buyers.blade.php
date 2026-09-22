@extends('admin.layout')


@section('title', 'Buyer Applications')

@section('page-heading', 'Buyer Applications')


@section('content')

<div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">

    <div>

        <p class="text-sm font-semibold text-[#1F6F5B]">
            Account management
        </p>

        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
            Buyer Applications
        </h1>

        <p class="mt-2 text-sm text-gray-500">
            Review and manage SUKI SHOP buyer registrations.
        </p>

    </div>


    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3">

        <p class="text-xs text-gray-400">
            Total buyers
        </p>

        <p class="mt-1 text-lg font-bold text-gray-900">
            {{ $buyers->count() }}
        </p>

    </div>

</div>


<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">

    <div class="border-b border-gray-100 px-6 py-5">

        <h2 class="font-semibold text-gray-900">
            Registered Buyers
        </h2>

        <p class="mt-1 text-xs text-gray-500">
            Approve pending registrations or reject invalid applications.
        </p>

    </div>


    @if($buyers->isEmpty())

        <div class="px-6 py-16 text-center">

            <p class="font-medium text-gray-500">
                No buyer registrations found.
            </p>

        </div>

    @else

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="border-b border-gray-100 bg-[#FAFBFA]">

                    <tr>

                        <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            Buyer
                        </th>

                        <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            Contact
                        </th>

                        <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            Registered
                        </th>

                        <th class="px-6 py-4 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            Status
                        </th>

                        <th class="px-6 py-4 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    @foreach($buyers as $buyer)

                        <tr class="transition hover:bg-[#FAFBFA]">

                            <td class="px-6 py-4">

                                <div class="flex items-center gap-3">

                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC] text-sm font-bold text-[#173F35]">

                                        {{ strtoupper(substr($buyer->first_name ?? 'B', 0, 1)) }}

                                    </div>


                                    <div>

                                        <p class="whitespace-nowrap text-sm font-semibold text-gray-800">
                                            {{ $buyer->full_name }}
                                        </p>

                                        <p class="mt-0.5 text-xs text-gray-400">
                                            Buyer #{{ $buyer->id }}
                                        </p>

                                    </div>

                                </div>

                            </td>


                            <td class="px-6 py-4">

                                <p class="text-sm text-gray-700">
                                    {{ $buyer->email }}
                                </p>

                                <p class="mt-1 text-xs text-gray-400">
                                    {{ $buyer->phone }}
                                </p>

                            </td>


                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">

                                {{ $buyer->created_at->format('M d, Y') }}

                            </td>


                            <td class="px-6 py-4">

                                @if($buyer->status === 'pending')

                                    <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                        Pending
                                    </span>

                                @elseif($buyer->status === 'active')

                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        Active
                                    </span>

                                @else

                                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600">
                                        Rejected
                                    </span>

                                @endif

                            </td>


                            <td class="px-6 py-4">

                                @if($buyer->status === 'pending')

                                    <div class="flex justify-end gap-2">

                                        <form
                                            method="POST"
                                            action="{{ route('admin.buyers.approve', $buyer) }}"
                                        >

                                            @csrf

                                            <button
                                                type="submit"
                                                class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#155244]"
                                            >
                                                Approve
                                            </button>

                                        </form>


                                        <form
                                            method="POST"
                                            action="{{ route('admin.buyers.reject', $buyer) }}"
                                        >

                                            @csrf

                                            <button
                                                type="submit"
                                                class="rounded-lg border border-red-200 bg-white px-4 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                            >
                                                Reject
                                            </button>

                                        </form>

                                    </div>

                                @else

                                    <p class="text-right text-xs text-gray-400">
                                        No action required
                                    </p>

                                @endif

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    @endif

</div>

@endsection