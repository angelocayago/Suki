import test from 'node:test';
import assert from 'node:assert/strict';
import { initChatHistory } from '../../resources/js/chat-workspace.js';
import { initChatComposer } from '../../resources/js/chat-images.js';

test('only the latest history page starts at the bottom and initialization preserves later scrolling', () => {
    const latest = { dataset: { latestPage: 'true' }, scrollTop: 0, scrollHeight: 500 };
    initChatHistory(latest);
    assert.equal(latest.scrollTop, 500);
    latest.scrollTop = 30; initChatHistory(latest);
    assert.equal(latest.scrollTop, 30);
    const older = { dataset: { latestPage: 'false' }, scrollTop: 0, scrollHeight: 500 };
    initChatHistory(older);
    assert.equal(older.scrollTop, 0);
});

function composer() {
    const node = () => ({ handlers: {}, children: [], addEventListener(name, handler) { this.handlers[name] = handler; }, replaceChildren() { this.children = []; } });
    const input = node(), body = node(), counter = node(), button = node(), status = node(), error = node(), previews = node(), form = node();
    body.value = 'Hi🌱'; input.files = []; button.disabled = false; status.textContent = '';
    form.dataset = {};
    const nodes = { '[data-chat-images]': input, '[data-chat-previews]': previews, '[data-chat-error]': error, '[data-chat-body]': body, '[data-chat-counter]': counter, '[data-chat-submit]': button, '[data-chat-send-status]': status };
    form.querySelector = selector => nodes[selector];
    const lifecycle = {};
    globalThis.window = { addEventListener(name, handler) { lifecycle[name] = handler; } };
    initChatComposer(form);
    return { form, input, body, counter, button, status, lifecycle };
}

test('composer counts Unicode characters and blocks duplicate native submits until history restoration', () => {
    const f = composer();
    assert.equal(f.counter.textContent, '3/5000');
    f.body.value = 'Reply'; f.body.handlers.input();
    assert.equal(f.counter.textContent, '5/5000');
    f.form.handlers.submit({ preventDefault() { assert.fail('First submit prevented'); } });
    assert.equal(f.button.disabled, true);
    assert.equal(f.status.textContent, 'Sending…');
    let prevented = false;
    f.form.handlers.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    f.lifecycle.pageshow();
    assert.equal(f.button.disabled, false);
    assert.equal(f.status.textContent, '');
});

test('invalid images do not lock the composer or report a successful submission', () => {
    const f = composer(); f.input.files = [{ type: 'image/svg+xml', size: 1 }];
    let prevented = false;
    f.form.handlers.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(f.button.disabled, false);
    assert.equal(f.status.textContent, '');
});
