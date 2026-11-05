<dialog id="deliveryCompletionDialog" aria-modal="true" aria-labelledby="deliveryCompletionTitle" aria-describedby="deliveryCompletionExplanation" class="m-auto w-[calc(100%_-_2rem)] max-w-lg rounded-2xl border border-[#DCE5E0] p-0 text-[#24312C] shadow-xl backdrop:bg-black/50">
    <div class="p-5 sm:p-6">
        <h2 id="deliveryCompletionTitle" class="text-xl font-bold text-[#173F35]">Confirm Delivery Completion</h2>
        <dl class="mt-5 space-y-3 break-words text-sm">
            <div><dt class="font-semibold">Order</dt><dd id="deliveryCompletionOrder"></dd></div>
            <div><dt class="font-semibold">Buyer</dt><dd id="deliveryCompletionBuyer"></dd></div>
            <div><dt class="font-semibold">Received by</dt><dd id="deliveryCompletionRecipient"></dd></div>
            <div><dt class="font-semibold">Shipment COD due</dt><dd id="deliveryCompletionAmount"></dd></div>
            <div><dt class="font-semibold">COD collection</dt><dd id="deliveryCompletionCod"></dd></div>
            <div><dt class="font-semibold">Delivery proof</dt><dd id="deliveryCompletionProof"></dd></div>
        </dl>
        <p id="deliveryCompletionExplanation" class="mt-5 text-sm text-[#617169]">Confirm only after handing over the parcel and collecting any required COD. Submission saves the delivery proof and updates the shipment's delivery status.</p>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" id="deliveryCompletionCancel" autofocus class="rounded-xl border border-[#1F6F5B] px-4 py-3 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Cancel</button>
            <button type="button" id="deliveryCompletionConfirm" class="rounded-xl bg-[#173F35] px-4 py-3 text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Confirm Delivery</button>
        </div>
    </div>
</dialog>
@include('rider.delivery-completion-script')
