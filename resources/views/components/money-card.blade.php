@props(['label','amount'])
<div class="rounded-xl bg-white p-4 shadow"><p class="text-xs uppercase text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-bold text-[#173F35]">₱{{ number_format(((int) $amount) / 100, 2) }}</p></div>
