import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initSellerFilters } from '../../resources/js/seller-filters.js';

function fixture() {
    const element = () => ({ events: {}, attrs: {}, addEventListener(type, fn) { (this.events[type] ??= []).push(fn); }, emit(type, event = {}) { this.events[type]?.forEach(fn => fn(event)); }, setAttribute(key, value) { this.attrs[key] = value; }, focus() { this.focused = true; } });
    const trigger = element(), panel = element(), close = element();
    panel.hidden = true; panel.unsavedValue = 'Keep this filter';
    const root = { dataset: {}, querySelector: selector => ({ '[data-filter-toggle]': trigger, '[data-filter-panel]': panel, '[data-filter-close]': close })[selector] };
    initSellerFilters(root);
    return { root, trigger, panel, close };
}

for (const page of ['Products', 'Inventory', 'Reports', 'Sales', 'Orders']) {
    test(`${page} shared filter toggles preserve input, close on Escape and bind once`, () => {
        const { root, trigger, panel, close } = fixture();
        initSellerFilters(root);
        assert.equal(trigger.events.click.length, 1);
        trigger.emit('click'); assert.equal(panel.hidden, false); assert.equal(trigger.attrs['aria-expanded'], 'true');
        trigger.emit('click'); assert.equal(panel.hidden, true); assert.equal(panel.unsavedValue, 'Keep this filter');
        trigger.emit('click'); close.emit('click'); assert.equal(panel.hidden, true); assert.equal(trigger.focused, true);
        trigger.emit('click'); let prevented = false;
        panel.emit('keydown', { key: 'Escape', preventDefault() { prevented = true; } });
        assert.equal(prevented, true); assert.equal(panel.hidden, true); assert.equal(trigger.attrs['aria-expanded'], 'false');
    });
}

test('Separate panels do not affect each other and missing UI is a safe no-op', () => {
    const a = fixture(), b = fixture();
    a.trigger.emit('click'); assert.equal(a.panel.hidden, false); assert.equal(b.panel.hidden, true);
    initSellerFilters(null);
    initSellerFilters({ dataset: {}, querySelector() { return null; } });
});
