// Add Product owns one picker and one ordered set of files; Edit keeps its existing controls.
export function initProductCreate(form, doc = document) {
    if (!form || form.dataset.initialized === '1') return;
    form.dataset.initialized = '1';
    const picker = doc.getElementById('imagePicker');
    const upload = doc.getElementById('images');
    const button = doc.getElementById('addProductImages');
    const grid = doc.getElementById('imagePreview');
    const status = doc.getElementById('imageUploadStatus');
    let files = [];
    let urls = [];
    let submitting = false;
    function render() {
        urls.forEach(url => URL.revokeObjectURL(url)); urls = [];
        grid.replaceChildren();
        const transfer = new DataTransfer();
        files.forEach((file, index) => {
            transfer.items.add(file);
            const card = doc.createElement('div');
            card.className = 'relative aspect-square overflow-hidden rounded-xl border border-[#DDE6E1] bg-[#F5F8F6]';
            const image = doc.createElement('img');
            image.src = URL.createObjectURL(file); urls.push(image.src);
            image.alt = `Selected product image ${index + 1}`;
            image.className = 'h-full w-full object-cover';
            card.append(image);
            if (index === 0) {
                const cover = doc.createElement('span');
                cover.textContent = 'Main Image';
                cover.className = 'absolute left-1 top-1 rounded bg-[#173F35] px-2 py-1 text-[10px] font-semibold text-white';
                card.append(cover);
            }
            const remove = doc.createElement('button');
            remove.type = 'button'; remove.textContent = 'Remove';
            remove.setAttribute('aria-label', `Remove selected image ${index + 1}`);
            remove.className = 'absolute bottom-1 right-1 rounded-lg bg-white px-2 py-1 text-xs font-semibold text-red-600 shadow-sm';
            remove.addEventListener('click', () => { files.splice(index, 1); status.textContent = ''; render(); });
            card.append(remove); grid.append(card);
        });
        upload.files = transfer.files;
        button.disabled = files.length >= 5;
    }
    button.addEventListener('click', () => picker.click());
    picker.addEventListener('change', () => {
        const chosen = Array.from(picker.files || []);
        if (chosen.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
            status.textContent = 'Choose JPG, PNG or WebP images up to 5 MB each.';
        } else {
            const slots = 5 - files.length;
            files = files.concat(chosen.slice(0, slots));
            status.textContent = chosen.length > slots ? 'You can upload up to 5 images.' : '';
            render();
        }
        picker.value = '';
    });
    form.addEventListener('submit', event => {
        // The variant editor's capture handler runs first and blocks dirty combinations.
        if (event.defaultPrevented) return;
        if (submitting) { event.preventDefault(); return; }
        if (event.submitter?.value !== 'draft' && !files.length) {
            event.preventDefault();
            status.textContent = 'Add at least one product photo before publishing.';
            button.focus();
            return;
        }
        submitting = true;
    });
    render();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => initProductCreate(document.querySelector('[data-product-create]')));
}
