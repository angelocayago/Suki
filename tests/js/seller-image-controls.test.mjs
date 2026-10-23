import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';

// Minimal DOM doubles run the production event handlers, without a browser dependency.
class Element {
    constructor() {
        this.events = {};
        this.attrs = {};
        this.children = [];
        this.files = [];
        this.disabled = false;
        this.classes = new Set();
        this.classList = {
            add: x => this.classes.add(x), remove: x => this.classes.delete(x),
            contains: x => this.classes.has(x),
            toggle: (x, force) => {
                const add = force ?? !this.classes.has(x);
                add ? this.classes.add(x) : this.classes.delete(x);
                return add;
            },
        };
    }
    set value(value) { if (value === '') this.files = []; }
    addEventListener(type, fn) { (this.events[type] ??= []).push(fn); }
    emit(type, event = {}) { this.events[type]?.forEach(fn => fn(event)); }
    click() { this.clicked = true; this.emit('click'); }
    focus() { this.focused = true; }
    setAttribute(key, value) { this.attrs[key] = value; }
    getAttribute(key) { return this.attrs[key] ?? null; }
    removeAttribute(key) { delete this.attrs[key]; }
    querySelector(key) { return this.nodes[key]; }
    querySelectorAll() { return []; }
    append(...children) { this.children.push(...children); }
    replaceChildren() { this.children = []; }
    contains() { return false; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
}
function environment() {
    const elements = {};
    const doc = new Element();
    doc.getElementById = id => elements[id] ??= new Element();
    doc.createElement = () => new Element();
    doc.body = new Element();
    doc.documentElement = new Element();
    const win = new Element();
    win.innerWidth = 1400;
    let nextUrl = 0;
    const context = { document: doc, window: win, URL: {
        createObjectURL: () => `blob:test-${++nextUrl}`, revokeObjectURL() {},
    }, DataTransfer: class {
        constructor() { this.files = []; this.items = { add: file => this.files.push(file) }; }
    }};
    return { context, doc, elements, run(source) {
        vm.runInNewContext(source, context);
        doc.emit('DOMContentLoaded');
    }};
}
function imageSetup() {
    const env = environment();
    const rows = [1, 2].map(id => {
        const row = new Element();
        row.nodes = Object.fromEntries(['replacement', 'removal', 'preview', 'placeholder', 'replace', 'remove', 'cancel-replacement', 'image-status']
            .map(name => [`[data-${name}]`, new Element()]));
        row.nodes['[data-removal]'].disabled = true;
        row.nodes['[data-preview]'].attrs.src = `https://active.example/storage/products/${id}.png`;
        return row;
    });
    env.doc.getElementById('productImageControls').querySelectorAll = () => rows;
    const view = readFileSync('resources/views/seller/partials/product-image-controls.blade.php', 'utf8');
    env.run(view.match(/<script>([\s\S]*?)<\/script>/)[1]);
    return { ...env, rows };
}
const file = { type: 'image/png', size: 1024 };

test('Replace targets one picker, previews its file and supports cancellation', () => {
    const { rows } = imageSetup();
    const row = rows[0].nodes;
    assert.equal(row['[data-replacement]'].disabled, true);
    row['[data-replace]'].click();
    assert.equal(row['[data-replacement]'].clicked, true);
    assert.equal(rows[1].nodes['[data-replacement]'].clicked, undefined);
    row['[data-replacement]'].emit('cancel');
    assert.equal(row['[data-replacement]'].disabled, true);
    row['[data-replace]'].click();
    row['[data-replacement]'].files = [file];
    row['[data-replacement]'].emit('change');
    assert.match(row['[data-preview]'].src, /^blob:/);
    assert.equal(row['[data-image-status]'].textContent, 'Will be replaced when you save.');
    row['[data-cancel-replacement]'].click();
    assert.equal(row['[data-replacement]'].files.length, 0);
    assert.equal(row['[data-preview]'].src, 'https://active.example/storage/products/1.png');
});

test('Invalid replacement files leave the saved preview and disable the empty upload', () => {
    const { rows } = imageSetup();
    const row = rows[0].nodes;
    for (const invalid of [{ type: 'text/plain', size: 1 }, { type: 'image/png', size: 6 * 1024 * 1024 }]) {
        row['[data-replace]'].click();
        row['[data-replacement]'].files = [invalid];
        row['[data-replacement]'].emit('change');
        assert.equal(row['[data-replacement]'].files.length, 0);
        assert.equal(row['[data-replacement]'].disabled, true);
        assert.match(row['[data-image-status]'].textContent, /up to 5 MB/);
        assert.equal(row['[data-preview]'].src, 'https://active.example/storage/products/1.png');
    }
});

test('Remove marks only its row, disables its upload and can be undone', () => {
    const { rows } = imageSetup();
    const row = rows[0].nodes;
    row['[data-remove]'].click();
    assert.equal(row['[data-removal]'].disabled, false);
    assert.equal(row['[data-replacement]'].disabled, true);
    assert.equal(rows[1].nodes['[data-removal]'].disabled, true);
    assert.equal(row['[data-remove]'].textContent, 'Undo removal');
    row['[data-remove]'].click();
    assert.equal(row['[data-removal]'].disabled, true);
    assert.equal(row['[data-replacement]'].disabled, true);
});

test('Adding images enforces count and type, supports removal and blocks unsafe undo', () => {
    const { elements, rows } = imageSetup();
    const input = elements.editAddImages;
    input.files = [file, file, file, file];
    input.emit('change');
    assert.equal(input.files.length, 0);
    rows[0].nodes['[data-remove]'].click();
    input.files = [file, file, file, file];
    input.emit('change');
    assert.equal(input.files.length, 4);
    rows[0].nodes['[data-remove]'].click();
    assert.equal(rows[0].nodes['[data-removal]'].disabled, false);
    elements.editAddedPreviews.children[0].children[1].click();
    assert.equal(input.files.length, 3);
    rows[0].nodes['[data-remove]'].click();
    assert.equal(rows[0].nodes['[data-removal]'].disabled, true);
    input.files = [{ type: 'text/plain', size: 1 }];
    input.emit('change');
    assert.equal(input.files.length, 3);
    assert.match(elements.editImageStatus.textContent, /JPG, PNG or WebP/);
});

test('Both logout triggers open one dialog; No closes and confirmation allows the existing form', () => {
    const env = environment();
    const view = readFileSync('resources/views/layouts/seller.blade.php', 'utf8');
    const script = [...view.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)].map(match => match[1])
        .find(source => source.includes('const logoutForm'));
    env.run(script);
    const el = env.elements;
    el.sellerLogoutTrigger.click();
    assert.equal(el.sellerLogoutDialog.open, true);
    el.sellerLogoutCancel.click();
    assert.equal(el.sellerLogoutDialog.open, false);
    assert.equal(el.sellerLogoutTrigger.focused, true);
    assert.equal(el.sellerLogoutConfirm.disabled, false);
    el.sellerAccountLogout.click();
    assert.equal(el.sellerLogoutDialog.open, true);
    el.sellerLogoutCancel.click();
    assert.equal(el.sellerAccountToggle.focused, true);
    let prevented = false;
    el.sellerLogoutForm.emit('submit', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    prevented = false;
    el.sellerLogoutForm.emit('submit', { submitter: el.sellerLogoutConfirm, preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
    assert.equal(el.sellerLogoutConfirm.disabled, true);
});

test('Add Product previews selected photos, removes them, keeps cover order and enforces existing limits', () => {
    const env = environment();
    const nodes = Object.fromEntries(['imagePicker', 'images', 'imagePreview', 'imageUploadStatus', 'addProductImages'].map(id => [id, new Element()]));
    const photoElement = () => {
        const el = new Element();
        el.appendChild = child => el.append(child);
        Object.defineProperty(el, 'innerHTML', { set() { el.children = []; } });
        return el;
    };
    nodes.imagePreview = photoElement();
    env.doc.getElementById = id => nodes[id] ?? null;
    env.doc.createElement = photoElement;
    const form = new Element(); form.dataset = {};
    env.doc.querySelector = () => form;
    const script = readFileSync(new URL('../../resources/js/seller-product-create.js', import.meta.url), 'utf8').replace('export function', 'function');
    env.run(script);
    assert.equal(nodes.imagePreview.children.length, 0);
    env.context.initProductCreate(form, env.doc);
    assert.equal(nodes.imagePicker.events.change.length, 1);
    let prevented = false;
    form.emit('submit', { submitter: { value: 'publish' }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(nodes.addProductImages.focused, true);
    nodes.addProductImages.click();
    assert.equal(nodes.imagePicker.clicked, true);
    const first = { name: 'first.png', type: 'image/png', size: 1024 };
    const second = { name: 'second.webp', type: 'image/webp', size: 1024 };
    nodes.imagePicker.files = [first, second]; nodes.imagePicker.emit('change');
    assert.deepEqual(nodes.images.files, [first, second]);
    assert.equal(nodes.imagePreview.children[0].children[0].alt, 'Selected product image 1');
    assert.equal(nodes.imagePreview.children[0].children[1].textContent, 'Main Image');
    nodes.imagePreview.children[0].children.at(-1).click();
    assert.deepEqual(nodes.images.files, [second]);
    assert.equal(nodes.imagePreview.children[0].children[1].textContent, 'Main Image');
    nodes.imagePicker.files = [{ type: 'image/png', size: 5 * 1024 * 1024 + 1 }]; nodes.imagePicker.emit('change');
    assert.deepEqual(nodes.images.files, [second]); assert.match(nodes.imageUploadStatus.textContent, /5 MB/);
    nodes.imagePicker.files = Array.from({ length: 5 }, () => first); nodes.imagePicker.emit('change');
    assert.equal(nodes.images.files.length, 5); assert.match(nodes.imageUploadStatus.textContent, /5 images/);
    assert.equal(nodes.imagePreview.children.length, 5);
    prevented = false;
    form.emit('submit', { submitter: { value: 'publish' }, preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
    form.emit('submit', { submitter: { value: 'publish' }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
});
