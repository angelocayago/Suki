<style>
    #sellerLogoutDialog{position:fixed;inset:0;margin:auto;width:calc(100% - 32px);max-width:420px;max-height:calc(100dvh - 32px);overflow:auto;padding:24px;border:1px solid #dde6e1;border-radius:16px;background:#fff;color:#173f35;box-shadow:0 20px 60px #102c2533;font-family:inherit}
    #sellerLogoutDialog::backdrop{background:#102c2580;backdrop-filter:blur(3px)}
    #sellerLogoutDialog h2{margin:0;font-size:16px;font-weight:600;line-height:1.5}
    #sellerLogoutDialog p{margin:6px 0 22px;color:#677a70;font-size:12px;line-height:1.7}
    #sellerLogoutDialog .seller-logout-actions{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:10px}
    #sellerLogoutDialog button{min-height:40px;padding:9px 17px;border:1px solid #a8bdb2;border-radius:10px;background:#fff;color:#173f35;font:inherit;font-size:12px;font-weight:600;cursor:pointer}
    #sellerLogoutDialog button[data-seller-logout-confirm]{border-color:#173f35;background:#173f35;color:#fff}
    #sellerLogoutDialog button:hover{background:#edf4f0}
    #sellerLogoutDialog button[data-seller-logout-confirm]:hover{background:#1f6f5b}
    #sellerLogoutDialog button:focus-visible{outline:3px solid #1f6f5b;outline-offset:3px}
    #sellerLogoutDialog button:disabled{opacity:.65;cursor:wait}
</style>

<dialog id="sellerLogoutDialog" aria-labelledby="sellerLogoutTitle" aria-describedby="sellerLogoutDescription">
    <h2 id="sellerLogoutTitle">Are you sure you want to log out?</h2>
    <p id="sellerLogoutDescription">You will be signed out of your seller account.</p>
    <div class="seller-logout-actions">
        <button type="button" data-seller-logout-cancel autofocus>No</button>
        <button type="button" data-seller-logout-confirm>Log Out</button>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('sellerLogoutDialog');
    const cancel = dialog.querySelector('[data-seller-logout-cancel]');
    const confirm = dialog.querySelector('[data-seller-logout-confirm]');
    let pendingForm = null, trigger = null, submitting = false;

    function dismiss() {
        if (submitting) return;
        dialog.close();
        pendingForm = null;
        trigger?.focus();
    }

    document.querySelectorAll('form[data-seller-logout]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (dialog.open || submitting) return;
            pendingForm = form;
            trigger = event.submitter || form.querySelector('button[type="submit"]');
            dialog.showModal();
            cancel.focus();
        });
    });
    cancel.addEventListener('click', dismiss);
    dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(); });
    dialog.addEventListener('click', event => {
        // Keep the header menu available so cancellation can restore its Sign Out trigger.
        event.stopPropagation();
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dismiss();
    });
    dialog.addEventListener('keydown', event => {
        // The native modal makes the page inert; don't also activate mobile-sidebar keys.
        event.stopPropagation();
        if (event.key === 'Tab') {
            if (event.shiftKey && document.activeElement === cancel) { event.preventDefault(); confirm.focus(); }
            else if (!event.shiftKey && document.activeElement === confirm) { event.preventDefault(); cancel.focus(); }
        }
    });
    confirm.addEventListener('click', () => {
        if (!pendingForm || submitting) return;
        submitting = true;
        cancel.disabled = true;
        confirm.disabled = true;
        // Submit the original POST form and CSRF token once, without firing confirmation again.
        HTMLFormElement.prototype.submit.call(pendingForm);
    });
});
</script>
