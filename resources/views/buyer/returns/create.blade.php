@extends('layouts.app')
@section('content')
<main class="mx-auto max-w-3xl space-y-5 px-4 py-8">
    <a class="underline" href="{{ route('buyer.my-orders') }}">My Orders</a><h1 class="text-2xl font-bold">Return / Refund</h1>
    <section class="rounded-xl border bg-white p-5"><h2 class="font-semibold">{{ $item->product_name }}</h2>
        <p>Purchased variant: {{ $item->variant_name ?? $item->product_variant_id }} · Full quantity: {{ $item->quantity }}</p>
        <p>Original merchandise: ₱{{ number_format(($item->unit_price_minor * $item->quantity)/100,2) }}</p>
        @if($calculation)<p>Eligible refund: ₱{{ number_format($calculation['buyer_refund_due_minor']/100,2) }} (original discounts applied; shipping excluded)</p>@endif
    </section>
    @if(!$available)<p role="status">Return/refund submission is temporarily disabled pending schema and financial activation review. Existing requests remain available in My Returns &amp; Refunds.</p>@endif
    @if($eligibility)<p role="alert">{{ $eligibility }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ route('buyer.return-refunds.store',$item) }}" class="space-y-4 rounded-xl border bg-white p-5">
        @csrf <input type="hidden" name="type" value="RETURN"><input type="hidden" name="quantity" value="{{ $item->quantity }}">
        <p>The Seller will review your request. If approved, contact the Seller through Chat to arrange the refund and any item return. Approval does not confirm payment.</p>
        <fieldset @disabled(!$available || $eligibility) class="space-y-4">
            <label class="block">Reason<select name="reason" required class="block w-full rounded border">@foreach(['Damaged item','Incorrect item','Missing parts','Not as described','Other'] as $reason)<option @selected(old('reason')===$reason)>{{ $reason }}</option>@endforeach</select></label>
            <label class="block">Description<textarea name="description" required maxlength="5000" rows="4" class="block w-full rounded border">{{ old('description') }}</textarea></label>
            <section data-proof-upload class="rounded-xl border border-[#D6E8E1] bg-[#F1F8F5] p-4" aria-labelledby="return-proof-heading">
                <h2 id="return-proof-heading" class="font-semibold text-[#173F35]">Optional Proof Photo</h2>
                <p id="return-proof-help" class="mt-1 text-sm text-gray-600">JPG or PNG, maximum 5 MiB.</p>
                <div class="mt-3 flex min-w-0 flex-wrap items-center gap-3">
                    <input id="return-proof-photo" type="file" name="file" accept="image/jpeg,image/png" aria-label="Upload Proof Photo" aria-describedby="return-proof-help" class="block max-w-full text-sm text-gray-700">
                    <label hidden data-proof-choose for="return-proof-photo" class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1F6F5B] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#173F35] peer-disabled:cursor-not-allowed peer-disabled:opacity-50">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 16 5-5 5 5 3-3 5 5"/></svg>
                        Upload Proof Photo
                    </label>
                    <span hidden id="return-proof-filename" data-proof-filename role="status" aria-live="polite" aria-atomic="true" class="min-w-0 flex-1 text-sm text-gray-600 [overflow-wrap:anywhere]">No file selected</span>
                    <button hidden data-proof-remove type="button" class="min-h-11 rounded px-2 py-2 text-sm font-semibold text-[#173F35] underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#173F35]">Remove Photo</button>
                </div>
            </section>
            <label class="block"><input type="checkbox" name="confirmed" value="1" required> I confirm the purchased item, full quantity and refund amount above and want to submit this request.</label>
            <button class="rounded bg-[#173F35] px-4 py-2 text-white">Confirm and Submit Return / Refund</button>
        </fieldset>
    </form>
    <a class="underline" href="{{ route('buyer.return-refunds.index') }}">My Returns &amp; Refunds</a>
</main>
<script>
(() => {
    const section = document.querySelector('[data-proof-upload]');
    if (!section) return;
    const input = section.querySelector('input[type="file"]');
    const choose = section.querySelector('[data-proof-choose]');
    const filename = section.querySelector('[data-proof-filename]');
    const remove = section.querySelector('[data-proof-remove]');
    if (!input || !choose || !filename || !remove) return;

    const refresh = () => {
        const file = input.files && input.files[0];
        filename.textContent = file ? file.name : 'No file selected';
        remove.hidden = !file;
    };
    // Enhance only after controls are available; the native input is the fallback.
    input.classList.add('sr-only', 'peer');
    input.setAttribute('aria-describedby', 'return-proof-help return-proof-filename');
    choose.hidden = false;
    filename.hidden = false;
    refresh();
    input.addEventListener('change', refresh);
    // Native cancellation preserves the selection; do not reset on picker open/cancel.
    remove.addEventListener('click', () => {
        input.value = '';
        refresh();
        input.focus();
    });
})();
</script>
@endsection
