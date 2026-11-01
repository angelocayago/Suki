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

    <section class="rounded-2xl border border-[#E5ECE8] bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-[#173F35]">Camera scan</h2><p class="text-sm text-[#617169]">Camera processing stays in this browser. If unsupported or denied, use the manual entry below.</p></div><button id="start-camera" type="button" class="rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Start camera</button></div>
        <video id="scanner-video" class="mt-4 hidden w-full rounded-xl bg-black" playsinline muted></video>
        <p id="camera-message" class="mt-3 text-sm text-[#617169]"></p>
    </section>

    <section class="rounded-2xl border border-[#E5ECE8] bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-[#173F35]">Manual fallback</h2>
        <form method="POST" action="{{ route($actorType === 'logistics' ? 'logistics.shipment-scanner.resolve' : 'rider.shipment-scanner.resolve') }}" class="mt-3 flex flex-col gap-3 sm:flex-row">
            @csrf
            <input id="scan-payload" name="payload" class="flex-1 rounded-lg border-[#DDE6E1] text-sm" placeholder="Paste QR payload or enter tracking code" autofocus>
            <button class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Find shipment</button>
        </form>
    </section>

    @if($shipment)
        <section class="rounded-2xl border border-[#D6E5DE] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-mono text-lg font-bold text-[#173F35]">{{ $shipment->tracking_code }}</p><p class="mt-1 text-sm text-[#617169]">Current status: {{ str_replace('_', ' ', strtolower($shipment->sellerOrder->workflow_status?->value ?? $shipment->sellerOrder->workflow_status)) }}</p></div><a href="{{ route('shipments.waybill', $shipment) }}" class="text-sm font-semibold text-[#1F6F5B] underline">View waybill</a></div>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-[#728078]">Order</dt><dd class="font-medium">{{ $shipment->sellerOrder->order->order_number }}</dd></div><div><dt class="text-[#728078]">Store</dt><dd class="font-medium">{{ $shipment->sellerOrder->seller->name }}</dd></div></dl>
            @if($allowedAction)
                <form method="POST" action="{{ route($actorType === 'logistics' ? 'logistics.shipment-scanner.action' : 'rider.shipment-scanner.action', $shipment) }}" class="mt-5 flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="action" value="{{ $allowedAction }}">
                    @if($allowedAction === 'mark_sorted')<label class="text-sm font-medium text-[#24312C]">Sorting area<input required name="sorting_area" class="mt-1 block rounded-lg border-[#DDE6E1] text-sm" placeholder="Sorting area"></label>@endif
                    <button class="rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">{{ ['accept_pickup' => 'Accept pickup', 'confirm_pickup' => 'Confirm parcel pickup', 'arrive_sorting' => 'Confirm arrival at sorting center', 'receive_sorting' => 'Receive at sorting center', 'mark_sorted' => 'Mark parcel sorted', 'accept_delivery' => 'Accept delivery assignment', 'begin_delivery' => 'Mark out for delivery'][$allowedAction] }}</button>
                </form>
            @else
                <p class="mt-5 rounded-lg bg-[#F5F8F6] p-3 text-sm text-[#52635B]">No scan action is available for your role at this shipment stage.</p>
            @endif
        </section>
    @endif
</div>

<script>
(() => {
    const button = document.getElementById('start-camera'); const video = document.getElementById('scanner-video'); const message = document.getElementById('camera-message'); const input = document.getElementById('scan-payload');
    button?.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia || !window.BarcodeDetector) { message.textContent = 'Camera QR scanning is not supported here. Use the manual entry field.'; return; }
        try {
            const detector = new BarcodeDetector({ formats: ['qr_code'] }); const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = stream; video.classList.remove('hidden'); await video.play(); message.textContent = 'Point the camera at the QR code.';
            const detect = async () => { try { const codes = await detector.detect(video); if (codes[0]?.rawValue) { input.value = codes[0].rawValue; stream.getTracks().forEach(track => track.stop()); video.classList.add('hidden'); input.form.submit(); return; } } catch (_) {} requestAnimationFrame(detect); };
            requestAnimationFrame(detect);
        } catch (_) { message.textContent = 'Camera permission was unavailable. Use the manual entry field.'; }
    });
})();
</script>
@endsection
