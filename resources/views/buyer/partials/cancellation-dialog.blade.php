@php $cancellationErrors = $errors->getBag('cancellation'); @endphp
@if($cancellationErrors->any())
    <div class="bc-error-summary" role="alert" aria-live="assertive" tabindex="-1" data-cancel-errors>
        <strong>Cancellation could not be completed.</strong>
        <ul>@foreach($cancellationErrors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif
<dialog id="buyerCancellationDialog" class="bc-dialog" aria-labelledby="buyerCancellationTitle" aria-describedby="buyerCancellationEffect" data-validation-message="{{ implode(' ', $cancellationErrors->all()) }}" data-error-reference="{{ $cancellationErrors->any() ? old('cancel_order_reference') : '' }}">
    <form method="POST" data-cancellation-form>
        @csrf
        <input type="hidden" name="cancel_order_reference" value="">
        <header class="bc-heading"><span class="bc-warning" aria-hidden="true">!</span><div><h2 id="buyerCancellationTitle">Cancel Order</h2><p>Order <strong data-cancellation-reference></strong></p></div></header>
        <p id="buyerCancellationEffect">This cancels every item and seller parcel in this order. This action cannot be undone. Choose your reason to continue.</p>
        <fieldset><legend>Why are you cancelling? <span>(required)</span></legend>
            @foreach(\App\Services\OrderCancellationService::REASONS as $value=>$label)
                <label class="bc-reason"><input type="radio" name="cancel_reason" value="{{ $value }}" required @checked(old('cancel_reason')===$value)><span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
        <p class="bc-feedback" role="status" aria-live="polite" data-cancellation-feedback></p>
        <footer><button type="button" class="bc-keep" data-cancellation-close>Keep Order</button><button type="submit" class="bc-confirm">Confirm Cancellation</button></footer>
    </form>
</dialog>
<style>
.bc-dialog{width:min(94vw,460px);max-height:90dvh;margin:auto;padding:24px;border:1px solid #dde6e1;border-radius:20px;background:#fff;color:#233e31;box-shadow:0 25px 70px #12332940;overflow:auto}.bc-dialog::backdrop{background:#102a22a6}.bc-heading{display:flex;gap:12px;align-items:center;margin-bottom:15px}.bc-heading h2{font-size:20px;font-weight:700;margin:0}.bc-heading p{font-size:12px;margin:4px 0 0;overflow-wrap:anywhere}.bc-warning{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:#fff0ed;color:#b42318;font-size:22px;font-weight:700;flex:none}#buyerCancellationEffect{font-size:13px;line-height:1.65;margin:0 0 20px}.bc-dialog fieldset{padding:0;border:0;margin:0}.bc-dialog legend{font-size:13px;font-weight:600;margin-bottom:10px}.bc-dialog legend span{font-weight:400;color:#697b71}.bc-reason{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #dde6e1;border-radius:10px;margin-top:8px;cursor:pointer;font-size:13px}.bc-reason:has(input:checked){border-color:#1f6f5b;background:#f0f8f4}.bc-reason input{accent-color:#1f6f5b;width:17px;height:17px;flex:none}.bc-dialog footer{display:flex;gap:10px;margin-top:18px}.bc-dialog footer button{flex:1;padding:12px 10px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;min-height:44px}.bc-keep{border:1px solid #ceddd3;background:#fff;color:#244b3a}.bc-confirm{border:1px solid #b42318;background:#b42318;color:#fff}.bc-dialog :is(button,input):focus-visible{outline:3px solid #72a991;outline-offset:3px}.bc-dialog button:disabled{opacity:.6;cursor:wait}.bc-feedback{font-size:12px;color:#697b71;margin-top:12px;min-height:18px}.bc-error-summary{margin:20px auto;padding:16px;max-width:64rem;border:1px solid #f0b4aa;border-radius:12px;background:#fff1ed;color:#982318;font-size:13px}.bc-error-summary ul{list-style:disc;padding-left:20px;margin-top:6px}html[data-theme=dark] .bc-dialog{background:#192b23;color:#e2eee7;border-color:#405b4b}html[data-theme=dark] .bc-reason{border-color:#405b4b}html[data-theme=dark] .bc-reason:has(input:checked){background:#244b3a;border-color:#8dd2af}html[data-theme=dark] .bc-keep{background:#20392c;color:#d4eddd;border-color:#648271}html[data-theme=dark] .bc-dialog :is(legend span,.bc-feedback){color:#b4c9bc}html[data-theme=dark] .bc-error-summary{background:#42271f;color:#ffd9d1;border-color:#966555}@media(max-width:420px){.bc-dialog{padding:18px}.bc-dialog footer{flex-direction:column-reverse}}
</style>
@include('buyer.partials.cancellation-script')
