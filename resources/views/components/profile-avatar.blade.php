@props(['user' => null, 'name' => null, 'photo' => null, 'size' => 'h-9 w-9', 'circular' => false])

@php
    $displayName = trim((string) ($name ?? ($user?->full_name ?: $user?->name ?: '')));
    $initial = $displayName === '' ? 'U' : mb_strtoupper(mb_substr($displayName, 0, 1));
@endphp

@if($circular)
    <span class="{{ $size }} aspect-square inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#173F35] font-semibold text-white" @if(!filled($photo)) aria-label="{{ $displayName ?: 'User' }} avatar" @endif>
        @if(filled($photo))
            <img src="{{ Storage::disk('public')->url($photo) }}" alt="{{ $displayName ?: 'User' }} avatar" class="block h-full w-full object-cover" />
        @else
            {{ $initial }}
        @endif
    </span>
@elseif(filled($photo))
    <img src="{{ Storage::disk('public')->url($photo) }}" alt="{{ $displayName ?: 'User' }} avatar" class="{{ $size }} rounded-full object-cover" />
@else
    <span aria-label="{{ $displayName ?: 'User' }} avatar" class="{{ $size }} inline-flex items-center justify-center rounded-full bg-[#173F35] font-semibold text-white">{{ $initial }}</span>
@endif
