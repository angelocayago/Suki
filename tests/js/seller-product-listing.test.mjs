import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initProductListing } from '../../resources/js/seller-product-listing.js';

class Element {
    constructor(value = '') { this.value = value; this.dataset = {}; this.events = {}; this.attrs = {}; this.checked = false; this.disabled = false; }
    addEventListener(type, handler) { (this.events[type] ??= []).push(handler); }
    emit(type, event = {}) { this.events[type]?.forEach(handler => handler(event)); }
    setAttribute(name, value) { this.attrs[name] = value; }
    focus() { this.focused = true; }
}

function fixture() {
    const toggle = new Element(), panel = new Element(), close = new Element(), clear = new Element(), count = new Element();
    panel.hidden = true;
    const boxes = ['P0001', 'P0002', 'P0003', 'P0001', 'P0002', 'P0003'].map(id => new Element(id));
    const headers = [new Element(), new Element()];
    const rows = boxes.map(box => { const row = new Element(); row.dataset.productRow = box.value; return row; });
    const window = new Element();
    const root = new Element();
    root.ownerDocument = { defaultView: window };
    root.querySelector = selector => ({ '[data-filter-toggle]': toggle, '[data-filter-panel]': panel, '[data-filter-close]': close, '[data-clear-selection]': clear, '[data-selected-count]': count })[selector];
    root.querySelectorAll = selector => ({ '[data-product-checkbox]': boxes, '[data-select-page]': headers, '[data-product-row]': rows })[selector];
    initProductListing(root);
    return { root, toggle, panel, close, clear, count, boxes, headers, rows, window };
}

test('Filter toggle opens and closes without submitting or discarding draft field values', () => {
    const { toggle, panel } = fixture();
    panel.draftSearch = 'Unsaved search';
    toggle.emit('click');
    assert.equal(panel.hidden, false); assert.equal(toggle.attrs['aria-expanded'], 'true');
    toggle.emit('click');
    assert.equal(panel.hidden, true); assert.equal(toggle.attrs['aria-expanded'], 'false');
    assert.equal(panel.draftSearch, 'Unsaved search');
});

test('Close and keyboard Escape restore focus and synchronize aria-expanded', () => {
    const { toggle, panel, close } = fixture();
    toggle.emit('click'); close.emit('click');
    assert.equal(panel.hidden, true); assert.equal(toggle.focused, true);
    toggle.emit('click');
    let prevented = false;
    panel.emit('keydown', { key: 'Escape', preventDefault() { prevented = true; } });
    assert.equal(prevented, true); assert.equal(panel.hidden, true); assert.equal(toggle.attrs['aria-expanded'], 'false');
    panel.emit('keydown', { key: 'Tab' });
    assert.equal(panel.hidden, true);
});

test('Individual selection synchronizes desktop/mobile copies and indeterminate headers', () => {
    const { boxes, headers, rows, count, clear } = fixture();
    boxes[0].checked = true; boxes[0].emit('change');
    assert.equal(boxes[3].checked, true); assert.equal(count.textContent, '1 selected on this page');
    assert.ok(headers.every(box => box.indeterminate && !box.checked));
    assert.equal(rows[0].dataset.selected, 'true'); assert.equal(clear.disabled, false);
    boxes[3].checked = false; boxes[3].emit('change');
    assert.equal(boxes[0].checked, false); assert.equal(count.textContent, '0 selected on this page');
    assert.ok(headers.every(box => !box.indeterminate && !box.checked));
});

test('Select All selects unique current-page products; removing one creates partial state', () => {
    const { boxes, headers, count } = fixture();
    headers[0].checked = true; headers[0].emit('change');
    assert.ok(boxes.every(box => box.checked)); assert.equal(count.textContent, '3 selected on this page');
    assert.ok(headers.every(box => box.checked && !box.indeterminate));
    boxes[1].checked = false; boxes[1].emit('change');
    assert.equal(count.textContent, '2 selected on this page'); assert.equal(boxes[4].checked, false);
    assert.ok(headers.every(box => !box.checked && box.indeterminate));
    headers[1].checked = false; headers[1].emit('change');
    assert.ok(boxes.every(box => !box.checked));
});

test('Clear Selection clears checks, highlights, count and header state', () => {
    const { boxes, headers, count, clear, rows } = fixture();
    headers[0].checked = true; headers[0].emit('change'); clear.emit('click');
    assert.ok(boxes.every(box => !box.checked)); assert.ok(rows.every(row => row.dataset.selected === 'false'));
    assert.equal(count.textContent, '0 selected on this page'); assert.equal(clear.disabled, true);
    assert.ok(headers.every(box => !box.checked && !box.indeterminate));
});

test('Navigation and browser history restoration discard selection and stale checked values', () => {
    const { boxes, headers, count, window } = fixture();
    headers[0].checked = true; headers[0].emit('change'); window.emit('pagehide');
    assert.ok(boxes.every(box => !box.checked));
    boxes[0].checked = true; window.emit('pageshow');
    assert.ok(boxes.every(box => !box.checked)); assert.equal(count.textContent, '0 selected on this page');
    const nextPage = fixture(); assert.equal(nextPage.count.textContent, '0 selected on this page');
});

test('Reinitializing does not duplicate handlers and empty pages disable Select All', () => {
    const { root, toggle, boxes, headers } = fixture();
    initProductListing(root);
    assert.equal(toggle.events.click.length, 1); assert.equal(boxes[0].events.change.length, 1);
    const empty = fixture();
    empty.root.dataset.listingInitialized = '';
    empty.root.querySelectorAll = selector => selector === '[data-select-page]' ? empty.headers : [];
    initProductListing(empty.root);
    assert.ok(empty.headers.every(header => header.disabled));
    assert.ok(headers.every(header => !header.disabled));
});
