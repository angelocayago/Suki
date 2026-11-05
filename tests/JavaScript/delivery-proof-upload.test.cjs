const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/rider/delivery-proof-upload-script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const element = () => ({handlers: {}, attributes: {}, textContent: '', hidden: false, focused: false,
        addEventListener(type, fn) {(this.handlers[type] ??= []).push(fn);},
        setAttribute(key, value) {this.attributes[key] = value;},
        removeAttribute(key) {delete this[key];},
        focus() {this.focused = true;}, click() {this.clicks = (this.clicks || 0) + 1;}});
    const panels = [0, 1].map(() => {
        const fields = Object.fromEntries(['input', 'add', 'selected', 'preview', 'filename', 'error', 'replace', 'remove'].map(key => [key, element()]));
        fields.input.files = [];
        Object.defineProperty(fields.input, 'value', {set(value) {if (value === '') this.files = [];}, get() {return this.files.length ? 'selected' : '';}});
        return {dataset: {}, fields, querySelector(selector) {return fields[selector.match(/data-proof-(.+)\]/)[1]];}};
    });
    const created = [], revoked = [], window = element();
    const context = {document: {querySelectorAll: () => panels}, window,
        URL: {createObjectURL(file) {const url = 'blob:photo-' + created.length; created.push({file, url}); return url;}, revokeObjectURL(url) {revoked.push(url);}}};
    function fire(element, type) {
        const event = {preventDefault() {this.prevented = true;}};
        for (const fn of element.handlers[type] ?? []) fn(event);
        return event;
    }
    const run = () => vm.runInNewContext(source, context); run();
    const choose = (index, file) => {panels[index].fields.input.files = file ? [file] : []; fire(panels[index].fields.input, 'change');};
    return {panels, created, revoked, window, run, fire, choose};
}
const photo = (name = 'proof.jpg', size = 1024, type = 'image/jpeg') => ({name, size, type});

test('Add opens native picker; selection shows local preview and filename without upload', () => {
    const h = harness(), f = h.panels[0].fields;
    h.fire(f.add, 'click'); assert.equal(f.input.clicks, 1);
    const file = photo(); h.choose(0, file);
    assert.equal(f.preview.src, 'blob:photo-0'); assert.equal(f.filename.textContent, 'proof.jpg');
    assert.equal(f.selected.hidden, false); assert.equal(f.add.hidden, true);
    assert.equal(f.input.files[0], file); assert.equal(f.error.hidden, true);
});

test('Replace retains the old selection until a new file is chosen, then revokes its URL', () => {
    const h = harness(), f = h.panels[0].fields; h.choose(0, photo());
    h.fire(f.replace, 'click'); assert.equal(f.input.clicks, 1); assert.equal(f.input.files[0].name, 'proof.jpg');
    h.choose(0, photo('replacement.png', 1024, 'image/png'));
    assert.equal(f.filename.textContent, 'replacement.png'); assert.equal(f.preview.src, 'blob:photo-1');
    assert.deepEqual(h.revoked, ['blob:photo-0']); assert.equal(f.input.files[0].name, 'replacement.png');
});

test('Remove clears actual files and preview, restores Add focus and permits reselection', () => {
    const h = harness(), f = h.panels[0].fields; h.choose(0, photo()); h.fire(f.remove, 'click');
    assert.equal(f.input.files.length, 0); assert.equal(f.preview.src, undefined);
    assert.equal(f.filename.textContent, ''); assert.equal(f.selected.hidden, true);
    assert.equal(f.add.hidden, false); assert.equal(f.add.focused, true);
    assert.deepEqual(h.revoked, ['blob:photo-0']); h.choose(0, photo()); assert.equal(f.selected.hidden, false);
});

test('delivery cards keep independent input, preview and removal state', () => {
    const h = harness(); h.choose(0, photo('one.jpg')); h.choose(1, photo('two.jpg'));
    h.fire(h.panels[0].fields.remove, 'click');
    assert.equal(h.panels[1].fields.input.files[0].name, 'two.jpg');
    assert.equal(h.panels[1].fields.preview.src, 'blob:photo-1');
    assert.deepEqual(h.revoked, ['blob:photo-0']);
});

test('disallowed MIME, disguised executable extension, empty files and oversized photos are cleared inline', () => {
    for (const file of [photo('bad.svg', 100, 'image/svg+xml'), photo('bad.php', 100, 'image/jpeg'), photo('bad.jpg', 100, 'text/html'), photo('empty.jpg', 0), photo('large.jpg', 5 * 1024 * 1024 + 1)]) {
        const h = harness(), f = h.panels[0].fields; h.choose(0, file);
        assert.equal(f.input.files.length, 0); assert.equal(f.error.hidden, false);
        assert.match(f.error.textContent, /too large|valid JPG/); assert.equal(f.input.attributes['aria-invalid'], 'true');
        assert.equal(h.created.length, 0); h.choose(0, photo()); assert.equal(f.error.hidden, true);
    }
});

test('exact size boundary and supported JPEG PNG and WebP formats are accepted', () => {
    for (const [name, type] of [['a.JPEG', 'image/jpeg'], ['a.png', 'image/png'], ['a.webp', 'image/webp']]) {
        const h = harness(); h.choose(0, photo(name, 5 * 1024 * 1024, type));
        assert.equal(h.panels[0].fields.selected.hidden, false);
    }
});

test('unreadable image bytes are rejected when preview decoding fails', () => {
    const h = harness(), f = h.panels[0].fields; h.choose(0, photo()); f.preview.onerror();
    assert.equal(f.input.files.length, 0); assert.match(f.error.textContent, /could not be opened/);
    assert.deepEqual(h.revoked, ['blob:photo-0']);
});

test('required input reports an accessible inline error instead of native missing-file tooltip', () => {
    const h = harness(), f = h.panels[0].fields;
    assert.equal(h.fire(f.input, 'invalid').prevented, true);
    assert.match(f.error.textContent, /Add a delivery photo/); assert.equal(f.error.hidden, false);
    assert.equal(f.add.focused, true); assert.equal(f.input.attributes['aria-invalid'], 'true');
});

test('initialization is idempotent and object URLs are cleaned on page exit and restored on return', () => {
    const h = harness(), f = h.panels[0].fields; h.run();
    assert.equal(f.input.handlers.change.length, 1); h.choose(0, photo());
    h.fire(h.window, 'pagehide'); assert.deepEqual(h.revoked, ['blob:photo-0']);
    h.fire(h.window, 'pageshow'); assert.equal(f.preview.src, 'blob:photo-1');
});
