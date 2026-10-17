@props(['status' => null])

@php
    $value = trim((string) ($status ?? ''));
    $key = strtolower(preg_replace('/[_-]+/', ' ', $value));
    $colors = match ($key) {
        'active', 'approved', 'verified' => 'bg-emerald-50 text-emerald-700',
        'pending', 'under review', 'awaiting approval' => 'bg-amber-50 text-amber-700',
        'suspended', 'rejected', 'disabled', 'inactive' => 'bg-red-50 text-red-700',
        default => 'bg-gray-100 text-gray-600',
    };
    $label = $value !== '' ? \Illuminate\Support\Str::headline($value) : 'Not available';
@endphp

<span {{ $attributes->class(['inline-flex max-w-full items-center whitespace-normal break-words rounded-full px-2.5 py-1 text-xs font-medium leading-5', $colors]) }}>{{ $label }}</span>
