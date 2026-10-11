@forelse($sellers as $seller)


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

{{ strtoupper(substr($seller->name,0,1)) }}

</div>


<div>

<p class="
font-semibold
text-[#253831]
text-sm
">

{{ $seller->name }}

</p>


<p class="
text-xs
text-gray-500
">

{{ $seller->owner->email ?? '-' }}

</p>

</div>


</div>


</td>





<td class="px-5 py-4 text-sm">

{{ $seller->shop_name ?? $seller->name }}

</td>





<td class="px-5 py-4 text-sm">

{{ $seller->owner->email ?? '-' }}

</td>





<td class="px-5 py-4">


<span class="
inline-flex
px-3
py-1
rounded-full
text-xs
font-medium

{{ $seller->status === 'approved'
    ? 'bg-green-100 text-green-700'
    :
    (
        $seller->status === 'suspended'
        ? 'bg-red-100 text-red-700'
        :
        'bg-yellow-100 text-yellow-700'
    )
}}

">

{{ ucfirst($seller->status) }}

</span>


</td>





<td class="px-5 py-4 text-sm">

{{ $seller->products_count ?? $seller->products->count() }}

</td>





<td class="px-5 py-4 text-sm">

{{ $seller->created_at->format('M d, Y') }}

</td>





<td class="px-5 py-4 text-center">

<a

href="{{ route('superadmin.sellers.show',$seller) }}"

class="
text-xs
font-semibold
text-[#1F6F5B]
hover:text-[#155244]
"

>

View

</a>

</td>


</tr>


@empty


<tr>

<td colspan="7"

class="
px-6
py-12
text-center
text-sm
text-gray-500
"

>

No sellers found.

</td>

</tr>


@endforelse