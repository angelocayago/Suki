@props(['status'])

@php
    $normalizedStatus = strtolower((string) $status);
    $classes = match ($normalizedStatus) {
        'active', 'approved', 'completed', 'resolved', 'delivered' => 'bg-emerald-100 text-emerald-700',
        'pending', 'open', 'under_review', 'waiting_response' => 'bg-amber-100 text-amber-800',
        'suspended', 'rejected', 'inactive', 'dismissed', 'hidden' => 'bg-red-100 text-red-700',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold', $classes]) }}>
    {{ str($normalizedStatus)->replace('_', ' ')->title() }}
</span>
