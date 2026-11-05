<script>
(() => {
    document.querySelectorAll('[data-delivery-proof]').forEach(panel => {
        if (panel.dataset.initialized) return;
        panel.dataset.initialized = 'true';
        const input = panel.querySelector('[data-proof-input]'), add = panel.querySelector('[data-proof-add]');
        const selected = panel.querySelector('[data-proof-selected]'), preview = panel.querySelector('[data-proof-preview]');
        const filename = panel.querySelector('[data-proof-filename]'), error = panel.querySelector('[data-proof-error]');
        let objectUrl = null;
        function release() {
            preview.onerror = null;
            preview.removeAttribute('src');
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        function clear(message = '') {
            release(); input.value = ''; selected.hidden = true; add.hidden = false;
            filename.textContent = ''; error.textContent = message; error.hidden = !message;
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
        }
        function show() {
            const file = input.files[0];
            if (!file) { clear(); return; }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || !/\.(jpe?g|png|webp)$/i.test(file.name) || file.size === 0) {
                clear('Choose a valid JPG, JPEG, PNG or WebP photo. Other file types are not accepted.'); return;
            }
            if (file.size > 5 * 1024 * 1024) {
                clear('This photo is too large. Choose an image no larger than 5 MB.'); return;
            }
            release(); error.hidden = true; error.textContent = ''; input.setAttribute('aria-invalid', 'false');
            objectUrl = URL.createObjectURL(file);
            const currentUrl = objectUrl;
            preview.onerror = () => {
                if (objectUrl === currentUrl) clear('This file could not be opened as a photo. Choose a valid JPG, JPEG, PNG or WebP image.');
            };
            preview.src = objectUrl; filename.textContent = file.name;
            selected.hidden = false; add.hidden = true;
        }
        add.addEventListener('click', () => input.click());
        panel.querySelector('[data-proof-replace]').addEventListener('click', () => input.click());
        panel.querySelector('[data-proof-remove]').addEventListener('click', () => { clear(); add.focus(); });
        input.addEventListener('change', show);
        input.addEventListener('invalid', event => {
            event.preventDefault();
            error.textContent = 'Add a delivery photo before marking this parcel as delivered.';
            error.hidden = false; input.setAttribute('aria-invalid', 'true'); add.focus();
        });
        window.addEventListener('pagehide', release);
        window.addEventListener('pageshow', () => { if (input.files.length && !objectUrl) show(); });
        if (input.files.length) show();
    });
})();
</script>
