import test from 'node:test';
import assert from 'node:assert/strict';
import { initChatComposer } from '../../resources/js/chat-images.js';

function fixture() {
    const element = () => ({ children: [], handlers: {}, addEventListener(name, handler) { this.handlers[name] = handler; }, append(...nodes) { this.children.push(...nodes); }, replaceChildren() { this.children = []; } });
    const input = element(), previews = element(), error = element(), form = element();
    form.dataset = {};
    form.querySelector = selector => ({ '[data-chat-images]': input, '[data-chat-previews]': previews, '[data-chat-error]': error })[selector];
    globalThis.document = { createElement: element };
    globalThis.window = { addEventListener() {} };
    globalThis.DataTransfer = class { files = []; items = { add: file => this.files.push(file) }; };
    const revoked = [];
    URL.createObjectURL = () => 'blob:preview';
    URL.revokeObjectURL = url => revoked.push(url);
    input.files = [];
    initChatComposer(form);
    return { input, previews, error, form, revoked };
}

test('image preview removal updates submitted files and releases previews', () => {
    const f = fixture();
    f.input.files = [{ type: 'image/png', size: 10 }, { type: 'image/jpeg', size: 20 }];
    f.input.handlers.change();
    assert.equal(f.previews.children.length, 2);
    f.previews.children[0].children[1].handlers.click();
    assert.equal(f.input.files.length, 1);
    assert.equal(f.input.files[0].type, 'image/jpeg');
    assert.equal(f.previews.children.length, 1);
    assert.equal(f.revoked.length, 2);
});

test('invalid formats, count and size prevent form submission', () => {
    const f = fixture();
    for (const files of [[{ type: 'image/svg+xml', size: 10 }], [{ type: 'image/png', size: 5242881 }], Array(6).fill({ type: 'image/png', size: 10 })]) {
        f.input.files = files;
        let prevented = false;
        f.form.handlers.submit({ preventDefault() { prevented = true; } });
        assert.equal(prevented, true);
        assert.match(f.error.textContent, /JPEG/);
    }
});

test('text-only submission works and initialization binds once', () => {
    const f = fixture();
    const handler = f.form.handlers.submit;
    initChatComposer(f.form);
    assert.equal(f.form.handlers.submit, handler);
    handler({ preventDefault() { assert.fail('Text-only submission blocked'); } });
    assert.equal(f.error.textContent, '');
});
