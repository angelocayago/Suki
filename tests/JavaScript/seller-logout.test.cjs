const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/seller/logout-modal.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const document = { activeElement: null };
    function element() {
        return { handlers: {}, disabled: false,
            addEventListener(type, fn) { this.handlers[type] = fn; },
            focus() { document.activeElement = this; },
        };
    }
    const cancel = element(), confirm = element(), dialog = element();
    dialog.open = false;
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => { dialog.open = false; };
    dialog.getBoundingClientRect = () => ({ left: 100, top: 100, right: 520, bottom: 300 });
    dialog.querySelector = selector => selector.includes('cancel') ? cancel : confirm;
    const forms = [element(), element()];
    forms.forEach(form => { form.trigger = element(); form.querySelector = () => form.trigger; form.method = 'POST'; form.action = '/logout'; form.csrf = 'original-token'; });
    document.getElementById = id => id === 'sellerLogoutDialog' ? dialog : null;
    document.querySelectorAll = selector => selector === 'form[data-seller-logout]' ? forms : [];
    document.addEventListener = (type, fn) => { assert.equal(type, 'DOMContentLoaded'); fn(); };
    const submitted = [];
    vm.runInNewContext(source, { document, HTMLFormElement: { prototype: { submit() { submitted.push(this); } } } });
    function event(extra = {}) { return { prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; }, ...extra }; }
    function open(index = 0, withSubmitter = true) {
        forms[index].trigger.focus();
        const e = event(withSubmitter ? { submitter: forms[index].trigger } : {});
        forms[index].handlers.submit(e);
        assert.equal(e.prevented, true);
    }
    return { document, cancel, confirm, dialog, forms, submitted, event, open };
}

test('both entry points open the same dialog without submitting; No restores original focus', () => {
    const h = harness();
    for (const i of [0, 1]) {
        h.open(i); assert.equal(h.dialog.open, true); assert.equal(h.document.activeElement, h.cancel);
        assert.equal(h.submitted.length, 0);
        h.cancel.handlers.click(); assert.equal(h.dialog.open, false);
        assert.equal(h.document.activeElement, h.forms[i].trigger);
    }
});

test('native Escape cancellation is intercepted and dismisses without logout', () => {
    const h = harness(); h.open(1);
    const e = h.event(); h.dialog.handlers.cancel(e);
    assert.equal(e.prevented, true); assert.equal(h.dialog.open, false);
    assert.equal(h.document.activeElement, h.forms[1].trigger); assert.equal(h.submitted.length, 0);
});

test('backdrop dismisses, but clicks on content or dialog padding do not', () => {
    const h = harness(); h.open();
    for (const target of [h.cancel, h.dialog]) {
        const e = h.event({ target, clientX: 150, clientY: 150 }); h.dialog.handlers.click(e);
        assert.equal(h.dialog.open, true); assert.equal(e.stopped, true);
    }
    h.dialog.handlers.click(h.event({ target: h.dialog, clientX: 50, clientY: 50 }));
    assert.equal(h.dialog.open, false); assert.equal(h.submitted.length, 0);
});

test('Tab wraps between buttons and modal keys cannot activate sidebar handlers', () => {
    const h = harness(); h.open();
    const reverse = h.event({ key: 'Tab', shiftKey: true }); h.dialog.handlers.keydown(reverse);
    assert.equal(reverse.prevented, true); assert.equal(h.document.activeElement, h.confirm);
    const forward = h.event({ key: 'Tab' }); h.dialog.handlers.keydown(forward);
    assert.equal(forward.prevented, true); assert.equal(h.document.activeElement, h.cancel);
    const escape = h.event({ key: 'Escape' }); h.dialog.handlers.keydown(escape);
    assert.equal(escape.stopped, true); assert.equal(escape.prevented, false);
});

test('confirmation submits original POST form and CSRF exactly once, guarding repeat activation', () => {
    const h = harness(); h.open(1, false); h.open(0);
    h.confirm.handlers.click(); h.confirm.handlers.click(); h.open(0);
    assert.deepEqual(h.submitted, [h.forms[1]]);
    assert.equal(h.submitted[0].method, 'POST'); assert.equal(h.submitted[0].action, '/logout'); assert.equal(h.submitted[0].csrf, 'original-token');
    assert.equal(h.cancel.disabled, true); assert.equal(h.confirm.disabled, true);
    h.cancel.handlers.click(); assert.equal(h.dialog.open, true);
});

test('global confirmation respects the dedicated Seller dialog and retains other roles', () => {
    const base = path.join(__dirname, '../../resources');
    const global = fs.readFileSync(path.join(base, 'js/app.js'), 'utf8');
    assert.match(global, /form\[data-confirm-logout\]/);
    assert.match(global, /if \(event\.defaultPrevented\) return;/);
    for (const file of ['views/layouts/seller.blade.php', 'views/seller/header/controls.blade.php']) {
        const markup = fs.readFileSync(path.join(base, file), 'utf8');
        assert.match(markup, /method="POST" data-seller-logout/);
    }
    assert.doesNotMatch(source, /window\.(confirm|alert)\(/);
});
