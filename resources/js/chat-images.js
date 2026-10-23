export function initChatComposer(form) {
    if (!form || form.dataset.chatReady) return;
    const input = form.querySelector('[data-chat-images]');
    const previews = form.querySelector('[data-chat-previews]');
    const error = form.querySelector('[data-chat-error]');
    if (!input || !previews || !error) return;
    form.dataset.chatReady = 'true';
    const body = form.querySelector('[data-chat-body]');
    const counter = form.querySelector('[data-chat-counter]');
    const sendButton = form.querySelector('[data-chat-submit]');
    const sendStatus = form.querySelector('[data-chat-send-status]');
    let submitted = false;
    const count = () => { if (body && counter) counter.textContent = `${Array.from(body.value || '').length}/5000`; };
    body?.addEventListener('input', count); count();
    let urls = [];
    const render = () => {
        urls.forEach(url => URL.revokeObjectURL(url)); urls = [];
        previews.replaceChildren(); error.textContent = '';
        const files = Array.from(input.files || []);
        if (files.length > 5 || files.some(file => !['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
            error.textContent = 'Choose up to 5 JPEG, PNG or WebP images, at most 5 MB each.';
            return false;
        }
        files.forEach((file, index) => {
            const card = document.createElement('div');
            const img = document.createElement('img');
            const remove = document.createElement('button');
            const url = URL.createObjectURL(file); urls.push(url);
            img.src = url; img.alt = `Selected image ${index + 1}`; img.className = 'h-24 w-24 rounded-xl object-cover';
            remove.type = 'button'; remove.textContent = `Remove image ${index + 1}`; remove.className = 'block text-xs text-red-700';
            remove.addEventListener('click', () => {
                const transfer = new DataTransfer();
                files.filter((_, i) => i !== index).forEach(remaining => transfer.items.add(remaining));
                input.files = transfer.files; render();
            });
            card.append(img, remove); previews.append(card);
        });
        return true;
    };
    input.addEventListener('change', render);
    form.addEventListener('submit', event => {
        if (submitted || !render()) { event.preventDefault(); return; }
        if (event.defaultPrevented) return;
        submitted = true;
        if (sendButton) sendButton.disabled = true;
        if (sendStatus) sendStatus.textContent = 'Sending…';
    });
    window.addEventListener('pageshow', () => {
        submitted = false;
        if (sendButton) sendButton.disabled = false;
        if (sendStatus) sendStatus.textContent = '';
    });
    window.addEventListener('pagehide', () => { urls.forEach(url => URL.revokeObjectURL(url)); urls = []; });
}
if (typeof document !== 'undefined') {
    const init = () => document.querySelectorAll('[data-chat-composer]').forEach(initChatComposer);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}
