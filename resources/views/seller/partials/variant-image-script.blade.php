<script>
globalThis.SukiVariantImages = (() => {
    function build(name, saved = '', removeName = '', removed = false) {
        const element = document.createElement('div');
        const label = document.createElement('label'); label.textContent = 'Variant Image (optional)';
        const input = document.createElement('input'); input.type = 'file'; input.name = name;
        input.accept = 'image/jpeg,image/png,image/webp';
        const image = document.createElement('img'); image.alt = 'Variant image preview';
        image.style = 'max-width:120px;max-height:120px;object-fit:contain';
        const status = document.createElement('p'); status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
        const clear = document.createElement('button'); clear.type = 'button'; clear.textContent = 'Remove selected image';
        const removalLabel = document.createElement('label');
        const removal = document.createElement('input'); removal.type = 'checkbox'; removal.name = removeName; removal.value = '1'; removal.checked = removed;
        const caption = document.createElement('span'); caption.textContent = 'Remove saved variant image';
        removalLabel.append(removal, caption); removalLabel.hidden = !saved;
        let url = null;
        function release() { if (url) URL.revokeObjectURL(url); url = null; }
        function refresh() {
            const source = url || (removal.checked ? '' : saved);
            image.hidden = !source; if (source) image.src = source; clear.hidden = !url;
        }
        input.addEventListener('change', () => {
            release();
            const file = input.files?.[0];
            const valid = !file || (['image/jpeg','image/png','image/webp'].includes(file.type) && file.size <= 4*1024*1024);
            status.textContent = valid ? '' : 'Choose a JPG, PNG or WebP image up to 4 MB.';
            input.setCustomValidity(valid ? '' : status.textContent);
            if (valid && file) { url = URL.createObjectURL(file); removal.checked = false; }
            refresh();
        });
        clear.addEventListener('click', () => { input.value = ''; release(); input.setCustomValidity(''); status.textContent = ''; refresh(); });
        removal.addEventListener('change', () => { if (removal.checked) { input.value = ''; release(); input.setCustomValidity(''); status.textContent = ''; } refresh(); });
        image.addEventListener('error', () => { image.hidden = true; });
        label.append(input); element.append(label, image, clear, removalLabel, status); refresh();
        return {element, dispose: release, setNames(next, nextRemove) { input.name = next; removal.name = nextRemove; }};
    }
    function mount(root) {
        root.querySelectorAll('[data-variant-image-editor]').forEach(host => {
            if (host.variantImageControl) return;
            const control = build(host.dataset.imageName, host.dataset.savedImage || '', host.dataset.removeName, host.dataset.removed === '1');
            host.variantImageControl = control;
            host.append(control.element);
            window.addEventListener('pagehide', control.dispose);
        });
    }
    return {build, mount};
})();
document.addEventListener('DOMContentLoaded', () => SukiVariantImages.mount(document));
</script>
