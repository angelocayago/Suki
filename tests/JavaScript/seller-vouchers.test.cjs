const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/seller/vouchers/partials/script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness({deferred=false,failure=false} = {}) {
    const element = () => ({
        value:'', textContent:'', disabled:false, handlers:{}, attributes:{}, dataset:{},
        addEventListener(type, callback) { this.handlers[type] = callback; },
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
        setCustomValidity(message) { this.validationMessage = message; },
    });
    const ids = Object.fromEntries(['shopVoucherForm','voucherDiscountType','voucherDiscountValue','voucherDiscountLabel','voucherStartsAt','voucherExpiresAt','voucherCode','generateVoucherCode','voucherCodeStatus','saveShopVoucher'].map(id => [id,element()]));
    ids.voucherDiscountType.value = 'FIXED'; ids.saveShopVoucher.textContent = 'Create Voucher';
    ids.generateVoucherCode.dataset.url = '/seller/vouchers/generate-code';
    ids.shopVoucherForm.reportValidity = () => !ids.voucherExpiresAt.validationMessage;
    const archive = element(), archiveButton = element(); archive.querySelector = () => archiveButton;
    const windowEvents = [], calls = [];
    let release;
    const response = {ok:!failure,json:async()=>failure?{message:'Unavailable'}:{code:'SHOP-ABCDEFGHIJ'}};
    const pending = new Promise(resolve => release = () => resolve(response));
    vm.runInNewContext(source, {
        document: {
            addEventListener: (_,callback) => callback(),
            getElementById: id => ids[id], querySelectorAll: () => [archive],
        },
        window: { addEventListener(_,callback) { windowEvents.push(callback); } },
        async fetch(url, options) { calls.push({url,options}); return deferred ? pending : response; },
    });
    function submit() {
        const event = {preventDefault(){ this.prevented = true; }};
        ids.shopVoucherForm.handlers.submit(event); return event;
    }
    return {ids,calls,release,submit,archive,archiveButton,windowEvents};
}

test('discount type changes limits and labels without erasing values, dates or manual codes', () => {
    const {ids} = harness();
    ids.voucherDiscountValue.value = '250.00'; ids.voucherCode.value = 'MY-MANUAL'; ids.voucherStartsAt.value = '2026-11-01T09:00';
    ids.voucherDiscountType.value = 'PERCENTAGE'; ids.voucherDiscountType.handlers.change();
    assert.equal(ids.voucherDiscountValue.max,'100'); assert.equal(ids.voucherDiscountLabel.textContent,'Discount Value (%)');
    assert.equal(ids.voucherDiscountValue.value,'250.00'); assert.equal(ids.voucherCode.value,'MY-MANUAL');
    ids.voucherDiscountType.value = 'FIXED'; ids.voucherDiscountType.handlers.change();
    assert.equal(ids.voucherDiscountValue.max,'21474836.47'); assert.equal(ids.voucherStartsAt.value,'2026-11-01T09:00');
});

test('generation is explicit, uses server response, and prevents repeated requests', async () => {
    const {ids,calls,release} = harness({deferred:true});
    ids.voucherCode.value = 'MANUAL'; assert.equal(calls.length,0);
    const request = ids.generateVoucherCode.handlers.click();
    assert.equal(ids.generateVoucherCode.disabled,true);
    await ids.generateVoucherCode.handlers.click(); assert.equal(calls.length,1);
    assert.equal(calls[0].url,'/seller/vouchers/generate-code'); assert.equal(calls[0].options.cache,'no-store');
    release(); await request;
    assert.equal(ids.voucherCode.value,'SHOP-ABCDEFGHIJ'); assert.equal(ids.generateVoucherCode.disabled,false);
    assert.equal(ids.voucherCodeStatus.textContent,'Code generated.');
});

test('manual edits made during generation win over a delayed generated response', async () => {
    const {ids,release,submit} = harness({deferred:true});
    const request = ids.generateVoucherCode.handlers.click();
    assert.equal(submit().prevented,true);
    ids.voucherCode.value = 'MY-NEW-CODE'; ids.voucherCode.handlers.input();
    release(); await request;
    assert.equal(ids.voucherCode.value,'MY-NEW-CODE'); assert.match(ids.voucherCodeStatus.textContent,/typed code was kept/);
});

test('failed generation keeps a typed code and leaves manual or automatic saving available', async () => {
    const {ids,submit} = harness({failure:true});
    ids.voucherCode.value = 'KEEP-THIS'; await ids.generateVoucherCode.handlers.click();
    assert.equal(ids.voucherCode.value,'KEEP-THIS'); assert.match(ids.voucherCodeStatus.textContent,/could not be generated/);
    assert.equal(ids.generateVoucherCode.disabled,false); assert.equal(submit().prevented,undefined);
});

test('blank codes submit for server generation, duplicate submissions stop, and history restores controls', () => {
    const {ids,submit,windowEvents} = harness();
    assert.equal(submit().prevented,undefined); assert.equal(ids.voucherCode.value,'');
    assert.equal(ids.shopVoucherForm.attributes['aria-busy'],'true'); assert.equal(ids.saveShopVoucher.disabled,true);
    assert.equal(submit().prevented,true);
    windowEvents.forEach(callback=>callback());
    assert.equal(ids.saveShopVoucher.disabled,false); assert.equal(ids.saveShopVoucher.textContent,'Create Voucher');
});

test('expiry must be later even when the two inputs use different seconds formatting', () => {
    const {ids,submit} = harness();
    ids.voucherStartsAt.value = '2026-11-01T09:00'; ids.voucherExpiresAt.value = '2026-11-01T09:00:00';
    assert.equal(submit().prevented,true); assert.match(ids.voucherExpiresAt.validationMessage,/later/);
    ids.voucherExpiresAt.value = '2026-11-01T09:00:01'; ids.voucherExpiresAt.handlers.input();
    assert.equal(submit().prevented,undefined);
});

test('archive submission is protected against accidental duplicate submits', () => {
    const {archive,archiveButton} = harness();
    archive.handlers.submit({preventDefault(){ throw Error('First archive blocked'); }});
    assert.equal(archiveButton.disabled,true);
    let blocked = false; archive.handlers.submit({preventDefault(){ blocked=true; }}); assert.equal(blocked,true);
});
