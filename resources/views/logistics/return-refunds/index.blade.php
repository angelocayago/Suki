@extends('layouts.logistics')

@section('title', 'Return Hub Operations')
@section('page-heading', 'Return Hub Operations')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <header><h1 class="text-2xl font-bold text-[#24312C]">Return hub operations</h1><p class="mt-1 text-sm text-[#617169]">Verify buyer drop-offs and dispatch only returns assigned to your provider and designated barangay hub.</p></header>
    @if(session('success'))<p class="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-900" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-900" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @forelse($requests as $return)
        @php($workflow = $return->workflow)
        <section class="rounded-xl border border-[#DDE6E1] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold text-[#173F35]">RR-{{ $return->id }} · {{ $return->item?->product_name ?? 'Return item' }}</h2><p class="mt-1 text-sm">Buyer: {{ $return->buyer?->name ?? 'Buyer' }} · Hub: {{ $workflow?->returnHub?->name ?? 'Unavailable' }}</p><p class="mt-1 text-xs uppercase tracking-wide text-[#617169]">{{ str_replace('_', ' ', $workflow?->logistics_state ?? '') }}</p></div></div>
            @if($workflow?->logistics_state === 'AWAITING_BUYER_DROPOFF')
                <form method="POST" action="{{ route('logistics.return-refunds.hub-receipt', $return) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
                    <input type="hidden" name="hub_id" value="{{ $workflow->return_hub_id }}">
                    <label class="text-sm font-medium">Scan / verify return reference<input name="return_reference" required maxlength="100" class="mt-1 block rounded border border-[#B9CBC2] px-3 py-2" autocomplete="off"></label>
                    <label class="text-sm font-medium">Note (optional)<input name="note" maxlength="2000" class="mt-1 block rounded border border-[#B9CBC2] px-3 py-2"></label>
                    <button class="rounded bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Receive at hub</button>
                </form>
            @elseif($workflow?->logistics_state === 'RECEIVED_AT_RETURN_HUB')
                <form method="POST" action="{{ route('logistics.return-refunds.dispatch', $return) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
                    <input type="hidden" name="hub_id" value="{{ $workflow->return_hub_id }}">
                    <label class="text-sm font-medium">Dispatch note (optional)<input name="note" maxlength="2000" class="mt-1 block rounded border border-[#B9CBC2] px-3 py-2"></label>
                    <button class="rounded bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Dispatch to seller</button>
                </form>
            @endif
        </section>
    @empty
        <section class="rounded-xl border border-dashed border-[#B9CBC2] bg-[#F8FAF9] p-6 text-sm text-[#617169]">No authorized returns are assigned to this provider.</section>
    @endforelse
</div>
@endsection
