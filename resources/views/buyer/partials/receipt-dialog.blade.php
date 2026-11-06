@if(collect($orders ?? [$order ?? []])->contains(fn ($purchase) => ($purchase['status'] ?? '') === 'delivered'))
<dialog id="buyerReceiptDialog" class="br-dialog" aria-labelledby="buyerReceiptTitle" aria-describedby="buyerReceiptMessage">
    <div class="br-icon" aria-hidden="true">✓</div>
    <h2 id="buyerReceiptTitle">Confirm Order Received</h2>
    <p id="buyerReceiptMessage">Have you received your order? Confirm to complete your purchase.</p>
    <p class="br-feedback" role="status" aria-live="polite" data-receipt-feedback></p>
    <footer>
        <button type="button" class="br-cancel" data-receipt-cancel>Cancel</button>
        <button type="button" class="br-confirm" data-receipt-confirm>Confirm Received</button>
    </footer>
</dialog>
<style>
.br-dialog{width:min(94vw,440px);max-height:90dvh;margin:auto;padding:28px;border:1px solid #dde6e1;border-radius:20px;background:#fff;color:#233e31;box-shadow:0 25px 70px #12332940;overflow:auto}.br-dialog::backdrop{background:#102a22a6}.br-icon{display:grid;place-items:center;width:44px;height:44px;margin-bottom:16px;border-radius:14px;background:#ddf3ec;color:#1f6f5b;font-size:24px;font-weight:700}.br-dialog h2{font-size:21px;line-height:1.3;font-weight:700;margin:0 0 12px}.br-dialog p{font-size:14px;line-height:1.6;color:#61756a;margin:0}.br-dialog .br-feedback{font-size:12px;margin-top:12px}.br-feedback:empty{display:none}.br-dialog footer{display:flex;gap:10px;margin-top:24px}.br-dialog button{flex:1;min-height:44px;padding:12px 10px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer}.br-cancel{border:1px solid #ceddd3;background:#fff;color:#244b3a}.br-confirm{border:1px solid #1f6f5b;background:#1f6f5b;color:#fff}.br-confirm:hover{background:#155244}.br-dialog button:focus-visible{outline:3px solid #72a991;outline-offset:3px}.br-dialog button:disabled{opacity:.6;cursor:wait}@media(max-width:420px){.br-dialog{padding:22px}.br-dialog footer{flex-direction:column-reverse}}html[data-theme=dark] .br-dialog{background:#192b23;color:#e2eee7;border-color:#405b4b}html[data-theme=dark] .br-dialog p{color:#b4c9bc}html[data-theme=dark] .br-cancel{background:#20392c;color:#d4eddd;border-color:#648271}
</style>
@include('buyer.partials.receipt-script')
@endif
