@extends($actorType === 'logistics' ? 'layouts.logistics' : 'layouts.rider')

@section('title', 'Shipment Scanner')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-[#173F35]">Shipment Scanner</h1>
        <p class="mt-1 text-sm text-[#617169]">Scan a SUKI waybill QR code or enter its tracking code. Shipment status and access are verified on the server.</p>
    </div>

    @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="rounded-xl border border-[#E5ECE8] bg-white p-4 shadow-sm sm:p-5" aria-labelledby="camera-scan-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#EEF5F1] text-[#1F6F5B]"><i data-lucide="scan-line" class="h-5 w-5" aria-hidden="true"></i></span>
                <div><h2 id="camera-scan-heading" class="font-semibold text-[#173F35]">Camera scan</h2><p class="text-xs text-[#728078]">Scan a waybill QR code to find its shipment.</p></div>
            </div>
            <div class="flex items-center gap-2">
                <button id="start-camera" type="button" class="rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1F6F5B] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Start Camera</button>
                <button id="stop-camera" type="button" class="hidden rounded-lg border border-[#DDE6E1] bg-white px-4 py-2 text-sm font-semibold text-[#355347] hover:bg-[#F5F8F6] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Stop Camera</button>
            </div>
        </div>

        <div id="camera-status" data-state="{{ $shipmentVerified ? 'verified' : 'idle' }}" role="status" aria-live="polite" class="mt-4 flex items-center gap-3 rounded-lg border border-[#E5ECE8] bg-[#FAFCFB] p-3">
            <span id="camera-state-icon" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#EEF5F1] text-[#1F6F5B]"><i data-lucide="{{ $shipmentVerified ? 'check' : 'camera' }}" class="h-4 w-4" aria-hidden="true"></i></span>
            <span id="camera-spinner" class="hidden h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-[#DDE6E1] border-t-[#1F6F5B]" aria-hidden="true"></span>
            <div class="min-w-0"><p id="camera-status-title" class="text-sm font-semibold text-[#34483F]">{{ $shipmentVerified ? 'Shipment verified' : 'Ready to scan' }}</p><p id="camera-message" class="mt-0.5 text-xs text-[#728078]">{{ $shipmentVerified ? 'QR verified successfully. Review the shipment details below.' : 'Start the camera or enter the tracking code manually below.' }}</p></div>
        </div>

        <div id="camera-preview" class="relative mt-4 hidden overflow-hidden rounded-lg bg-[#10201B]">
            <video id="scanner-video" class="hidden aspect-video max-h-[440px] w-full object-cover" playsinline muted aria-label="Live camera preview for scanning a shipment QR code"></video>
            <div id="scanner-target" class="pointer-events-none absolute inset-0 hidden items-center justify-center" aria-hidden="true">
                <div class="relative aspect-square w-[min(58vw,240px)] rounded-xl shadow-[0_0_0_999px_rgba(8,20,16,0.24)]">
                    <span class="absolute left-0 top-0 h-8 w-8 rounded-tl-lg border-l-2 border-t-2 border-[#DDF3EC]/80"></span>
                    <span class="absolute right-0 top-0 h-8 w-8 rounded-tr-lg border-r-2 border-t-2 border-[#DDF3EC]/80"></span>
                    <span class="absolute bottom-0 left-0 h-8 w-8 rounded-bl-lg border-b-2 border-l-2 border-[#DDF3EC]/80"></span>
                    <span class="absolute bottom-0 right-0 h-8 w-8 rounded-br-lg border-b-2 border-r-2 border-[#DDF3EC]/80"></span>
                    <span id="scanner-scan-line" class="absolute inset-x-2 top-1/2 h-px animate-pulse bg-[#DDF3EC]/80"></span>
                </div>
            </div>
            <svg id="qr-detection-overlay" class="pointer-events-none absolute inset-0 hidden h-full w-full" aria-hidden="true">
                <polygon id="qr-detection-polygon" points="" fill="rgba(31,111,91,0.16)" stroke="#1F6F5B" stroke-width="5" vector-effect="non-scaling-stroke"></polygon>
            </svg>
        </div>
    </section>

    <section class="rounded-2xl border border-[#E5ECE8] bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-[#173F35]">Manual fallback</h2>
        <form id="shipment-resolve-form" method="POST" action="{{ route($actorType === 'logistics' ? 'logistics.shipment-scanner.resolve' : 'rider.shipment-scanner.resolve') }}" class="mt-3 flex flex-col gap-3 sm:flex-row">
            @csrf
            <input type="hidden" name="mode" value="{{ $scannerMode ?? '' }}">
            <input type="hidden" name="hub_id" value="{{ request()->query('hub_id', '') }}">
            <input id="qr-payload" type="hidden" name="payload" value="">
            <input id="scan-payload" name="tracking_code" value="{{ $prefillTrackingCode ?? '' }}" class="flex-1 rounded-lg border-[#DDE6E1] text-sm" placeholder="Enter tracking code" autofocus>
            <button id="resolve-shipment" class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Find shipment</button>
        </form>
    </section>

    @if($shipment)
        <section class="rounded-2xl border border-[#D6E5DE] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-lg font-bold text-[#173F35]">{{ $shipment->tracking_code }}</p><p class="mt-1 text-sm text-[#617169]">Current status: {{ str_replace('_', ' ', strtolower($shipment->sellerOrder->workflow_status?->value ?? $shipment->sellerOrder->workflow_status)) }}</p></div><a href="{{ route('shipments.waybill', $shipment) }}" class="text-sm font-semibold text-[#1F6F5B] underline">View waybill</a></div>
            @php
                $destinationOrder = $shipment->sellerOrder->order;
                $destinationAddress = collect([
                    $destinationOrder->house_number,
                    $destinationOrder->street_address,
                    $destinationOrder->barangay,
                    $destinationOrder->municipality,
                    $destinationOrder->province,
                    $destinationOrder->postal_code,
                ])->filter()->implode(', ');
            @endphp
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-[#728078]">Order reference</dt><dd class="font-medium">{{ $destinationOrder->order_number }}</dd></div>
                <div><dt class="text-[#728078]">Seller</dt><dd class="font-medium">{{ $shipment->sellerOrder->seller->name }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[#728078]">Delivery address</dt><dd class="font-medium">{{ $destinationAddress ?: 'Address unavailable' }}</dd></div>
                <div><dt class="text-[#728078]">Destination area</dt><dd class="font-medium">{{ collect([$destinationOrder->barangay, $destinationOrder->municipality, $destinationOrder->province])->filter()->implode(', ') ?: 'Not specified' }}</dd></div>
                <div><dt class="text-[#728078]">Pickup rider</dt><dd class="font-medium">{{ $shipment->pickupRider?->user?->full_name ?? 'Not assigned' }}</dd></div>
            </dl>
            @if($allowedAction)
                <form method="POST" action="{{ route($actorType === 'logistics' ? 'logistics.shipment-scanner.action' : 'rider.shipment-scanner.action', $shipment) }}" class="mt-5 flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="action" value="{{ $allowedAction }}">
                    @if($allowedAction === 'mark_sorted')
                        @php($recommendation = $sortingResolution['zone'] ?? null)
                        <div class="w-full rounded-lg border border-[#DDE6E1] bg-[#FAFCFB] p-4 text-left sm:basis-full">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-[#728078]">Destination</p>
                            <p class="mt-1 text-sm font-medium text-[#24312C]">{{ collect([$destinationOrder->barangay, $destinationOrder->municipality, $destinationOrder->province])->filter()->implode(', ') ?: 'Location codes unavailable' }}</p>
                            <p class="mt-3 text-[10px] font-semibold uppercase tracking-wide text-[#728078]">Assigned Sorting Zone</p>
                            @if($recommendation)
                                <p class="mt-1 text-sm font-semibold text-[#173F35]">{{ $recommendation->hub?->name ?? 'Destination hub not configured' }}</p>
                                <p class="text-sm text-[#52635B]">{{ $recommendation->zone_code }} — {{ $recommendation->name }}</p>
                                <p class="mt-1 text-xs text-[#1F6F5B]">✓ Automatically determined from {{ $sortingResolution['specificity'] }} destination codes.</p>
                            @elseif(($sortingResolution['ambiguous'] ?? false))
                                <p role="status" class="mt-1 text-sm text-amber-800">⚠ Multiple zones match at the same specificity and priority. Choose a zone manually.</p>
                            @else
                                <p role="status" class="mt-1 text-sm text-amber-800">⚠ No configured sorting zone matches this destination.</p>
                            @endif
                            @if($sortingZones->isNotEmpty())
                                @if($recommendation)
                                    <details class="mt-3 rounded-lg border border-[#DDE6E1] bg-white p-3">
                                        <summary class="cursor-pointer text-sm font-semibold text-[#1F6F5B]">Change Zone</summary>
                                        <label class="mt-3 block text-xs font-medium text-[#34483F]">Select active provider zone
                                            <select required name="sorting_zone_id" class="mt-1 block w-full rounded-lg border-[#DDE6E1] text-sm">
                                                @foreach($sortingZones as $zone)
                                                    <option value="{{ $zone->id }}" @selected($recommendation->id === $zone->id)>{{ $zone->zone_code }} — {{ $zone->name }}{{ $zone->hub ? ' · '.$zone->hub->name : '' }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="mt-3 block text-xs font-medium text-[#34483F]">Override reason <span class="font-normal text-[#728078]">(required if you change the zone)</span>
                                            <select name="override_reason" class="mt-1 block w-full rounded-lg border-[#DDE6E1] text-sm">
                                                <option value="">No override</option>
                                                <option value="temporary_route_change">Temporary route change</option>
                                                <option value="hub_capacity">Hub capacity</option>
                                                <option value="area_reassignment">Area reassignment</option>
                                                <option value="operational_exception">Operational exception</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </label>
                                        <label class="mt-3 block text-xs font-medium text-[#34483F]">Other reason note
                                            <input name="override_note" maxlength="250" class="mt-1 block w-full rounded-lg border-[#DDE6E1] text-sm" placeholder="Short operational note">
                                        </label>
                                    </details>
                                @else
                                    <label class="mt-3 block text-xs font-medium text-[#34483F]">Choose active provider zone
                                        <select required name="sorting_zone_id" class="mt-1 block w-full rounded-lg border-[#DDE6E1] text-sm"><option value="">Select a provider zone</option>@foreach($sortingZones as $zone)<option value="{{ $zone->id }}">{{ $zone->zone_code }} — {{ $zone->name }}{{ $zone->hub ? ' · '.$zone->hub->name : '' }}</option>@endforeach</select>
                                    </label>
                                    <label class="mt-3 block text-xs font-medium text-[#34483F]">Override reason (required)
                                        <select required name="override_reason" class="mt-1 block w-full rounded-lg border-[#DDE6E1] text-sm"><option value="">Select reason</option><option value="temporary_route_change">Temporary route change</option><option value="hub_capacity">Hub capacity</option><option value="area_reassignment">Area reassignment</option><option value="operational_exception">Operational exception</option><option value="other">Other</option></select>
                                    </label>
                                    <label class="mt-3 block text-xs font-medium text-[#34483F]">Other reason note (required for Other)
                                        <input name="override_note" maxlength="250" class="mt-1 block w-full rounded-lg border-[#DDE6E1] text-sm" placeholder="Short operational note">
                                    </label>
                                @endif
                            @else
                                <p class="mt-2 text-xs text-amber-800">No active zones are configured. <a class="font-semibold underline" href="{{ route('logistics.sorting-zones.index') }}">Configure sorting zones</a> before confirming.</p>
                            @endif
                        </div>
                    @endif
                    @if($allowedAction !== 'mark_sorted' || ($sortingZones->isNotEmpty() && (!$recommendation || $recommendation->hub?->is_active)))<button class="rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">{{ ['accept_pickup' => 'Accept pickup', 'confirm_pickup' => 'Confirm parcel pickup', 'arrive_sorting' => 'Confirm arrival at sorting center', 'receive_sorting' => 'Receive at sorting center', 'mark_sorted' => 'Sort to '.($recommendation?->hub?->name ?? 'Destination Hub'), 'dispatch_hub' => 'Dispatch to destination hub', 'arrive_hub' => 'Confirm arrival at destination hub', 'accept_hub_transfer' => 'Accept hub-transfer assignment', 'accept_delivery' => 'Accept delivery assignment', 'begin_delivery' => 'Start last-mile delivery', 'receive_sorting' => 'Receive at sorting center'][$allowedAction] }}</button>@endif
                </form>
                @if($actorType === 'rider' && $allowedAction === 'accept_hub_transfer')
                    <form method="POST" action="{{ route('rider.shipment-scanner.action', $shipment) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="action" value="decline_hub_transfer">
                        <label class="min-w-[220px] flex-1 text-xs font-medium text-[#34483F]">Reason for declining<input name="transfer_decline_reason" minlength="3" maxlength="250" required class="mt-1 block w-full rounded-lg border-[#DDE6E1] text-sm" placeholder="Short operational reason"></label>
                        <button class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-900">Decline Transfer</button>
                    </form>
                @endif
            @else
                <p class="mt-5 rounded-lg bg-[#F5F8F6] p-3 text-sm text-[#52635B]">No scan action is available for your role at this shipment stage.</p>
            @endif
        </section>
    @endif
</div>

@vite('resources/js/shipment-scanner.js')
@endsection
