@forelse($buyers as $buyer)


<tr class="
border-t
border-[#E7ECE9]
hover:bg-[#FBFCFB]
transition
">


<td class="
px-5
py-4
text-sm
font-semibold
text-[#253831]
">

{{ $buyer->name }}

</td>



<td class="
px-5
py-4
text-sm
">

{{ $buyer->email }}

</td>



<td class="
px-5
py-4
text-sm
">

{{ $buyer->phone ?? '-' }}

</td>



<td class="
px-5
py-4
text-sm
">

{{ $buyer->buyer_orders_count }}

</td>



<td class="
px-5
py-4
text-sm
">

₱{{ number_format($buyer->buyer_orders_sum_total_amount ?? 0,2) }}

</td>



<td class="
px-5
py-4
text-sm
">

{{ $buyer->created_at->format('M d, Y') }}

</td>



<td class="px-5 py-4">


<span class="
inline-flex
px-3
py-1
rounded-full
text-xs
font-medium

{{ $buyer->status === 'active'
    ? 'bg-green-100 text-green-700'
    :
    (
        $buyer->status === 'suspended'
        ? 'bg-red-100 text-red-700'
        :
        'bg-yellow-100 text-yellow-700'
    )
}}

">

{{ ucfirst($buyer->status) }}

</span>


</td>




<td class="px-5 py-4">


<form

method="POST"

action="{{ route('superadmin.buyers.status',$buyer) }}"

class="
flex
items-center
gap-2
"

>

@csrf



<select

name="status"

class="
w-[120px]
border
border-[#D6DFDA]
rounded-lg
px-3
py-2
text-xs
bg-white
"

>


@if($buyer->status !== 'active')

<option value="active">
Activate
</option>

@endif


@if($buyer->status !== 'inactive')

<option value="inactive">
Set Inactive
</option>

@endif


@if($buyer->status !== 'suspended')

<option value="suspended">
Suspend
</option>

@endif


</select>




<button

type="submit"

class="
text-xs
font-semibold
text-[#1F6F5B]
hover:text-[#155244]
transition
"

>

Update

</button>



</form>


</td>



</tr>


@empty


<tr>

<td

colspan="8"

class="
px-6
py-12
text-center
text-sm
text-gray-500
"

>

No buyer data available.

</td>

</tr>


@endforelse