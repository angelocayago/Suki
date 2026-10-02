@extends('superadmin.layout')


@section('title')

Order Details

@endsection



@section('content')


<div class="w-full">



<!-- BACK -->

<a
href="{{ route('superadmin.orders') }}"
class="
inline-flex
items-center
mb-6
text-sm
font-medium
text-[#1F6F5B]
hover:text-[#155244]
hover:underline
transition
"
>

← Back to Orders

</a>







<!-- ORDER HEADER -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
mb-6
"
>


<div
class="
flex
flex-col
md:flex-row
md:items-center
justify-between
gap-6
"
>


<div>


<p class="
text-sm
text-gray-500
mb-1
">

Order

</p>


<h1 class="
text-3xl
font-bold
text-[#173F35]
">

#{{ $order->order_number }}

</h1>


<p class="
text-gray-500
mt-2
">

Placed
{{ ($order->placed_at ?? $order->created_at)->format('M d, Y h:i A') }}

</p>


</div>





<div>


<span
class="
inline-flex
px-4
py-2
rounded-full
text-sm
font-medium

{{ in_array($order->status, ['DELIVERED', 'COMPLETED'])
    ? 'bg-green-100 text-green-700'
    : (
        in_array($order->status, ['CANCELLED', 'RETURNED', 'DELIVERY_FAILED'])
        ? 'bg-red-100 text-red-700'
        : (
            in_array($order->status, [
                'CONFIRMED',
                'PREPARING',
                'READY_FOR_PICKUP',
                'PICKED_UP',
                'AT_SORTING_CENTER',
                'SORTED',
                'ASSIGNED_TO_RIDER',
                'OUT_FOR_DELIVERY'
            ])
            ? 'bg-blue-100 text-blue-700'
            : 'bg-yellow-100 text-yellow-700'
        )
    )
}}

"
>

{{ ucwords(strtolower(str_replace('_', ' ', $order->status))) }}

</span>


</div>



</div>


</div>









<!-- TOP INFORMATION GRID -->

<div
class="
grid
grid-cols-1
lg:grid-cols-2
gap-6
mb-6
"
>





<!-- ORDER SUMMARY -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Order Summary

</h2>



<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Order Number
</p>

<p class="font-semibold">
{{ $order->order_number }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Order Status
</p>

<p class="font-semibold">
{{ ucwords(strtolower(str_replace('_', ' ', $order->status))) }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Payment Method
</p>

<p class="font-semibold">
{{ $order->payment_method
    ? ucwords(str_replace('_', ' ', strtolower($order->payment_method)))
    : '-'
}}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Payment Status
</p>

<p class="font-semibold">
{{ ucwords(strtolower(str_replace('_', ' ', $order->payment_status ?? 'UNPAID'))) }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Subtotal
</p>

<p class="font-semibold">
₱{{ number_format((float) $order->subtotal, 2) }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Shipping Fee
</p>

<p class="font-semibold">
₱{{ number_format((float) $order->shipping_fee, 2) }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Discount
</p>

<p class="font-semibold">
₱{{ number_format((float) $order->discount_amount, 2) }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Total Amount
</p>

<p class="
text-xl
font-bold
text-[#173F35]
">

₱{{ number_format((float) $order->total_amount, 2) }}

</p>

</div>


</div>


</div>








<!-- BUYER / SELLER -->

<div
class="
space-y-6
"
>




<!-- BUYER INFORMATION -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Buyer Information

</h2>


<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Name
</p>

<p class="font-semibold">
{{ $order->buyer->name ?? '-' }}
</p>

</div>


<div>

<p class="text-sm text-gray-500">
Email
</p>

<p class="font-semibold">
{{ $order->buyer->email ?? '-' }}
</p>

</div>


<div>

<p class="text-sm text-gray-500">
Phone
</p>

<p class="font-semibold">
{{ $order->buyer->phone ?? '-' }}
</p>

</div>


</div>


</div>







<!-- SELLER INFORMATION -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Seller Information

</h2>


@if($order->seller)


<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Name
</p>

<p class="font-semibold">
{{ $order->seller->name }}
</p>

</div>


<div>

<p class="text-sm text-gray-500">
Email
</p>

<p class="font-semibold">
{{ $order->seller->email ?? '-' }}
</p>

</div>


<div>

<p class="text-sm text-gray-500">
Phone
</p>

<p class="font-semibold">
{{ $order->seller->phone ?? '-' }}
</p>

</div>


</div>


@else


<p class="text-gray-500">
No seller is assigned to this order.
</p>


@endif


</div>



</div>


</div>










<!-- ORDER ITEMS -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
shadow-sm
overflow-hidden
mb-6
"
>


<div class="p-8 pb-5">


<h2 class="
text-xl
font-bold
text-[#173F35]
">

Order Items

</h2>


</div>



<div class="overflow-x-auto">


<table class="
w-full
min-w-[760px]
"
>


<thead
class="
bg-[#F8FAF9]
text-sm
text-gray-500
"
>


<tr>

<th class="p-5 text-left">
Product
</th>

<th class="p-5 text-left">
Variation
</th>

<th class="p-5 text-left">
Unit Price
</th>

<th class="p-5 text-left">
Quantity
</th>

<th class="p-5 text-left">
Subtotal
</th>

</tr>


</thead>



<tbody>


@forelse($order->items as $item)


<tr class="
border-t
border-[#E3EAE6]
">


<td class="p-5">


<p class="
font-semibold
text-[#173F35]
">

{{ $item->product_name ?? $item->product?->name ?? 'Product' }}

</p>


@if($item->product_slug)

<p class="
text-xs
text-gray-500
mt-1
">

{{ $item->product_slug }}

</p>

@endif


</td>





<td class="p-5">


@if(!empty($item->variation))


<div class="
text-sm
space-y-1
">


@foreach($item->variation as $key => $value)

<p>

<span class="text-gray-500">
{{ ucfirst($key) }}:
</span>

{{ is_array($value) ? implode(', ', $value) : $value }}

</p>

@endforeach


</div>


@elseif($item->variant_name)


{{ $item->variant_name }}


@else


-


@endif


</td>





<td class="p-5">

₱{{ number_format(
    (float) (
        $item->unit_price
        ?? (($item->unit_price_minor ?? 0) / 100)
    ),
    2
) }}

</td>





<td class="p-5">

{{ $item->quantity }}

</td>





<td class="p-5 font-semibold">

₱{{ number_format(
    (float) (
        $item->line_total
        ?? (
            (($item->unit_price_minor ?? 0) / 100)
            * $item->quantity
        )
    ),
    2
) }}

</td>


</tr>


@empty


<tr>

<td
colspan="5"
class="
p-8
text-center
text-gray-500
"
>

No order items found.

</td>

</tr>


@endforelse


</tbody>


</table>


</div>


</div>










<!-- SHIPPING + PAYMENT -->

<div
class="
grid
grid-cols-1
lg:grid-cols-2
gap-6
mb-6
"
>





<!-- SHIPPING INFORMATION -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Shipping Information

</h2>



<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Recipient
</p>

<p class="font-semibold">
{{ $order->recipient_name }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Contact Number
</p>

<p class="font-semibold">
{{ $order->recipient_phone }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Shipping Method
</p>

<p class="font-semibold">

{{ $order->shipping_method
    ? ucwords(str_replace('_', ' ', strtolower($order->shipping_method)))
    : '-'
}}

</p>

</div>



<div>

<p class="text-sm text-gray-500">
Delivery Address
</p>


<p class="font-semibold leading-relaxed">

{{ collect([
    $order->house_number,
    $order->street_address,
    $order->barangay,
    $order->municipality,
    $order->province,
    $order->postal_code
])->filter()->implode(', ') ?: '-' }}

</p>

</div>



<div>

<p class="text-sm text-gray-500">
Address Label
</p>

<p class="font-semibold">
{{ $order->address_label ?? '-' }}
</p>

</div>



<div>

<p class="text-sm text-gray-500">
Delivery Notes
</p>

<p class="font-semibold">
{{ $order->delivery_notes ?? '-' }}
</p>

</div>


</div>


</div>








<!-- PAYMENT INFORMATION -->

<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Payment Details

</h2>


@php

    $payment = $order->payments->first();

@endphp



<div class="space-y-5">


<div>

<p class="text-sm text-gray-500">
Method
</p>

<p class="font-semibold">

{{ $payment?->method
    ? ucwords(str_replace('_', ' ', strtolower($payment->method)))
    : (
        $order->payment_method
        ? ucwords(str_replace('_', ' ', strtolower($order->payment_method)))
        : '-'
    )
}}

</p>

</div>



<div>

<p class="text-sm text-gray-500">
Status
</p>

<p class="font-semibold">

{{ ucwords(strtolower(
    $payment?->status
    ?? $order->payment_status
    ?? 'UNPAID'
)) }}

</p>

</div>



<div>

<p class="text-sm text-gray-500">
Amount
</p>

<p class="font-semibold">

@if($payment)

₱{{ number_format($payment->amount_minor / 100, 2) }}

@else

₱{{ number_format((float) $order->total_amount, 2) }}

@endif

</p>

</div>



<div>

<p class="text-sm text-gray-500">
Provider Reference
</p>

<p class="font-semibold">
{{ $payment?->provider_ref ?? '-' }}
</p>

</div>


</div>


</div>



</div>










<!-- ORDER TIMELINE -->

@php

    $timeline = collect([

        [
            'label' => 'Order Placed',
            'time' => $order->placed_at,
        ],

        [
            'label' => 'Order Confirmed',
            'time' => $order->confirmed_at,
        ],

        [
            'label' => 'Preparing',
            'time' => $order->preparing_at,
        ],

        [
            'label' => 'Ready for Pickup',
            'time' => $order->ready_for_pickup_at,
        ],

        [
            'label' => 'Picked Up',
            'time' => $order->picked_up_at,
        ],

        [
            'label' => 'At Sorting Center',
            'time' => $order->at_sorting_center_at,
        ],

        [
            'label' => 'Sorted',
            'time' => $order->sorted_at,
        ],

        [
            'label' => 'Assigned to Rider',
            'time' => $order->assigned_to_rider_at,
        ],

        [
            'label' => 'Out for Delivery',
            'time' => $order->out_for_delivery_at,
        ],

        [
            'label' => 'Delivered',
            'time' => $order->delivered_at,
        ],

        [
            'label' => 'Completed',
            'time' => $order->completed_at,
        ],

        [
            'label' => 'Delivery Failed',
            'time' => $order->delivery_failed_at,
        ],

        [
            'label' => 'Returned',
            'time' => $order->returned_at,
        ],

        [
            'label' => 'Cancelled',
            'time' => $order->cancelled_at,
        ],

    ])->filter(fn ($event) => $event['time']);

@endphp



<div
class="
bg-white
rounded-3xl
border
border-[#E3EAE6]
p-8
shadow-sm
"
>


<h2 class="
text-xl
font-bold
text-[#173F35]
mb-6
">

Order Timeline

</h2>



@if($timeline->isNotEmpty())


<div class="space-y-6">


@foreach($timeline as $event)


<div class="
flex
items-start
gap-4
">


<div class="
w-3
h-3
rounded-full
bg-[#1F6F5B]
mt-2
shrink-0
">
</div>



<div>


<p class="
font-semibold
text-[#173F35]
">

{{ $event['label'] }}

</p>


<p class="
text-sm
text-gray-500
mt-1
">

{{ $event['time']->format('M d, Y h:i A') }}

</p>


</div>


</div>


@endforeach


</div>


@else


<p class="text-gray-500">

No order timeline events are available yet.

</p>


@endif


</div>






</div>


@endsection