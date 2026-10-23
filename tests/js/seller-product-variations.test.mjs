import { test } from 'node:test';
import assert from 'node:assert/strict';
import { combinationKey, combinations, reconcileRows, initVariantEditor, parseOptionValues } from '../../resources/js/seller-product-variations.js';

test('Cartesian generation is deterministic for one, two and three groups', () => {
    const groups = [{ name: 'Size', values: ['Small', 'Large'] }, { name: 'Color', values: ['Black', 'White'] }];
    assert.deepEqual(combinations(groups), [{ Size: 'Small', Color: 'Black' }, { Size: 'Small', Color: 'White' }, { Size: 'Large', Color: 'Black' }, { Size: 'Large', Color: 'White' }]);
    assert.equal(combinations(groups.slice(0, 1)).length, 2);
    assert.equal(combinations([...groups, { name: 'Material', values: ['Cotton', 'Silk'] }]).length, 8);
});
test('Normalization rejects duplicate, empty and excessive choices', () => {
    for (const groups of [[{ name: '', values: ['x'] }], [{ name: 'Size', values: [' Small', 'small '] }],
        [{ name: 'A', values: ['x'] }, { name: ' a ', values: ['y'] }], [{ name: 'Size', values: [''] }],
        [{ name: 'A', values: Array.from({ length: 8 }, (_, i) => String(i)) }, { name: 'B', values: Array.from({ length: 8 }, (_, i) => String(i)) }]]) {
        assert.throws(() => combinations(groups));
    }
});
test('Reordering preserves prices, stock, IDs, SKUs and deactivation while new values get new rows', () => {
    const saved = [{ options: { Size: 'Small', Color: 'Black' }, id: 1, sku: 'legacy-001', price: '9.25', stock: '3', is_active: false }];
    const rows = reconcileRows([{ Color: 'Black', Size: 'Small' }, { Color: 'White', Size: 'Small' }], saved);
    assert.equal(rows[0].sku, 'legacy-001'); assert.equal(rows[0].id, 1); assert.equal(rows[0].price, '9.25');
    assert.equal(rows[0].stock, '3'); assert.equal(rows[0].is_active, false);
    assert.equal(rows[1].id, undefined); assert.equal(rows[1].price, '');
    assert.equal(combinationKey({ ' size ': ' SMALL' }), combinationKey({ Size: 'Small' }));
});
test('Seller labels remain data, including punctuation and special object keys', () => {
    const rows = combinations([{ name: '__proto__', values: ['<script> & [value]'] }]);
    assert.equal(Object.getPrototypeOf(rows[0]), Object.prototype);
    assert.equal(Object.entries(rows[0])[0][1], '<script> & [value]');
});

class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.events = {}; this.dataset = {}; }
    append(...children) { this.children.push(...children); }
    replaceChildren() { this.children = []; }
    addEventListener(type, listener) { (this.events[type] ??= []).push(listener); }
    emit(type) { this.events[type]?.forEach(listener => listener()); }
    click() { if (!this.disabled) this.emit('click'); }
    focus() { this.focused = true; }
    querySelector(selector) { return this.querySelectorAll(selector)[0]; }
    closest() { return this.form; }
    querySelectorAll(selector) {
        return this.children.flatMap(child => [child, ...child.querySelectorAll(selector)])
            .filter(child => selector.split(',').map(tag => tag.trim()).includes(child.tag));
    }
}
function editor({ persisted = false, mode = 'simple', rows = [], groups: suppliedGroups } = {}) {
    const root = new Element(); root.dataset.persisted = persisted ? '1' : '0';
    const fields = new Element(); const groups = new Element(); const rowContainer = new Element();
    const generate = new Element('button'); const addGroup = new Element('button'); const status = new Element('p');
    fields.append(groups, rowContainer, generate, addGroup);
    const optionGroups = suppliedGroups ?? [{ name: 'Size [US]', values: ['Small', 'Large'] }, { name: 'Color', values: ['Black', 'White'] }];
    const state = new Element('script'); state.textContent = JSON.stringify({ groups: optionGroups, rows, savedGroups: persisted ? optionGroups : [] });
    const table = new Element();
    const form = new Element('form'); root.form = form;
    const nodes = { '[data-variant-table]': table, '[data-variant-state]': state, '[data-variable-fields]': fields, '[data-option-groups]': groups,
        '[data-variant-rows]': rowContainer, '[data-variant-status]': status, '[data-generate]': generate, '[data-add-group]': persisted ? null : addGroup };
    root.querySelector = selector => nodes[selector];
    const select = new Element('select'); select.value = mode;
    const simple = new Element(); const simplePrice = new Element('input'); simplePrice.id = 'price'; simple.append(simplePrice);
    const sale = new Element(); const saleToggle = new Element('input'); saleToggle.id = 'on_sale'; saleToggle.checked = false; sale.append(saleToggle);
    const doc = { createElement: tag => new Element(tag), getElementById: id => id === 'productType' ? select : saleToggle,
        querySelectorAll: () => [simple, sale] };
    initVariantEditor(root, doc);
    const field = name => fields.querySelectorAll('input').find(input => input.name === name);
    return { root, doc, form, table, fields, groups, rowContainer, generate, addGroup, status, select, simple, sale, simplePrice, field };
}
test('Optional groups are visible initially; explicit generation switches pricing and keeps indexed selections', () => {
    const ui = editor();
    assert.equal(ui.fields.hidden, false); assert.equal(ui.simplePrice.disabled, false);
    ui.generate.click();
    assert.equal(ui.simplePrice.disabled, true);
    assert.equal(ui.rowContainer.children.length, 4);
    assert.equal(ui.table.hidden, false);
    const price = ui.field('variant_rows[0][price]'); price.value = '24.75'; price.emit('input');
    ui.generate.click();
    assert.equal(ui.field('variant_rows[0][price]').value, '24.75');
    assert.equal(ui.field('variant_rows[0][selections][]').value, 'Small');
    assert.equal(ui.fields.querySelectorAll('input').some(input => input.name?.includes('[Size [US]]')), false);
    while (ui.groups.children.length) ui.groups.children[0].querySelectorAll('button')[0].click();
    assert.equal(ui.rowContainer.children.length, 0);
    assert.equal(ui.select.value, 'simple'); assert.equal(ui.simplePrice.disabled, false);
});

test('Saved labels are read-only, added values can be removed, and persisted rows retain hidden identity', () => {
    const ui = editor({ persisted: true, mode: 'variable', rows: [{ id: 42, sku: 'LEGACY', options: { 'Size [US]': 'Small', Color: 'Black' }, price: '9.25', stock: 2, is_active: true }] });
    assert.equal(ui.field('option_groups[0][name]').readOnly, true);
    assert.equal(ui.field('option_groups[0][values][]').readOnly, true);
    assert.equal(ui.field('variant_rows[0][id]').value, 42);
    const first = ui.groups.children[0]; first.querySelectorAll('button').find(button => button.textContent === 'Add value').click();
    const inputs = ui.groups.children[0].querySelectorAll('input');
    assert.equal(inputs.at(-1).readOnly, false);
    ui.groups.children[0].querySelectorAll('button').find(button => button.textContent === 'Remove').click();
    assert.equal(ui.groups.children[0].querySelectorAll('input').length, 3);
});

test('Empty initial state supports adding comma-separated options and removing them back to simple mode', () => {
    const ui = editor({ groups: [] });
    assert.equal(ui.fields.hidden, false);
    assert.equal(ui.generate.hidden, true);
    assert.equal(ui.table.hidden, true);
    assert.equal(ui.simplePrice.disabled, false);
    ui.addGroup.click();
    assert.equal(ui.groups.children.length, 1);
    assert.equal(ui.generate.disabled, true);
    const name = ui.field('option_groups[0][name]');
    name.value = ' Size '; name.emit('input');
    const values = ui.groups.children[0].querySelectorAll('input').find(input => !input.name);
    values.value = ' Small,  Medium , Large '; values.emit('input');
    assert.equal(ui.generate.disabled, false);
    assert.equal(ui.rowContainer.children.length, 0);
    assert.equal(ui.simplePrice.disabled, false);
    assert.equal(ui.select.value, 'simple');
    ui.generate.click();
    assert.equal(ui.select.value, 'variable');
    assert.equal(ui.rowContainer.children.length, 3);
    assert.equal(ui.field('option_groups[0][values][]').value, 'Small');
    assert.equal(ui.simplePrice.disabled, true);
    assert.equal(ui.simple.hidden, true); assert.equal(ui.sale.hidden, true);
    ui.groups.children[0].querySelector('button').click();
    assert.equal(ui.select.value, 'simple'); assert.equal(ui.simplePrice.disabled, false);
    assert.equal(ui.table.hidden, true);
    assert.equal(ui.fields.querySelectorAll('input').some(input => input.name?.startsWith('variant_rows')), false);
});

test('Comma parsing normalizes whitespace while retaining blanks and duplicates for validation', () => {
    assert.deepEqual(parseOptionValues(' Small, dark   BLUE ,Large '), ['Small', 'dark BLUE', 'Large']);
    for (const value of ['', 'Small,', 'Small,,Large', 'Small, small']) {
        assert.throws(() => combinations([{ name: 'Size', values: parseOptionValues(value) }]));
    }
});

test('Group and combination limits disable actions without dropping options or existing prices', () => {
    const ui = editor(); ui.generate.click();
    ui.addGroup.click(); assert.equal(ui.groups.children.length, 3); assert.equal(ui.addGroup.disabled, true);
    ui.addGroup.click(); assert.equal(ui.groups.children.length, 3);
    ui.groups.children[2].querySelector('button').click();
    assert.equal(ui.addGroup.disabled, false);
    const values = ui.groups.children[0].querySelectorAll('input').find(input => !input.name);
    values.value = Array.from({ length: 10 }, (_, i) => `Size ${i}`).join(','); values.emit('input');
    const color = ui.groups.children[1].querySelectorAll('input').find(input => !input.name);
    color.value = 'A,B,C,D,E,F'; color.emit('input');
    assert.equal(ui.generate.disabled, true); assert.match(ui.status.textContent, /50/);
    assert.equal(ui.rowContainer.children.length, 4);
    assert.equal(ui.field('option_groups[0][values][]').value, 'Size 0');
});

test('Regeneration is explicit, reports removed combinations, and restores cached price and zero stock', () => {
    const ui = editor(); ui.generate.click();
    const price = ui.field('variant_rows[0][price]'); price.value = '19.50'; price.emit('input');
    const stock = ui.field('variant_rows[0][stock]'); stock.value = '0'; stock.emit('input');
    const values = ui.groups.children[1].querySelectorAll('input').find(input => !input.name);
    values.value = 'White'; values.emit('input');
    assert.equal(ui.rowContainer.children.length, 4);
    let prevented = false;
    ui.form.events.submit[0]({ preventDefault() { prevented = true; }, stopImmediatePropagation() {} });
    assert.equal(prevented, true);
    ui.generate.click(); assert.equal(ui.rowContainer.children.length, 2);
    assert.match(ui.status.textContent, /2 removed/);
    values.value = 'Black,White'; values.emit('input'); ui.generate.click();
    assert.equal(ui.field('variant_rows[0][price]').value, '19.50');
    assert.equal(ui.field('variant_rows[0][stock]').value, '0');
    prevented = false;
    ui.form.events.submit[0]({ preventDefault() { prevented = true; }, stopImmediatePropagation() {} });
    assert.equal(prevented, false);
});

test('Restored rows render immediately with values, immutable identity and one set of handlers', () => {
    const ui = editor({ mode: 'variable', rows: [{ options: { 'Size [US]': 'Small', Color: 'Black' }, price: '12.25', stock: '7', is_active: true }] });
    assert.equal(ui.table.hidden, false); assert.equal(ui.simplePrice.disabled, true);
    assert.equal(ui.field('variant_rows[0][price]').value, '12.25');
    assert.equal(ui.field('variant_rows[0][stock]').value, '7');
    assert.equal(ui.rowContainer.querySelectorAll('input').some(input => input.name?.endsWith('[sku]')), false);
    initVariantEditor(ui.root, ui.doc);
    assert.equal(ui.addGroup.events.click.length, 1);
});

test('Saved simple products keep their type and do not expose option conversion', () => {
    const ui = editor({ persisted: true, mode: 'simple', groups: [] });
    assert.equal(ui.fields.hidden, true); assert.equal(ui.simplePrice.disabled, false);
    assert.equal(ui.generate.disabled, true); assert.equal(ui.select.value, 'simple');
});
