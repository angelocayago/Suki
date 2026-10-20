@extends('layouts.rider')

@section('title', 'My Deliveries')

@section('content')

@if(session('success'))
    <div class="mb-4 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 p-4 text-sm text-red-700"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('rider.availability.update') }}" class="mb-4 flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 text-sm">
    @csrf
    <span class="font-semibold text-gray-800">Availability</span>
    <select name="availability" class="rounded-lg border-gray-300 text-sm">
        <option value="ONLINE" @selected(($availability ?? 'ONLINE') === 'ONLINE')>Online</option>
        <option value="OFFLINE" @selected(($availability ?? 'ONLINE') === 'OFFLINE')>Offline</option>
    </select>
    <button class="rounded-lg bg-[#173F35] px-3 py-2 text-xs font-semibold text-white">Update</button>
</form>

@php
    /*
    |--------------------------------------------------------------------------
    | RIDER INFORMATION
    |--------------------------------------------------------------------------
    */

    $riderName = $riderName ?? session('logged_in_rider_name', 'SUKI Rider');

    /*
    |--------------------------------------------------------------------------
    | ALL ORDERS
    |--------------------------------------------------------------------------
    */

    $allOrders = collect($orders ?? session('suki_orders', session('orders', [])));

    if (!($allOrders instanceof \Illuminate\Support\Collection)) {
        $allOrders = collect($allOrders);
    }

    /*
    |--------------------------------------------------------------------------
    | AVAILABLE PICKUPS
    |--------------------------------------------------------------------------
    |
    | Orders that are ready for pickup and do not have a rider assigned yet.
    |
    */

    $availablePickups = $allOrders
        ->filter(function ($order) {
            return ($order['status'] ?? '') === 'ready_for_pickup'
                && empty($order['pickup_rider_index']);
        });

    /*
    |--------------------------------------------------------------------------
    | MY DELIVERIES
    |--------------------------------------------------------------------------
    |
    | Only show orders assigned to the currently logged-in rider.
    | Logistics saves the rider_index when assigning a delivery.
    |
    */

    $riderIndex = $riderId ?? session('logged_in_rider_index');

    $myOrders = $allOrders
        ->filter(function ($order) use ($riderIndex, $riderName) {

            $status = strtolower($order['status'] ?? '');
            $pickupStatuses = ['ready_for_pickup', 'picked_up', 'at_sorting_center'];

            if (in_array($status, $pickupStatuses, true) && isset($order['pickup_rider_index'])) {
                return (string) $order['pickup_rider_index'] === (string) $riderIndex;
            }

            if (isset($order['rider_index'])) {
                return (string) $order['rider_index'] === (string) $riderIndex;
            }

            // Fallback for older orders created before rider_index
            return ($order['rider_name'] ?? '') === $riderName;
        });

    /*
    |--------------------------------------------------------------------------
    | STATUS COUNTS
    |--------------------------------------------------------------------------
    */

    $assignedCount = $myOrders
        ->where('status', 'assigned_to_rider')
        ->count();

    $liveTrackedDelivery = $myOrders->first(fn ($order) => in_array(strtolower($order['status'] ?? ''), ['assigned_to_rider', 'out_for_delivery'], true) && !empty($order['shipment_id']));

    $pickedUpCount = $myOrders
        ->where('status', 'picked_up')
        ->count();

    $sortingCount = $myOrders
        ->where('status', 'at_sorting_center')
        ->count();

    $outForDeliveryCount = $myOrders
        ->where('status', 'out_for_delivery')
        ->count();

    $deliveredCount = $myOrders
        ->whereIn('status', ['delivered', 'completed'])
        ->count();
@endphp

@if($liveTrackedDelivery)
    <div id="live-tracking-state" class="mb-4 rounded-xl border border-[#DDF3EC] bg-[#EEF8F3] px-4 py-3 text-sm text-[#155244]">
        Live location is preparing for your active delivery.
    </div>
    @push('scripts')
    <script>
    (() => {
      const state = document.getElementById('live-tracking-state');
      const endpoint = @json(route('rider.shipments.location.store', $liveTrackedDelivery['shipment_id']));
      const send = position => fetch(endpoint, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content}, body:JSON.stringify({latitude:position.coords.latitude, longitude:position.coords.longitude})}).then(r => { if (!r.ok) throw new Error(); state.textContent='Live location is active for this delivery.'; }).catch(() => state.textContent='Location update unavailable. Check permission and connection.');
      if (!navigator.geolocation) { state.textContent='This device does not support live location.'; return; }
      navigator.geolocation.watchPosition(send, () => state.textContent='Location permission is required to share active delivery updates.', {enableHighAccuracy:true, maximumAge:10000, timeout:15000});
    })();
    </script>
    @endpush
@endif

@push('scripts')
<script>
document.querySelectorAll('[data-copy-address]').forEach(button => button.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(button.dataset.copyAddress); button.textContent = 'Address copied'; }
  catch (_) { button.textContent = 'Copy unavailable'; }
}));
</script>
@endpush


{{-- ============================================================
     PAGE HEADER
============================================================ --}}

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">
        My Deliveries
    </h1>

    <p class="mt-1 text-sm text-gray-500">
        Manage your assigned deliveries and available pickups.
    </p>
</div>


{{-- ============================================================
     STATUS SUMMARY
============================================================ --}}

<div class="grid grid-cols-2 gap-4 mb-8 md:grid-cols-5">

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Assigned</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $assignedCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Picked Up</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $pickedUpCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Sorting Center</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $sortingCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Out for Delivery</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $outForDeliveryCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Delivered</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $deliveredCount }}
        </p>
    </div>

</div>


{{-- ============================================================
     AVAILABLE PICKUPS
============================================================ --}}

<div class="mb-8">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">
                Available Pickups
            </h2>

            <p class="text-sm text-gray-500">
                Accept a delivery that is waiting for a rider.
            </p>
        </div>

        <span class="px-3 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
            {{ $availablePickups->count() }} Available
        </span>
    </div>


    @if ($availablePickups->count())

        <div class="space-y-4">

            @foreach ($availablePickups as $order)

                @php
                    $orderId = $order['id'] ?? $loop->index;
                @endphp

                <div class="p-5 bg-white border border-gray-100 rounded-xl shadow-sm">

                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-800">
                                    Order #{{ $order['order_number'] ?? $orderId }}
                                </h3>

                                <span class="px-2 py-1 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-full">
                                    Ready for Pickup
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-gray-500">
                                {{ $order['customer_name'] ?? 'Customer' }}
                            </p>

                            @if (!empty($order['address']))
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $order['address'] }}
                                </p>
                            @endif
                        </div>


                        {{-- Accept Pickup --}}

                        <form
                            method="POST"
                            action="{{ ($order['source'] ?? 'session') === 'database'
                                ? route('rider.database.pickup.accept', $orderId)
                                : route('rider.pickup.accept', $orderId) }}"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition md:w-auto"
                            >
                                Accept Pickup
                            </button>
                        </form>

                    </div>

                </div>

            @endforeach

        </div>

    @else

        <div class="p-8 text-center bg-white border border-gray-100 rounded-xl">

            <div class="flex items-center justify-center w-14 h-14 mx-auto mb-3 rounded-full bg-gray-100">
                <svg
                    class="w-7 h-7 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"
                    />
                </svg>
            </div>

            <h3 class="font-semibold text-gray-700">
                No available pickups
            </h3>

            <p class="mt-1 text-sm text-gray-500">
                There are currently no orders waiting for a rider.
            </p>

        </div>

    @endif

</div>


{{-- ============================================================
     MY ASSIGNED DELIVERIES
============================================================ --}}

<div>

    <div class="mb-4">
        <h2 class="text-lg font-bold text-gray-800">
            My Assigned Deliveries
        </h2>

        <p class="text-sm text-gray-500">
            Track and update your current deliveries.
        </p>
    </div>


    @if ($myOrders->count())

        <div class="space-y-4">

            @foreach ($myOrders as $order)

                @php
                    $orderId = $order['id'] ?? $loop->index;
                    $status = $order['status'] ?? 'assigned_to_rider';

                    $statusLabels = [
                        'assigned_to_rider' => 'Assigned to Rider',
                        'ready_for_pickup' => 'Pickup Accepted',
                        'picked_up' => 'Picked Up',
                        'at_sorting_center' => 'At Sorting Center',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'completed' => 'Completed',
                    ];

                    $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                @endphp


                <div class="p-5 bg-white border border-gray-100 rounded-xl shadow-sm">

                    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">

                        {{-- Order Details --}}

                        <div class="flex-1">

                            <div class="flex flex-wrap items-center gap-2">

                                <h3 class="font-bold text-gray-800">
                                    Order #{{ $order['order_number'] ?? $orderId }}
                                </h3>

                                <span class="px-2.5 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
                                    {{ $statusLabel }}
                                </span>

                            </div>


                            <div class="mt-3 space-y-1">

                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Customer:</span>
                                    {{ $order['customer_name'] ?? 'Customer' }}
                                </p>

                                @if (!empty($order['address']))
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Address:</span>
                                        {{ $order['address'] }}
                                    </p>
                                    <button type="button" data-copy-address="{{ $order['navigation_address'] ?? $order['address'] }}" class="text-xs font-semibold text-[#1F6F5B] underline">Copy address for navigation</button>
                                @endif
                                @if(in_array($status, ['ready_for_pickup', 'picked_up'], true) && !empty($order['pickup_address']))
                                    <p class="mt-2 text-sm text-gray-600"><span class="font-medium">Pickup:</span> {{ $order['pickup_address'] }}</p>
                                    <button type="button" data-copy-address="{{ $order['pickup_address'] }}" class="text-xs font-semibold text-[#1F6F5B] underline">Copy pickup address</button>
                                @endif

                                @if (isset($order['total']))
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Total:</span>
                                        ₱{{ number_format((float) $order['total'], 2) }}
                                    </p>
                                @endif

                            </div>

                        </div>


                        {{-- Status Action --}}

                        <div class="w-full lg:w-auto">

                            @if ($status === 'ready_for_pickup')

                                <form method="POST" action="{{ ($order['source'] ?? 'session') === 'database'
                                    ? route('rider.database.order.status', $orderId)
                                    : route('rider.order.status', $orderId) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="picked_up">
                                    <button type="submit" class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition lg:w-auto">
                                        Confirm Parcel Collection
                                    </button>
                                </form>
                                @if (($order['source'] ?? '') === 'database' && empty($order['pickup_accepted_at']))
                                    <form method="POST" action="{{ route('rider.database.pickup.decline', $orderId) }}" class="mt-2" onsubmit="return confirm('Decline this assigned pickup?');">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg border border-red-200 px-4 py-2 text-xs font-semibold text-red-700">Decline Pickup</button>
                                    </form>
                                @endif

                            @elseif ($status === 'assigned_to_rider')

                                @if(($order['source'] ?? '') === 'database' && empty($order['delivery_accepted_at']))
                                    <form method="POST" action="{{ route('rider.database.delivery.accept', $orderId) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg border border-[#1F6F5B] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">Accept Delivery</button>
                                    </form>
                                    <form method="POST" action="{{ route('rider.database.delivery.decline', $orderId) }}" class="mb-2" onsubmit="return confirm('Decline this delivery assignment?');">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700">Decline Delivery</button>
                                    </form>
                                @endif

                                <form
                                    method="POST"
                                    action="{{ ($order['source'] ?? 'session') === 'database'
                                        ? route('rider.database.order.status', $orderId)
                                        : route('rider.order.status', $orderId) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="out_for_delivery"
                                    >

                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition lg:w-auto"
                                    >
                                        Mark Out for Delivery
                                    </button>
                                </form>


                            @elseif ($status === 'picked_up')

                                <form
                                    method="POST"
                                    action="{{ ($order['source'] ?? 'session') === 'database'
                                        ? route('rider.database.order.status', $orderId)
                                        : route('rider.order.status', $orderId) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="at_sorting_center"
                                    >

                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition lg:w-auto"
                                    >
                                        Send to Sorting Center
                                    </button>
                                </form>


                            @elseif ($status === 'at_sorting_center')

                                <span class="inline-flex px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg">
                                    Processing at Sorting Center
                                </span>


                            @elseif ($status === 'out_for_delivery')

                                <form
                                    method="POST"
                                    enctype="multipart/form-data"
                                    onsubmit="return confirm('Confirm delivery and COD collection?');"
                                    action="{{ ($order['source'] ?? 'session') === 'database'
                                        ? route('rider.database.order.status', $orderId)
                                        : route('rider.order.status', $orderId) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="delivered"
                                    >

                                    @if(($order['source'] ?? '') === 'database')
                                        <label class="mb-2 block text-xs font-semibold text-gray-600">Delivery proof (JPG, PNG or WebP, up to 5 MB)
                                            <input type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp" required class="mt-1 block w-full text-xs">
                                        </label>
                                        <input name="received_by" required maxlength="150" placeholder="Received by" class="mb-2 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                        <input name="delivery_note" maxlength="1000" placeholder="Delivery note (optional)" class="mb-2 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                        <label class="mb-3 block text-sm"><input type="checkbox" name="cod_collected" value="1" required> COD collected: ₱{{ number_format($order['total'], 2) }}</label>
                                    @endif

                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition lg:w-auto"
                                    >
                                        Mark as Delivered
                                    </button>
                                </form>

                                @if (($order['source'] ?? 'session') === 'database')
                                    <form method="POST" action="{{ route('rider.database.delivery.failed', $orderId) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        <select name="failure_reason" required class="rounded-lg border border-gray-200 px-3 py-2 text-xs"><option value="">Select failure reason</option><option value="buyer_unavailable">Buyer unavailable</option><option value="buyer_unreachable">Buyer unreachable</option><option value="incorrect_address">Incorrect/incomplete address</option><option value="access_issue">Delivery access issue</option><option value="buyer_requested_reschedule">Buyer requested reschedule</option><option value="buyer_refused">Buyer refused delivery</option><option value="other">Other</option></select>
                                        <input name="failure_notes" maxlength="1000" placeholder="Notes (required for Other)" class="rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                        <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white">Record Failure</button>
                                    </form>
                                    <form method="POST" action="{{ route('rider.database.delivery.issue', $orderId) }}" class="mt-2 grid gap-2">
                                        @csrf
                                        <select name="issue_category" required class="rounded-lg border border-gray-200 px-3 py-2 text-xs"><option value="">Report delivery issue</option><option value="buyer_unreachable">Buyer unreachable</option><option value="wrong_or_incomplete_address">Wrong or incomplete address</option><option value="damaged_parcel">Damaged parcel</option><option value="other">Other</option></select>
                                        <input name="issue_details" maxlength="1000" placeholder="Details (optional)" class="rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                        <button type="submit" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800">Save Issue Report</button>
                                    </form>
                                @endif


                            @elseif ($status === 'delivered')

                                <span class="inline-flex px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg">
                                    Awaiting Buyer Confirmation
                                </span>


                            @elseif ($status === 'completed')

                                <span class="inline-flex px-4 py-2 text-sm font-semibold text-green-700 bg-green-100 rounded-lg">
                                    Delivery Completed
                                </span>

                            @endif

                            @if (($order['source'] ?? 'session') === 'database')
                                <form method="POST" action="{{ route('rider.messages.context.shipment.store', $orderId) }}" class="mt-2 inline-flex w-full lg:w-auto">
                                    @csrf
                                    <button type="submit" class="w-full rounded-lg border border-[#1F6F5B] px-4 py-2 text-sm font-semibold text-[#1F6F5B] hover:bg-[#F6F9F7]">Message Support</button>
                                </form>
                                <a href="{{ route('rider.complaints.create', $orderId) }}" class="mt-2 inline-flex w-full items-center justify-center rounded-lg border border-[#DCE5E0] px-4 py-2 text-sm font-semibold text-[#1F6F5B] hover:bg-[#F6F9F7] lg:w-auto">Submit Complaint</a>
                            @endif

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    @else

        <div class="p-8 text-center bg-white border border-gray-100 rounded-xl">

            <h3 class="font-semibold text-gray-700">
                No assigned deliveries
            </h3>

            <p class="mt-1 text-sm text-gray-500">
                Your accepted deliveries will appear here.
            </p>

        </div>

    @endif

</div>

@endsection
