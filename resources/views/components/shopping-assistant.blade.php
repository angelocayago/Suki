<div class="fixed bottom-4 right-4 z-50 font-[Poppins]" data-shopping-assistant data-handoff-template="{{ url('/shopping-assistant/handoff/products/__product__') }}">
    <button type="button" data-assistant-launch class="flex h-12 items-center gap-2 rounded-md border border-[#174f3f] bg-[#174f3f] px-4 text-sm font-semibold text-white shadow-lg focus:outline-none focus:ring-2 focus:ring-[#1f6f5b] focus:ring-offset-2" aria-expanded="false" aria-controls="shoppingAssistantPanel">
        <span aria-hidden="true">S</span><span>Ask SUKI</span>
    </button>
    <section id="shoppingAssistantPanel" data-assistant-panel hidden class="mt-3 w-[min(23rem,calc(100vw-2rem))] overflow-hidden rounded-md border border-[#cbdccf] bg-[#fffdf6] shadow-xl" aria-label="SUKI shopping assistant">
        <header class="flex items-center justify-between border-b border-[#d9e5da] px-4 py-3 text-[#173f35]"><div><strong class="text-sm">SUKI Assistant</strong><p class="text-[11px] text-[#62786b]">Catalog and order support</p></div><button type="button" data-assistant-close aria-label="Close shopping assistant" class="rounded p-1 hover:bg-[#edf4ee]">&times;</button></header>
        <div data-assistant-log class="max-h-64 space-y-3 overflow-y-auto p-4 text-sm" role="log" aria-live="polite"><p class="rounded bg-[#eef5ed] p-3 text-[#234438]">Hi! Ask about products, COD, delivery, returns, or your order.</p></div>
        <div class="flex flex-wrap gap-2 px-4 pb-3" data-assistant-actions>
            @foreach(['Find a product','Track my order','COD and delivery','Return or refund','Talk to support'] as $action)<button type="button" class="rounded border border-[#bfd2c2] px-2 py-1 text-xs text-[#174f3f] hover:bg-[#edf4ee]">{{ $action }}</button>@endforeach
        </div>
        <form data-assistant-form class="flex gap-2 border-t border-[#d9e5da] p-3" action="{{ route('shopping-assistant.message') }}" method="POST">@csrf
            <label class="sr-only" for="shoppingAssistantInput">Ask SUKI</label><input id="shoppingAssistantInput" data-assistant-input maxlength="600" required class="min-w-0 flex-1 rounded border border-[#bcd0c0] bg-white px-3 py-2 text-sm" placeholder="Ask about products or support"><button class="rounded bg-[#174f3f] px-3 py-2 text-sm font-semibold text-white">Send</button>
        </form>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-shopping-assistant]'); if (!root) return;
    const launch = root.querySelector('[data-assistant-launch]'), panel = root.querySelector('[data-assistant-panel]'), close = root.querySelector('[data-assistant-close]'), form = root.querySelector('[data-assistant-form]'), input = root.querySelector('[data-assistant-input]'), log = root.querySelector('[data-assistant-log]'), csrf = form.querySelector('[name=_token]').value;
    let prior = null;
    const open = () => { prior = document.activeElement; panel.hidden = false; launch.setAttribute('aria-expanded', 'true'); input.focus(); };
    const shut = () => { panel.hidden = true; launch.setAttribute('aria-expanded', 'false'); prior?.focus(); };
    const appendLink = (url, label) => { if (!url) return; const link = document.createElement('a'); link.href = url; link.className = 'mt-2 block text-xs font-semibold text-[#174f3f] underline'; link.textContent = label; log.append(link); };
    launch.onclick = open; close.onclick = shut;
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) shut(); });
    root.querySelectorAll('[data-assistant-actions] button').forEach(button => button.onclick = () => { input.value = button.textContent; form.requestSubmit(); });
    form.addEventListener('submit', async event => {
        event.preventDefault(); const text = input.value.trim(); if (!text) return;
        const line = document.createElement('p'); line.className = 'rounded border border-[#d9e5da] bg-white p-2'; line.textContent = text; log.append(line); input.value = '';
        const status = document.createElement('p'); status.textContent = 'Looking that up…'; log.append(status);
        try {
            const response = await fetch(form.action, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify({message: text})});
            const data = await response.json(); status.textContent = data.text || data.message || 'I could not complete that request.';
            (data.products || []).forEach(product => {
                const link = document.createElement('a'); link.href = product.url; link.className = 'block rounded border border-[#bfd2c2] p-2 text-xs text-[#174f3f]'; link.textContent = `${product.name} · ₱${Number(product.price).toFixed(2)}`; log.append(link);
                if (!product.id) return;
                const handoff = document.createElement('button'); handoff.type = 'button'; handoff.className = 'mt-1 text-xs font-semibold text-[#174f3f] underline'; handoff.textContent = 'Talk to this seller';
                handoff.onclick = () => {
                    const confirmation = document.createElement('div'); confirmation.className = 'mt-1 rounded border border-[#bfd2c2] p-2 text-xs'; confirmation.append('Open a human seller conversation for this product? ');
                    const cancel = document.createElement('button'), confirm = document.createElement('button'); cancel.type = confirm.type = 'button'; cancel.textContent = 'Cancel'; confirm.textContent = 'Confirm'; cancel.className = 'ml-2 underline'; confirm.className = 'ml-2 font-semibold underline';
                    cancel.onclick = () => confirmation.remove(); confirm.onclick = async () => { confirm.disabled = true; const handoffResponse = await fetch(root.dataset.handoffTemplate.replace('__product__', product.id), {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify({confirm: true})}); const result = await handoffResponse.json(); if (result.conversation_url) window.location.assign(result.conversation_url); else confirmation.textContent = result.message || 'Unable to open support right now.'; };
                    confirmation.append(cancel, confirm); log.append(confirmation);
                }; log.append(handoff);
            });
            appendLink(data.order_url, 'Open order tracking'); appendLink(data.support_url, 'Open Buyer Help');
        } catch (_) { status.textContent = 'Support is temporarily unavailable. Please try again.'; }
        log.scrollTop = log.scrollHeight;
    });
});
</script>
