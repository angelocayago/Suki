const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/buyer/vouchers/script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function page(clipboard) {
    const status = {}, input = {value: 'REAL-SHOP-CODE', focus() { this.focused = true; }, select() { this.selected = true; }};
    const button = {dataset: {copyVoucher: 'code'}, addEventListener(type, fn) { this[type] = fn; }, closest() { return {querySelector: () => status}; }};
    const submit = {}, form = {dataset: {}, addEventListener(type, fn) { this[type] = fn; }, querySelector: () => submit};
    const window = {addEventListener(type, fn) { this[type] = fn; }};
    vm.runInNewContext(source, {navigator: {clipboard}, window, document: {
        addEventListener(type, fn) { fn(); }, getElementById: () => input,
        querySelectorAll: selector => selector === '[data-copy-voucher]' ? [button] : [form],
    }});
    return {button, input, status, form, submit, window};
}

test('Copy Code copies the real saved code without claiming or redeeming', async () => {
    let copied;
    const {button, status, form} = page({async writeText(value) { copied = value; }});
    await button.click();
    assert.equal(copied, 'REAL-SHOP-CODE');
    assert.equal(status.textContent, 'Voucher code copied.');
    assert.equal(form.dataset.submitting, undefined);
});

test('clipboard failure selects the code for accessible manual copying', async () => {
    const {button, input, status} = page({async writeText() { throw Error('Unavailable'); }});
    await button.click();
    assert.equal(input.focused, true); assert.equal(input.selected, true);
    assert.match(status.textContent, /highlighted code/);
});

test('claim form prevents a second submission and recovers on browser navigation', () => {
    const {form, submit, window} = page({});
    let prevented = 0;
    form.submit({preventDefault() { prevented++; }});
    form.submit({preventDefault() { prevented++; }});
    assert.equal(prevented, 1); assert.equal(submit.disabled, true);
    window.pageshow();
    assert.equal(submit.disabled, false); assert.equal(form.dataset.submitting, undefined);
});
