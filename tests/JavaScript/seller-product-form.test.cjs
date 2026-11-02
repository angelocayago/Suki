const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// Run the actual page script with a small DOM double; no browser dependencies.
const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/seller/partials/product-form-script.blade.php'), 'utf8');
const source = blade.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/const oldColors = .*?;/, 'const oldColors = ["Blue"];');

function element() {
    return {
        children: [], handlers: {}, dataset: {}, value: '',
        addEventListener(type, callback) { this.handlers[type] = callback; },
        appendChild(child) { this.children.push(child); return child; },
        append(...children) { this.children.push(...children); },
        replaceChildren() { this.children = []; },
        focus() {},
        setCustomValidity(message) { this.validationMessage = message; },
    };
}
function stockRow(value, status = 'active') {
    const row = element(), stock = element(), state = element(), output = element();
    stock.value = String(value); state.value = status;
    row.querySelector = selector => selector.includes('data-variant-stock') ? stock
        : selector.includes('data-variant-status') ? state : selector.includes('data-stock-indicator') ? output : stock;
    return { row, stock, state, output };
}
function harness(withChips = false) {
    const primary = stockRow(0);
    const ids = Object.fromEntries(['productForm', 'productVariantRows', 'productVariantTemplate', 'addProductVariant', 'primaryOptions', 'addPrimaryOption', 'images', 'imagePreview', 'imageUploadStatus'].map(id => [id, element()]));
    const optionInputs = [{name:'variants[4][options][Flavor]',value:'Vanilla'},{name:'variants[4][options][Color]',value:'Blue'}];
    if (withChips) ids.variantOptionChips = element();
    ids.productForm.querySelectorAll = selector => selector === '[data-stock-row]' ? [primary.row] : [];
    ids.productVariantRows.querySelectorAll = selector => selector === '[name]' ? [{name:'variants[4][id]'}] : optionInputs;
    ids.productVariantTemplate.innerHTML = '<div><input name="variants[__INDEX__][stock]"></div>';
    const inserted = [], revoked = [];
    vm.runInNewContext(source, {
        document: {
            addEventListener: (_, callback) => callback(),
            getElementById: id => ids[id],
            createElement: () => {
                const node = element();
                Object.defineProperty(node, 'innerHTML', { set(html) {
                    const variant = stockRow(0);
                    variant.row.html = html; inserted.push(variant); this.firstElementChild = variant.row;
                } });
                return node;
            },
        },
        window: { addEventListener() {} },
        URL: { createObjectURL: file => 'blob:' + file.name, revokeObjectURL: url => revoked.push(url) },
    });
    return { primary, ids, inserted, revoked, optionInputs };
}

test('active variant stock boundaries, inactive exclusion, and input updates', () => {
    const { primary, ids } = harness();
    for (const [quantity, text, level] of [[0,'Out of Stock','empty'],[1,'Low Stock','low'],[10,'Low Stock','low'],[11,'In Stock','in']]) {
        primary.stock.value = String(quantity);
        ids.productForm.handlers.input({ target: { closest: () => primary.row } });
        assert.equal(primary.output.textContent, text);
        assert.equal(primary.output.dataset.level, level);
    }
    primary.state.value = 'inactive';
    ids.productForm.handlers.change({ target: { closest: () => primary.row } });
    assert.match(primary.output.textContent, /excluded from stock alerts/);
    assert.equal(primary.output.dataset.level, 'none');
});

test('adding variants after sparse validation input allocates unused indexes', () => {
    const { ids, inserted } = harness();
    ids.addProductVariant.handlers.click(); ids.addProductVariant.handlers.click();
    assert.match(inserted[0].row.html, /variants\[5\]\[stock\]/);
    assert.match(inserted[1].row.html, /variants\[6\]\[stock\]/);
    assert.equal(ids.productVariantRows.children.length, 2);
    assert.equal(inserted[0].output.textContent, 'Out of Stock');
});

test('photos validate limits and retain aligned color inputs with safe filenames', () => {
    const { ids, revoked } = harness();
    const image = name => ({ name, type: 'image/png', size: 100 });
    ids.images.files = Array.from({length: 21}, () => image('too-many.png'));
    ids.images.handlers.change();
    assert.match(ids.images.validationMessage, /up to 20/);
    assert.equal(ids.imagePreview.children.length, 0);
    ids.images.files = [image('<unsafe>.png'), image('red.png')];
    ids.images.handlers.change();
    assert.equal(ids.images.validationMessage, '');
    const figures = ids.imagePreview.children;
    assert.equal(figures.length, 2);
    assert.equal(figures[0].children[1].textContent, '<unsafe>.png');
    assert.equal(figures[0].children[2].children[0].name, 'image_colors[0]');
    assert.equal(figures[0].children[2].children[0].value, 'Blue');
    assert.equal(figures[1].children[2].children[0].name, 'image_colors[1]');
    ids.images.files = [image('next.png')]; ids.images.handlers.change();
    assert.equal(revoked.length, 2);
    assert.equal(ids.imagePreview.children.length, 1);
    ids.images.files = [{name:'oversize.png',type:'image/png',size:6*1024*1024}]; ids.images.handlers.change();
    assert.match(ids.images.validationMessage, /no larger than 4 MB/);
});

test('edit variant summary retains incoming chips for both Color and custom options', () => {
    const {ids,optionInputs} = harness(true);
    assert.deepEqual(ids.variantOptionChips.children.map(group=>group.children[0].textContent),['Flavor','Color']);
    assert.equal(ids.variantOptionChips.children[0].children[1].children[0].textContent,'Vanilla');
    optionInputs[0].value='Chocolate';
    ids.productForm.handlers.input({target:{name:optionInputs[0].name,closest:()=>null}});
    assert.equal(ids.variantOptionChips.children[0].children[1].children[0].textContent,'Chocolate');
});
