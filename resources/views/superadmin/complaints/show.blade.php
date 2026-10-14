@extends('superadmin.layout')

@section('title', 'Complaint Case')

@section('content')
@php
    $isClosed = $complaint->status->isTerminal();
    $nextStatuses = collect(\App\Enums\ComplaintStatus::cases())
        ->filter(fn ($status) => ! $status->isTerminal() && $complaint->status->canTransitionTo($status));
    $canResolve = $complaint->status->canTransitionTo(\App\Enums\ComplaintStatus::Resolved);
    $canDismiss = $complaint->status->canTransitionTo(\App\Enums\ComplaintStatus::Dismissed);
    $shipment = $complaint->shipment;
    $snapshot = $complaint->context_snapshot ?? [];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <a href="{{ route('superadmin.complaints.index') }}" class="text-sm font-semibold text-[#1F6F5B]">&larr; Complaint queue</a>
            <h1 class="mt-2 text-2xl font-bold text-[#173F35]">{{ $complaint->reference }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ str(strtolower($complaint->category->value))->replace('_', ' ')->headline() }} &middot; created {{ $complaint->created_at->format('M d, Y h:i A') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="self-start rounded-full bg-[#EEF5F1] px-4 py-2 text-xs font-bold text-[#173F35]">{{ str(strtolower($complaint->status->value))->replace('_', ' ')->headline() }}</span>
            @if((int) $complaint->assigned_to === (int) auth()->id())
                <form method="POST" action="{{ route('superadmin.messages.context.complaint.store', $complaint) }}">
                    @csrf
                    <button type="submit" class="rounded-xl border border-[#1F6F5B] px-4 py-2 text-xs font-semibold text-[#1F6F5B]">Message Complainant</button>
                </form>
            @endif
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
                <h2 class="text-lg font-bold text-[#173F35]">Complaint information</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase text-gray-500">Complainant</dt><dd class="mt-1 font-semibold">{{ $complaint->complainant->name ?? 'Deleted user' }}</dd><dd class="text-xs text-gray-500">{{ str(strtolower($complaint->complainant_role))->headline() }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Assigned admin</dt><dd class="mt-1 font-semibold">{{ $complaint->assignee->name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Category</dt><dd class="mt-1 font-semibold">{{ str(strtolower($complaint->category->value))->replace('_', ' ')->headline() }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Waiting response from</dt><dd class="mt-1 font-semibold">{{ $complaint->waiting_on ? str(strtolower($complaint->waiting_on->value))->replace('_', ' ')->headline() : 'Nobody' }}</dd></div>
                </dl>
                <div class="mt-5"><div class="text-xs uppercase text-gray-500">Subject</div><p class="mt-2 font-semibold text-[#173F35]">{{ $complaint->subject }}</p></div>
                <div class="mt-5"><div class="text-xs uppercase text-gray-500">Description</div><p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $complaint->description }}</p></div>
                @if($isClosed)
                    <div class="mt-5 rounded-xl bg-[#F6F9F7] p-4">
                        <div class="text-xs uppercase text-gray-500">Resolution</div>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $complaint->resolution_notes }}</p>
                        <div class="mt-2 text-xs text-gray-500">{{ $complaint->resolver->name ?? 'System' }} &middot; {{ $complaint->resolved_at?->format('M d, Y h:i A') }}</div>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Complaint evidence</h2>
                <p class="mt-1 text-xs text-gray-500">Evidence is stored privately and available only through protected routes.</p>
                <div class="mt-4 space-y-3">
                    @forelse($complaint->evidence as $evidence)
                        <div class="flex flex-col gap-3 rounded-xl border border-[#E3EAE6] p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-semibold text-[#173F35]">{{ $evidence->original_name }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $evidence->mime_type }} &middot; {{ number_format($evidence->size / 1024, 1) }} KB &middot; {{ $evidence->uploader->name ?? 'Deleted user' }}</div>
                                @if($evidence->description)<p class="mt-2 text-sm text-gray-700">{{ $evidence->description }}</p>@endif
                            </div>
                            <div class="flex shrink-0 gap-3 text-sm"><a href="{{ route('superadmin.complaints.evidence.show', [$complaint, $evidence]) }}" target="_blank" rel="noopener" class="font-semibold text-[#1F6F5B]">View</a><a href="{{ route('superadmin.complaints.evidence.download', [$complaint, $evidence]) }}" class="font-semibold text-[#1F6F5B]">Download</a></div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No complaint evidence attached.</p>
                    @endforelse
                </div>
                @if(!$isClosed)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('superadmin.complaints.evidence.store', $complaint) }}" class="mt-5 space-y-3 border-t border-[#E7EDE9] pt-5">
                        @csrf
                        <input type="file" name="evidence" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="block w-full rounded-xl border border-[#DCE5E0] p-2 text-sm">
                        <input name="description" maxlength="1000" placeholder="Evidence description (optional)" class="w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500">JPG, PNG, WebP, or PDF. Maximum 10 MB.</p>
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Upload evidence</button>
                    </form>
                @endif
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-lg font-bold text-[#173F35]">Current ERP context</h2><p class="mt-1 text-xs text-gray-500">Read-only operational records. Complaint actions do not change these states.</p></div>
                </div>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase text-gray-500">Order</dt><dd class="mt-1 font-semibold">@if($complaint->order)<a class="text-[#1F6F5B] hover:underline" href="{{ route('superadmin.orders.show', $complaint->order) }}">{{ $complaint->order->order_number }}</a>@else Not linked @endif</dd><dd class="text-xs text-gray-500">Status: {{ $complaint->order->status ?? 'N/A' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Seller order</dt><dd class="mt-1 font-semibold">{{ $complaint->sellerOrder ? '#'.$complaint->sellerOrder->id : 'Not linked' }}</dd><dd class="text-xs text-gray-500">Status: {{ $complaint->sellerOrder?->workflow_status?->value ?? 'N/A' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Buyer</dt><dd class="mt-1 font-semibold">{{ $complaint->buyer->name ?? $complaint->order?->buyer?->name ?? 'Not linked' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Seller/store</dt><dd class="mt-1 font-semibold">@if($complaint->seller)<a class="text-[#1F6F5B] hover:underline" href="{{ route('superadmin.sellers.show', $complaint->seller) }}">{{ $complaint->seller->name }}</a>@else Not linked @endif</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Product</dt><dd class="mt-1 font-semibold">@if($complaint->product)<a class="text-[#1F6F5B] hover:underline" href="{{ route('superadmin.products.show', $complaint->product) }}">{{ $complaint->product->name }}</a>@else Not linked @endif</dd><dd class="text-xs text-gray-500">{{ $complaint->product?->category?->name }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Shipment</dt><dd class="mt-1 font-semibold">{{ $shipment->tracking_code ?? 'Not linked' }}</dd><dd class="text-xs text-gray-500">Status: {{ $shipment?->workflow_status?->value ?? 'N/A' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Pickup rider</dt><dd class="mt-1 font-semibold">{{ $shipment?->pickupRider?->user?->name ?? 'Not assigned' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Delivery rider</dt><dd class="mt-1 font-semibold">{{ $shipment?->rider?->user?->name ?? 'Not assigned' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Logistics provider</dt><dd class="mt-1 font-semibold">{{ $shipment?->provider?->name ?? $complaint->logisticsProvider?->name ?? 'Not assigned' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Payment status</dt><dd class="mt-1 font-semibold">{{ $complaint->order->payment_status ?? 'N/A' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Context captured when filed</h2>
                <p class="mt-1 text-xs text-gray-500">This snapshot remains historical even if linked records later change.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse($snapshot as $section => $values)
                        @if(is_array($values))
                            <div class="rounded-xl bg-[#F6F9F7] p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str($section)->replace('_', ' ')->headline() }}</div>
                                <dl class="mt-2 space-y-1 text-sm">
                                    @foreach($values as $label => $value)
                                        <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ str($label)->replace('_', ' ')->headline() }}</dt><dd class="text-right font-medium text-gray-700">{{ is_scalar($value) ? $value : json_encode($value) }}</dd></div>
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                    @empty
                        <p class="text-sm text-gray-500">No context snapshot was recorded.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            @if(!$isClosed)
                <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-[#173F35]">Case actions</h2>

                    <form method="POST" action="{{ route('superadmin.complaints.assignment', $complaint) }}" class="mt-4 space-y-3">
                        @csrf @method('PATCH')
                        <select name="assigned_to" required class="w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                            <option value="">Select active admin</option>
                            @foreach($admins as $admin)
                                <option value="{{ $admin->id }}" @selected($complaint->assigned_to === $admin->id)>{{ $admin->name }}</option>
                            @endforeach
                        </select>
                        <button class="w-full rounded-xl border border-[#173F35] px-4 py-2 text-sm font-semibold text-[#173F35]">Assign complaint</button>
                    </form>

                    @if($nextStatuses->isNotEmpty())
                        <form method="POST" action="{{ route('superadmin.complaints.status', $complaint) }}" class="mt-5 space-y-3 border-t border-[#E7EDE9] pt-5">
                            @csrf @method('PATCH')
                            <select name="status" required class="w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                                @foreach($nextStatuses as $status)
                                    <option value="{{ $status->value }}">{{ str(strtolower($status->value))->replace('_', ' ')->headline() }}</option>
                                @endforeach
                            </select>
                            @if($nextStatuses->contains(\App\Enums\ComplaintStatus::WaitingResponse))
                                <select name="waiting_on" required class="w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                                    <option value="">Waiting response from</option>
                                    @foreach(\App\Enums\ComplaintWaitingOn::cases() as $party)
                                        <option value="{{ $party->value }}">{{ str(strtolower($party->value))->replace('_', ' ')->headline() }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <textarea name="notes" maxlength="5000" rows="3" placeholder="Status note (optional)" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                            <button class="w-full rounded-xl bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Update status</button>
                        </form>
                    @endif

                    @if($canResolve)
                        <form method="POST" action="{{ route('superadmin.complaints.resolve', $complaint) }}" class="mt-5 space-y-3 border-t border-[#E7EDE9] pt-5">
                            @csrf
                            <textarea name="resolution_notes" required maxlength="5000" rows="3" placeholder="Required resolution notes" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                            <button class="w-full rounded-xl bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Resolve complaint</button>
                        </form>
                    @endif

                    @if($canDismiss)
                        <form method="POST" action="{{ route('superadmin.complaints.dismiss', $complaint) }}" class="mt-5 space-y-3 border-t border-[#E7EDE9] pt-5">
                            @csrf
                            <textarea name="resolution_notes" required maxlength="5000" rows="3" placeholder="Required dismissal notes" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                            <button class="w-full rounded-xl bg-gray-700 px-4 py-2 text-sm font-semibold text-white">Dismiss complaint</button>
                        </form>
                    @endif
                </section>
            @else
                <div class="rounded-2xl border border-[#DCE5E0] bg-[#F6F9F7] p-5 text-sm text-gray-700">This complaint is closed and cannot be modified.</div>
            @endif

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Complaint history</h2>
                <div class="mt-4 space-y-4">
                    @forelse($complaint->events as $event)
                        <div class="border-l-2 border-[#BFD2C9] pl-3 text-sm">
                            <div class="font-semibold text-[#173F35]">{{ str(strtolower($event->action))->replace('_', ' ')->headline() }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $event->created_at->format('M d, Y h:i A') }} &middot; {{ $event->actor->name ?? 'System' }}</div>
                            @if($event->previous_status || $event->new_status)
                                <p class="mt-2 text-gray-700">{{ $event->previous_status?->value ?? 'New' }} &rarr; {{ $event->new_status?->value ?? 'No status change' }}</p>
                            @endif
                            @if($event->newAssignee)
                                <p class="mt-1 text-gray-700">Assigned to {{ $event->newAssignee->name }}</p>
                            @endif
                            @if($event->waiting_on)
                                <p class="mt-1 text-gray-700">Waiting for {{ str(strtolower($event->waiting_on->value))->replace('_', ' ')->headline() }}</p>
                            @endif
                            @if($event->notes)<p class="mt-2 whitespace-pre-line text-gray-700">{{ $event->notes }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No complaint history.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
