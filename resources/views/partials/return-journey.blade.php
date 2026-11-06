<section class="mx-auto my-5 max-w-5xl space-y-4 rounded-xl border bg-white p-5" aria-label="Return parcel and refund workflow">
    <h2 class="text-xl font-semibold">Return &amp; Refund Progress</h2>
    <p>Purchased variant: {{ $returnRefundRequest->item?->variant_name ?? $returnRefundRequest->item?->product_variant_id ?? 'Historical variant details unavailable' }} · Full quantity: {{ $returnRefundRequest->quantity }}</p>
    <p>Requested Refund: {{ $refundQuote===null?'Requires financial review':'₱'.number_format($refundQuote/100,2) }}. This is not a payment.@if(in_array($returnRefundRequest->status,\App\Models\ReturnRefundRequest::ACTIVE_STATUSES,true)) Awaiting Decision.@endif</p>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(!$ready)<p role="status">New return/refund actions are disabled pending schema and financial activation review. History and chat remain available.</p>@endif
    @if(!$workflow)<p>This historical request requires support review before using the structured return workflow. Approval is unavailable until the required workflow and evidence can be verified; Admin may record a justified rejection.</p>@else
        <p>Seller: {{ $returnRefundRequest->seller_recommendation==='APPROVE' ? 'Confirmed willingness to proceed' : ($returnRefundRequest->seller_recommendation==='REJECT' ? 'Disputed/declined — Admin review required' : 'Awaiting response') }}</p>
        <p>Return parcel: <strong>{{ ['PENDING'=>'Return shipping pending — not yet shipped','SHIPPED'=>'Return shipped','RECEIVED'=>'Returned parcel received'][$workflow->parcel_status] ?? 'Unknown parcel state — support review required' }}</strong></p>
        <p>Authorized Seller pickup/return destination, captured when requested: {{ $workflow->return_address }}</p>
        <div id="return-tracking"><h3 class="font-semibold">Track Return</h3><p>Courier: {{ $workflow->courier ?: 'Not yet supplied' }} · Tracking number: {{ $workflow->tracking_number ?: 'Not yet shipped' }}</p><p>Shipping date: {{ $workflow->shipped_on?->format('M j, Y') ?? 'Not supplied' }}</p></div>
        <a class="underline" href="#return-tracking">Track Return</a>
        @if($workflow->received_at)<p>Received {{ $workflow->received_at->format('M j, Y') }} · Inspection: {{ $workflow->inspection_result }} — {{ $workflow->inspection_note }}</p>@endif
        @if(in_array($role,['buyer','admin'],true) && $workflow->destination)
            <details><summary>Private refund destination (Buyer and authorized Admin only)</summary><p>{{ $workflow->destination['method'] }} · {{ $workflow->destination['holder'] }} · {{ $workflow->destination['bank'] ?? '' }} · {{ $workflow->destination['account'] }}</p></details>
        @endif
        @if($role==='buyer' && $returnRefundRequest->seller_recommendation==='APPROVE' && $workflow->parcel_status!=='RECEIVED' && in_array($returnRefundRequest->status,\App\Models\ReturnRefundRequest::ACTIVE_STATUSES,true))
            <h3 class="font-semibold">Submit Refund &amp; Return Details</h3>
            <p>Keep payment information in this private form; do not send account details in chat.</p>
            <form action="{{ route('buyer.return-refunds.details',$returnRefundRequest) }}" method="POST" class="space-y-3">
                @csrf <fieldset @disabled(!$ready) class="grid gap-3 sm:grid-cols-2">
                    <label>Destination type<select name="method" required class="block w-full rounded border"><option>GCASH</option><option>BANK</option></select></label>
                    <label>Account holder<input name="holder" required maxlength="100" autocomplete="off" class="block w-full rounded border"></label>
                    <label>Account / GCash number<input name="account" type="password" required maxlength="34" autocomplete="off" class="block w-full rounded border"></label>
                    <label>Bank name (required for BANK)<input name="bank" maxlength="100" autocomplete="off" class="block w-full rounded border"></label>
                    <label>Parcel state<select name="parcel_status" required class="block w-full rounded border">@if($workflow->parcel_status!=='SHIPPED')<option value="PENDING">Not yet shipped</option>@endif<option value="SHIPPED">Shipped</option></select></label>
                    <label>Courier (required if shipped)<input name="courier" maxlength="100" value="{{ $workflow->courier }}" class="block w-full rounded border"></label>
                    <label>Tracking number (required if shipped)<input name="tracking_number" maxlength="100" value="{{ $workflow->tracking_number }}" class="block w-full rounded border"></label>
                    <label>Shipping date<input type="date" name="shipped_on" max="{{ now()->toDateString() }}" class="block w-full rounded border"></label>
                    <button class="rounded bg-[#173F35] px-4 py-2 text-white">Submit Refund &amp; Return Details</button>
                </fieldset>
            </form>
        @endif
        @if($role==='seller' && $workflow->parcel_status==='SHIPPED' && in_array($returnRefundRequest->status,\App\Models\ReturnRefundRequest::ACTIVE_STATUSES,true))
            <form method="POST" action="{{ route('seller.return-refunds.receive',$returnRefundRequest) }}" class="space-y-3">@csrf
                <fieldset @disabled(!$ready)><label>Inspection result<select name="inspection_result" required><option value="ACCEPTED">Correct item received</option><option value="MISSING">Missing item</option><option value="INCORRECT">Incorrect item</option><option value="DAMAGED">Further damage</option></select></label><label class="block">Inspection details<textarea class="block w-full rounded border" name="inspection_note" required maxlength="2000"></textarea></label><label><input type="checkbox" required> I have received and inspected the returned parcel.</label><button class="block rounded bg-[#173F35] px-4 py-2 text-white">Confirm Returned Parcel</button></fieldset>
            </form>
        @endif
        @if(in_array($returnRefundRequest->status,\App\Models\ReturnRefundRequest::ACTIVE_STATUSES,true))
            <form method="POST" enctype="multipart/form-data" action="{{ route($role==='buyer'?'return-refunds.evidence.store':$role.'.return-refunds.evidence.store',$returnRefundRequest) }}">@csrf<label>Private waybill / concern / inspection photo (JPG/PNG, up to 5 MiB)<input name="file" type="file" accept="image/jpeg,image/png" required></label><input type="hidden" name="description" value="{{ $role==='seller'?'Seller inspection evidence':'Return shipping/concern evidence' }}"><button class="rounded border px-3 py-2">Upload private evidence</button></form>
        @endif
    @endif
    @if(in_array($role,['buyer','seller'],true))<form method="POST" action="{{ route($role.'.messages.context.seller-order.store',$returnRefundRequest->seller_order_id) }}">@csrf<button class="rounded border px-4 py-2">{{ $role==='buyer'?'Chat with Seller':'Chat with Buyer' }}</button></form>@endif
    @if($returnRefundRequest->status==='ADMIN_APPROVED')<p class="font-semibold">Refund Due — Payment Pending: ₱{{ number_format($returnRefundRequest->refund_minor/100,2) }}</p><p>No verified payment has been recorded. Approval does not mean Refund Paid.</p>@endif
    @if($role==='admin')<p>Refund payment verification is unavailable: a separately reviewed disbursement source, independent transfer verification and reconciliation policy are required.</p><button disabled aria-disabled="true" class="rounded border px-3 py-2">Verify Refund Payment (unavailable)</button>@endif
</section>
