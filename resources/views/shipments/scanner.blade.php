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

        <div id="camera-status" data-state="idle" role="status" aria-live="polite" class="mt-4 flex items-center gap-3 rounded-lg border border-[#E5ECE8] bg-[#FAFCFB] p-3">
            <span id="camera-state-icon" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#EEF5F1] text-[#1F6F5B]"><i data-lucide="camera" class="h-4 w-4" aria-hidden="true"></i></span>
            <span id="camera-spinner" class="hidden h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-[#DDE6E1] border-t-[#1F6F5B]" aria-hidden="true"></span>
            <div class="min-w-0"><p id="camera-status-title" class="text-sm font-semibold text-[#34483F]">Ready to scan</p><p id="camera-message" class="mt-0.5 text-xs text-[#728078]">Start the camera or enter the tracking code manually below.</p></div>
        </div>

        <div id="camera-preview" class="relative mt-4 hidden overflow-hidden rounded-lg bg-[#10201B]">
            <video id="scanner-video" class="hidden aspect-video max-h-[440px] w-full object-cover" playsinline muted aria-label="Live camera preview for scanning a shipment QR code"></video>
            <div id="scanner-target" class="pointer-events-none absolute inset-0 hidden items-center justify-center" aria-hidden="true">
                <div class="relative aspect-square w-[min(58vw,240px)] rounded-xl border border-[#DDF3EC]/70 shadow-[0_0_0_999px_rgba(8,20,16,0.24)]">
                    <span class="absolute inset-x-2 top-1/2 h-px animate-pulse bg-[#DDF3EC]/80"></span>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-[#E5ECE8] bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-[#173F35]">Manual fallback</h2>
        <form id="shipment-resolve-form" method="POST" action="{{ route($actorType === 'logistics' ? 'logistics.shipment-scanner.resolve' : 'rider.shipment-scanner.resolve') }}" class="mt-3 flex flex-col gap-3 sm:flex-row">
            @csrf
            <input id="scan-payload" name="tracking_code" value="{{ $prefillTrackingCode ?? '' }}" class="flex-1 rounded-lg border-[#DDE6E1] text-sm" placeholder="Paste QR payload or enter tracking code" autofocus>
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
                    @if($allowedAction === 'mark_sorted')<label class="text-sm font-medium text-[#24312C]">Sorting area<input required name="sorting_area" class="mt-1 block rounded-lg border-[#DDE6E1] text-sm" placeholder="Sorting area"></label>@endif
                    <button class="rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">{{ ['accept_pickup' => 'Accept pickup', 'confirm_pickup' => 'Confirm parcel pickup', 'arrive_sorting' => 'Confirm arrival at sorting center', 'receive_sorting' => 'Receive at sorting center', 'mark_sorted' => 'Confirm Sorting', 'accept_delivery' => 'Accept delivery assignment', 'begin_delivery' => 'Mark out for delivery'][$allowedAction] }}</button>
                </form>
            @else
                <p class="mt-5 rounded-lg bg-[#F5F8F6] p-3 text-sm text-[#52635B]">No scan action is available for your role at this shipment stage.</p>
            @endif
        </section>
    @endif
</div>

<script>
(() => {
    const startButton = document.getElementById('start-camera');
    const stopButton = document.getElementById('stop-camera');
    const video = document.getElementById('scanner-video');
    const preview = document.getElementById('camera-preview');
    const target = document.getElementById('scanner-target');
    const status = document.getElementById('camera-status');
    const statusIcon = document.getElementById('camera-state-icon');
    const spinner = document.getElementById('camera-spinner');
    const title = document.getElementById('camera-status-title');
    const message = document.getElementById('camera-message');
    const form = document.getElementById('shipment-resolve-form');
    const resolveButton = document.getElementById('resolve-shipment');
    const input = document.getElementById('scan-payload');
    if (!startButton || !stopButton || !video || !form || !input) return;

    let stream = null;
    let detector = null;
    let animationId = null;
    let isStarting = false;
    let isScanning = false;
    let isDetecting = false;
    let isSubmitting = false;
    let detected = false;
    let pageLeaving = false;
    let detectDelay = null;
    let submitDelay = null;

    const showIcon = (name) => {
        statusIcon.innerHTML = `<i data-lucide="${name}" class="h-4 w-4" aria-hidden="true"></i>`;
        window.lucide?.createIcons();
    };
    const setState = (state, heading, detail, icon, loading = false) => {
        status.dataset.state = state;
        title.textContent = heading;
        message.textContent = detail;
        spinner.classList.toggle('hidden', !loading);
        statusIcon.classList.toggle('hidden', loading);
        if (icon) showIcon(icon);
    };
    const cancelDetection = () => {
        isScanning = false;
        if (animationId !== null) cancelAnimationFrame(animationId);
        animationId = null;
    };
    const stopTracks = () => {
        cancelDetection();
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
        video.pause();
        video.srcObject = null;
        video.classList.add('hidden');
        preview.classList.add('hidden');
        target.classList.add('hidden');
        target.classList.remove('flex');
        stopButton.classList.add('hidden');
        stopButton.disabled = false;
        startButton.classList.remove('hidden');
        startButton.disabled = isSubmitting;
    };
    const resetCamera = () => {
        if (isStarting || isSubmitting) return;
        detected = false;
        detector = null;
        stopTracks();
        startButton.disabled = false;
        setState('idle', 'Ready to scan', 'Start the camera or enter the tracking code manually below.', 'camera');
    };
    const previewOnly = () => {
        detector = null;
        cancelDetection();
        target.classList.add('hidden');
        target.classList.remove('flex');
        startButton.classList.add('hidden');
        setState('preview-only', 'Camera preview is active', 'Automatic QR recognition is not supported by this browser. Use manual tracking entry.', 'camera');
    };
    const cameraError = (error) => {
        const name = error?.name;
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'No camera device was found.';
        if (window.isSecureContext === false) return 'Camera access is unavailable in this browser context. Use manual tracking entry.';
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') return 'Camera permission was denied. Allow camera access or use manual tracking entry.';
        if (name === 'NotReadableError' || name === 'AbortError' || name === 'TrackStartError') return 'Unable to start the camera. Close other apps using the camera or use manual tracking entry.';
        return 'Unable to start the camera. Close other apps using the camera or use manual tracking entry.';
    };
    const submitResolvedPayload = (value) => {
        if (detected || isSubmitting || !value) return;
        detected = true;
        input.value = value;
        stopTracks();
        startButton.disabled = true;
        setState('detected', 'QR Code Detected', 'The QR code was read. Preparing to verify the shipment.', 'check');
        detectDelay = setTimeout(() => {
            if (isSubmitting) return;
            setState('processing', 'Verifying shipment...', 'The server is validating the shipment and your access.', null, true);
            submitDelay = setTimeout(() => {
                if (isSubmitting) return;
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else {
                    isSubmitting = true;
                    resolveButton.disabled = true;
                    HTMLFormElement.prototype.submit.call(form);
                }
            }, 350);
        }, 550);
    };
    const scanFrame = async () => {
        if (!isScanning || !detector || isDetecting) return;
        if (video.readyState >= 2) {
            isDetecting = true;
            try {
                const codes = await detector.detect(video);
                if (!isScanning) return;
                const value = codes.find((code) => typeof code.rawValue === 'string' && code.rawValue.trim())?.rawValue;
                if (value) {
                    cancelDetection();
                    submitResolvedPayload(value);
                    return;
                }
            } catch (_) {
                cancelDetection();
                setState('error', 'QR recognition stopped', 'The camera could not read this frame. Stop and retry, or use manual tracking entry.', 'triangle-alert');
                return;
            } finally {
                isDetecting = false;
            }
        }
        if (isScanning) animationId = requestAnimationFrame(scanFrame);
    };
    const startCamera = async () => {
        if (isStarting || stream || isSubmitting) return;
        isStarting = true;
        detected = false;
        startButton.disabled = true;
        stopButton.classList.add('hidden');
        setState('starting', 'Opening camera...', 'Please allow camera access when your browser asks.', null, true);

        if (!navigator.mediaDevices?.getUserMedia) {
            isStarting = false;
            startButton.disabled = false;
            setState('error', 'Camera unavailable', window.isSecureContext === false ? 'Camera access is unavailable in this browser context. Use manual tracking entry.' : 'Camera scanning is not supported by this browser. Use manual tracking entry.', 'triangle-alert');
            return;
        }

        try {
            const cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            if (pageLeaving || isSubmitting) {
                cameraStream.getTracks().forEach((track) => track.stop());
                return;
            }
            stream = cameraStream;
            video.srcObject = stream;
            await video.play();
            if (pageLeaving || isSubmitting || stream !== cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                return;
            }
            preview.classList.remove('hidden');
            video.classList.remove('hidden');
            stopButton.classList.remove('hidden');
            startButton.disabled = true;

            if (typeof window.BarcodeDetector !== 'function') {
                previewOnly();
                return;
            }
            try {
                detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            } catch (_) {
                previewOnly();
                return;
            }

            target.classList.remove('hidden');
            target.classList.add('flex');
            startButton.classList.add('hidden');
            isScanning = true;
            setState('scanning', 'Scanning QR code...', 'Hold the waybill QR code inside the frame.', 'scan-line');
            animationId = requestAnimationFrame(scanFrame);
        } catch (error) {
            stopTracks();
            startButton.disabled = false;
            setState('error', 'Camera could not start', cameraError(error), 'triangle-alert');
        } finally {
            isStarting = false;
        }
    };

    startButton.addEventListener('click', startCamera);
    stopButton.addEventListener('click', resetCamera);
    form.addEventListener('submit', (event) => {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }
        isSubmitting = true;
        if (detectDelay !== null) clearTimeout(detectDelay);
        if (submitDelay !== null) clearTimeout(submitDelay);
        stopTracks();
        startButton.disabled = true;
        stopButton.disabled = true;
        resolveButton.disabled = true;
        setState('processing', 'Verifying shipment...', 'The server is validating the shipment and your access.', null, true);
    });
    const cleanupOnLeave = () => {
        pageLeaving = true;
        if (detectDelay !== null) clearTimeout(detectDelay);
        if (submitDelay !== null) clearTimeout(submitDelay);
        stopTracks();
    };
    window.addEventListener('pagehide', cleanupOnLeave);
    window.addEventListener('beforeunload', cleanupOnLeave);
})();
</script>
@endsection
