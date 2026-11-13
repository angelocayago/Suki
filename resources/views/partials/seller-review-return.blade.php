<main class="mx-auto max-w-3xl space-y-5 px-4 py-8">
    <a class="text-sm font-semibold text-[#1F6F5B]" href="{{ route($role.'.return-refunds.index') }}">Returns / Refunds</a>
    <section class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6">
        <h1 class="text-2xl font-bold">Return / Refund RR-{{ $returnRefundRequest->id }}</h1>
        <p class="font-semibold text-[#1F6F5B]">{{ $returnRefundRequest->statusLabel() }}</p>
        @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
        @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if(!$ready)<p role="status">New Seller decisions are unavailable until activation and schema readiness are verified. Existing requests remain readable.</p>@endif
        @if($role==='admin')<p>Seller-owned review. Admin monitoring is read-only.</p>@endif
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt>Product</dt><dd>{{ $returnRefundRequest->item?->product_name ?? 'Historical item' }}</dd></div>
            <div><dt>Purchased variant</dt><dd>{{ $returnRefundRequest->item?->variant_name ?? $returnRefundRequest->item?->product_variant_id }}</dd></div>
            <div><dt>Order</dt><dd>{{ $returnRefundRequest->order?->order_number ?? $returnRefundRequest->order?->reference ?? $returnRefundRequest->order_id }}</dd></div>
            <div><dt>Quantity</dt><dd>{{ $returnRefundRequest->quantity }}</dd></div>
            <div><dt>Buyer</dt><dd>{{ $returnRefundRequest->buyer?->full_name ?: $returnRefundRequest->buyer?->name }}</dd></div>
            <div><dt>Seller</dt><dd>{{ $returnRefundRequest->seller?->name }}</dd></div>
            <div><dt>{{ $returnRefundRequest->status==='ADMIN_APPROVED' ? 'Approved refund due' : 'Requested refund' }}</dt><dd>₱{{ number_format(($returnRefundRequest->refund_minor ?? $refundQuote ?? 0)/100,2) }}</dd></div>
        </dl>
        <p>Original discounts apply; shipping is excluded. Approval does not confirm payment to the Buyer.</p>
        <div><h2 class="font-semibold">Buyer concern</h2><p>{{ $returnRefundRequest->buyer_reason }}</p><p class="whitespace-pre-wrap">{{ $returnRefundRequest->buyer_description }}</p></div>
        <div><h2 class="font-semibold">Private proof images</h2>@forelse($evidence as $file)<a class="block underline" href="{{ route('return-refunds.evidence.show',[$returnRefundRequest,$file]) }}">View private proof image{{ $file->description ? ': '.$file->description : '' }}</a>@empty<p>No proof image attached.</p>@endforelse</div>
        @if($returnRefundRequest->decided_at)<div><h2 class="font-semibold">Seller decision reason</h2><p class="whitespace-pre-wrap">{{ $returnRefundRequest->seller_note }}</p><p>{{ \App\Support\ManilaTime::format($returnRefundRequest->decided_at) }} PHT</p></div>@endif
        @if($role==='seller' && $returnRefundRequest->status==='SUBMITTED')
            <form method="POST" action="{{ route('seller.return-refunds.decide',$returnRefundRequest) }}" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                @csrf @method('PATCH')
                <fieldset @disabled(!$ready) class="space-y-3">
                    <label class="block">Decision<select name="decision" required class="block w-full rounded border"><option value="" disabled selected>Select Decision</option><option value="APPROVED" @selected(old('decision')==='APPROVED')>Approve</option><option value="REJECTED" @selected(old('decision')==='REJECTED')>Reject</option></select></label>
                    <label class="block">Decision reason<textarea name="reason" required maxlength="5000" rows="4" class="block w-full rounded border">{{ old('reason') }}</textarea></label>
                    <button type="submit" class="rounded bg-[#1F6F5B] px-4 py-2 text-white">Finalize Seller decision</button>
                </fieldset>
            </form>
        @endif
        @if(in_array($role,['buyer','seller'],true))<form method="POST" action="{{ route($role.'.messages.context.seller-order.store',$returnRefundRequest->seller_order_id) }}">@csrf<button class="rounded border border-[#1F6F5B] px-4 py-2 text-[#1F6F5B]">{{ $role==='buyer' ? 'Chat with Seller' : 'Chat with Buyer' }}</button></form>@endif
        <div><h2 class="font-semibold">Request history</h2>@foreach($returnRefundRequest->events()->orderBy('created_at')->orderBy('id')->get() as $event)<p>{{ $event->action }} · {{ \App\Support\ManilaTime::format($event->created_at) }} PHT</p>@endforeach</div>
    </section>
</main>
