@props(['user' => null, 'name' => null, 'photo' => null, 'size' => 'h-9 w-9'])

@php
    $displayName = trim((string) ($name ?? ($user?->full_name ?: $user?->name ?: '')));
    $initial = $displayName === '' ? 'U' : mb_strtoupper(mb_substr($displayName, 0, 1));
@endphp

@if(filled($photo))
    <img src="{{ Storage::disk('public')->url($photo) }}" alt="{{ $displayName ?: 'User' }} avatar" class="{{ $size }} rounded-full object-cover" />
@else
    <span aria-label="{{ $displayName ?: 'User' }} avatar" class="{{ $size }} inline-flex items-center justify-center rounded-full bg-[#173F35] font-semibold text-white">{{ $initial }}</span>
@endif
