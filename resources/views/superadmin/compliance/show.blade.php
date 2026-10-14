@extends('superadmin.layout')

@section('title', 'Compliance Case')

@section('content')
@php($isOpen = $violation->status === \App\Enums\SellerViolationStatus::Open)
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <a href="{{ route('superadmin.compliance.index') }}" class="text-sm font-semibold text-[#1F6F5B]">&larr; Compliance queue</a>
            <h1 class="mt-2 text-2xl font-bold text-[#173F35]">{{ $violation->reference }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $violation->seller->name }} &middot; {{ str($violation->category->value)->replace('_', ' ')->headline() }}</p>
        </div>
        <div class="flex gap-2">
            <span class="rounded-full bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800">{{ $violation->severity->value }}</span>
            <span class="rounded-full bg-[#EEF5F1] px-4 py-2 text-xs font-bold text-[#173F35]">{{ $violation->status->value }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Case details</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase text-gray-500">Seller</dt><dd class="mt-1 font-semibold"><a class="text-[#1F6F5B]" href="{{ route('superadmin.sellers.show', $violation->seller) }}">{{ $violation->seller->name }}</a></dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Created by</dt><dd class="mt-1 font-semibold">{{ $violation->creator->name ?? 'System' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Related product</dt><dd class="mt-1 font-semibold">{{ $violation->product->name ?? 'None' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Created</dt><dd class="mt-1 font-semibold">{{ $violation->created_at->format('M d, Y h:i A') }}</dd></div>
                </dl>
                <div class="mt-5"><div class="text-xs uppercase text-gray-500">Reason</div><p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $violation->reason }}</p></div>
                @if($violation->details)<div class="mt-5"><div class="text-xs uppercase text-gray-500">Details</div><p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $violation->details }}</p></div>@endif
                @if(!$isOpen)<div class="mt-5 rounded-xl bg-[#F6F9F7] p-4"><div class="text-xs uppercase text-gray-500">Resolution</div><p class="mt-2 text-sm text-gray-700">{{ $violation->resolution_notes }}</p><div class="mt-2 text-xs text-gray-500">{{ $violation->resolver->name ?? 'System' }} &middot; {{ $violation->resolved_at?->format('M d, Y h:i A') }}</div></div>@endif
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Evidence</h2>
                <div class="mt-4 space-y-3">
                    @forelse($violation->evidence as $evidence)
                        <div class="flex flex-col gap-3 rounded-xl border border-[#E3EAE6] p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div><div class="font-semibold text-[#173F35]">{{ $evidence->original_name }}</div><div class="mt-1 text-xs text-gray-500">{{ $evidence->mime_type }} &middot; {{ number_format($evidence->size / 1024, 1) }} KB &middot; {{ $evidence->uploader->name ?? 'System' }}</div>@if($evidence->description)<p class="mt-2 text-sm text-gray-700">{{ $evidence->description }}</p>@endif</div>
                            <div class="flex shrink-0 gap-3"><a href="{{ route('superadmin.compliance.evidence.show', [$violation, $evidence]) }}" target="_blank" rel="noopener" class="font-semibold text-[#1F6F5B] hover:underline">View</a><a href="{{ route('superadmin.compliance.evidence.download', [$violation, $evidence]) }}" class="font-semibold text-[#1F6F5B] hover:underline">Download</a></div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No evidence attached.</p>
                    @endforelse
                </div>
                @if($isOpen)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('superadmin.compliance.evidence.store', $violation) }}" class="mt-5 space-y-3 border-t border-[#E7EDE9] pt-5">
                        @csrf
                        <input type="file" name="evidence" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full rounded-xl border border-[#DCE5E0] p-2 text-sm">
                        <input name="description" maxlength="1000" placeholder="Evidence description (optional)" class="w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500">JPG, PNG, WebP, or PDF. Maximum 10 MB. Files are stored privately.</p>
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Upload evidence</button>
                    </form>
                @endif
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Warnings</h2>
                <div class="mt-4 space-y-3">
                    @forelse($violation->warnings as $warning)
                        <div class="rounded-xl border border-[#E3EAE6] p-4"><p class="text-sm text-gray-700">{{ $warning->message }}</p><div class="mt-2 text-xs text-gray-500">{{ $warning->issuer->name ?? 'System' }} &middot; {{ $warning->issued_at->format('M d, Y h:i A') }} &middot; {{ $warning->isAcknowledged() ? 'Acknowledged' : 'Not acknowledged' }}</div></div>
                    @empty
                        <p class="text-sm text-gray-500">No warnings issued.</p>
                    @endforelse
                </div>
                @if($isOpen)
                    <form method="POST" action="{{ route('superadmin.compliance.warnings.store', $violation) }}" class="mt-5 space-y-3 border-t border-[#E7EDE9] pt-5">
                        @csrf
                        <textarea name="message" required maxlength="2000" rows="3" placeholder="Warning message" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                        <button class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Issue warning</button>
                    </form>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Suspension impact</h2>
                <p class="mt-1 text-sm text-gray-500">Suspension applies to the owner account and every related operational profile.</p>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    @foreach($suspensionImpact['orders_by_status'] as $status => $count)
                        <div class="rounded-xl bg-[#F6F9F7] p-3"><div class="text-xs text-gray-500">{{ str($status)->replace('_', ' ')->headline() }}</div><div class="mt-1 text-xl font-bold text-[#173F35]">{{ $count }}</div></div>
                    @endforeach
                </div>
                <div class="mt-3 text-sm font-semibold {{ $suspensionImpact['active_orders_count'] > 0 ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ $suspensionImpact['active_orders_count'] }} active pre-pickup order(s)
                </div>

                <div class="mt-5 border-t border-[#E7EDE9] pt-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Affected seller profiles</div>
                    <div class="mt-3 space-y-2">
                        @foreach($suspensionImpact['stores'] as $store)
                            <div class="flex items-center justify-between rounded-xl bg-[#F6F9F7] px-3 py-2 text-sm"><span class="font-semibold">{{ $store['name'] }}</span><span>{{ $store['status'] }} &middot; {{ $store['active_orders_count'] }} active</span></div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 text-sm"><span class="font-semibold">Affected roles:</span> {{ collect($suspensionImpact['roles'])->map(fn ($role) => ucfirst($role))->join(', ') ?: 'None' }}</div>
                <div class="mt-2 text-sm"><span class="font-semibold">Logistics profile:</span> {{ $suspensionImpact['logistics_provider'] ? $suspensionImpact['logistics_provider']['name'].' ('.$suspensionImpact['logistics_provider']['status'].')' : 'None' }}</div>

                @if($isOpen && $suspensionImpact['active_orders_count'] > 0)
                    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Suspension is blocked until every listed order reaches PICKED_UP. Existing orders will not be cancelled or changed.
                    </div>
                @elseif($isOpen && $suspensionImpact['can_suspend'])
                    <form method="POST" action="{{ route('superadmin.compliance.suspend-seller', $violation) }}" class="mt-5 space-y-3 border-t border-[#E7EDE9] pt-5">
                        @csrf
                        <textarea name="reason" required maxlength="2000" rows="3" placeholder="Required suspension reason" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                        <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            <input type="checkbox" name="confirmation" value="1" required class="mt-1 rounded border-amber-400 text-red-600 focus:ring-red-500">
                            <span>I confirm this account-level suspension affects all listed seller profiles, roles, and the logistics profile shown above.</span>
                        </label>
                        <button class="w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white">Suspend seller account</button>
                    </form>
                @elseif($suspensionImpact['is_suspended'])
                    <div class="mt-5 rounded-xl bg-[#F6F9F7] p-4 text-sm text-gray-700">This seller account is already suspended.</div>
                @endif
            </section>

            @if($isOpen)
                <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-[#173F35]">Case actions</h2>
                    <form method="POST" action="{{ route('superadmin.compliance.severity', $violation) }}" class="mt-4 space-y-3">@csrf @method('PATCH')
                        <select name="severity" required class="w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">@foreach(\App\Enums\SellerViolationSeverity::cases() as $case)<option value="{{ $case->value }}" @selected($violation->severity === $case)>{{ $case->value }}</option>@endforeach</select>
                        <input name="reason" maxlength="2000" placeholder="Reason for severity change (optional)" class="w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                        <button class="w-full rounded-xl border border-[#173F35] px-4 py-2 text-sm font-semibold text-[#173F35]">Change severity</button>
                    </form>
                    <form method="POST" action="{{ route('superadmin.compliance.resolve', $violation) }}" class="mt-5 space-y-3 border-t pt-5">@csrf
                        <textarea name="resolution_notes" required maxlength="5000" rows="3" placeholder="Required resolution notes" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                        <button class="w-full rounded-xl bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Resolve case</button>
                    </form>
                    <form method="POST" action="{{ route('superadmin.compliance.dismiss', $violation) }}" class="mt-5 space-y-3 border-t pt-5">@csrf
                        <textarea name="resolution_notes" required maxlength="5000" rows="3" placeholder="Required dismissal notes" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                        <button class="w-full rounded-xl bg-gray-700 px-4 py-2 text-sm font-semibold text-white">Dismiss case</button>
                    </form>
                </section>
            @endif

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Compliance history</h2>
                <div class="mt-4 space-y-4">
                    @forelse($violation->events as $event)
                        <div class="border-l-2 border-[#BFD2C9] pl-3 text-sm"><div class="font-semibold text-[#173F35]">{{ str($event->action)->replace('_', ' ')->headline() }}</div><div class="mt-1 text-xs text-gray-500">{{ $event->created_at->format('M d, Y h:i A') }} &middot; {{ $event->actor->name ?? 'System' }}</div>@if($event->reason)<p class="mt-2 text-gray-700">{{ $event->reason }}</p>@endif</div>
                    @empty
                        <p class="text-sm text-gray-500">No compliance history.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
