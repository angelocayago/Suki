@props([
    'colspan' => 1,
    'title' => 'No records found',
    'message' => 'Try adjusting the current filters.',
])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-14 text-center">
        <p class="text-sm font-semibold text-[#33443D]">{{ $title }}</p>
        <p class="mt-1 text-xs text-gray-500">{{ $message }}</p>
    </td>
</tr>
