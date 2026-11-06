const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const partial = name => fs.readFileSync(path.join(__dirname, '../../resources/views/buyer/partials/'+name+'.blade.php'), 'utf8');
const source = partial('receipt-script').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const document = {activeElement: null};
    const node = () => ({dataset: {}, handlers: {}, disabled: false, textContent: '',
        addEventListener(type, fn) {(this.handlers[type] ??= []).push(fn);},
        focus() {document.activeElement = this;}});
    const dialog = node(), cancel = node(), confirm = node(), feedback = node(), window = node();
    const forms = ['ALPHA', 'BETA'].map(id => {
        const form = node();
        form.action = '/orders/'+id+'/status';
        form.fields = {_token: 'csrf-'+id, status: 'completed'};
        form.trigger = node(); form.trigger.disabled = true;
        form.querySelector = () => form.trigger;
        form.submissions = 0;
        return form;
    });
    const fire = (target, type, extra = {}) => {
        const event = {prevented: false, preventDefault() {this.prevented = true;}, ...extra};
        for (const fn of target.handlers[type] ?? []) fn(event);
        return event;
    };
    dialog.querySelector = selector => ({'[data-receipt-cancel]':cancel, '[data-receipt-confirm]':confirm, '[data-receipt-feedback]':feedback})[selector];
    dialog.open = false;
    dialog.showModal = () => {dialog.open = true;};
    dialog.close = () => {dialog.open = false; fire(dialog, 'close');};
    dialog.getBoundingClientRect = () => ({left:100,right:500,top:100,bottom:500});
    document.getElementById = () => dialog;
    document.querySelectorAll = () => forms;
    const HTMLFormElement = {prototype: {submit() {this.submissions++;}}};
    const run = () => vm.runInNewContext(source, {document, window, HTMLFormElement});
    run();
    const open = (index = 0) => fire(forms[index], 'submit', {submitter: forms[index].trigger});
    return {document, dialog, cancel, confirm, feedback, window, forms, fire, open, run};
}

test('receipt opens the custom dialog, focuses Cancel, and sends no request', () => {
    const h = harness();
    assert.ok(h.forms.every(f => !f.trigger.disabled));
    assert.equal(h.open().prevented, true);
    assert.equal(h.dialog.open, true);
    assert.equal(h.document.activeElement, h.cancel);
    assert.ok(h.forms.every(f => f.submissions === 0));
    assert.doesNotMatch(source, /window\.confirm|\bfetch\(/);
});

test('Cancel, Escape and backdrop dismiss without changes and restore focus', () => {
    const h = harness();
    h.open(); h.fire(h.cancel, 'click');
    assert.equal(h.dialog.open, false); assert.equal(h.document.activeElement, h.forms[0].trigger);
    h.open(1); assert.equal(h.fire(h.dialog, 'cancel').prevented, true);
    assert.equal(h.dialog.open, false); assert.equal(h.document.activeElement, h.forms[1].trigger);
    h.open(); h.fire(h.dialog, 'click', {target:h.dialog,clientX:150,clientY:150});
    assert.equal(h.dialog.open, true);
    h.fire(h.dialog, 'click', {target:h.dialog,clientX:10,clientY:10});
    assert.equal(h.dialog.open, false);
    h.fire(h.confirm, 'click'); assert.ok(h.forms.every(f => f.submissions === 0));
});

test('Tab and Shift+Tab remain inside the dialog', () => {
    const h = harness(); h.open();
    h.confirm.focus();
    assert.equal(h.fire(h.dialog, 'keydown', {key:'Tab'}).prevented, true);
    assert.equal(h.document.activeElement, h.cancel);
    assert.equal(h.fire(h.dialog, 'keydown', {key:'Tab',shiftKey:true}).prevented, true);
    assert.equal(h.document.activeElement, h.confirm);
});

test('Confirm submits the selected original form exactly once with its CSRF and status', () => {
    const h = harness(); h.open(1);
    h.open(0); // Another card cannot replace the pending purchase while open.
    h.fire(h.confirm, 'click'); h.fire(h.confirm, 'click'); h.open(1);
    assert.equal(h.forms[0].submissions, 0); assert.equal(h.forms[1].submissions, 1);
    assert.deepEqual(h.forms[1].fields, {_token:'csrf-BETA',status:'completed'});
    assert.equal(h.forms[1].action, '/orders/BETA/status');
    assert.ok(h.cancel.disabled && h.confirm.disabled && h.forms.every(f => f.trigger.disabled));
    h.fire(h.dialog, 'cancel'); assert.equal(h.dialog.open, true);
    assert.match(h.feedback.textContent, /Confirming receipt/);
});

test('cancel then open another card selects that card; back navigation resets pending state', () => {
    const h = harness(); h.open(0); h.fire(h.cancel, 'click'); h.open(1); h.fire(h.confirm, 'click');
    h.fire(h.window, 'pageshow');
    assert.equal(h.dialog.open, false);
    assert.ok(!h.cancel.disabled && !h.confirm.disabled && h.forms.every(f => !f.trigger.disabled));
    h.fire(h.confirm, 'click'); assert.equal(h.forms[1].submissions, 1);
    h.open(0); h.fire(h.confirm, 'click'); assert.equal(h.forms[0].submissions, 1);
});

test('initialization is idempotent and modal sizing supports narrow screens', () => {
    const h = harness(); h.run();
    assert.equal(h.forms[0].handlers.submit.length, 1);
    const markup = partial('receipt-dialog');
    assert.match(markup, /aria-labelledby="buyerReceiptTitle" aria-describedby="buyerReceiptMessage"/);
    assert.match(markup, /width:min\(94vw,440px\)/);
    assert.match(markup, /@media\(max-width:420px\)/);
});

test('an image load failure hides the broken image and exposes the contained fallback', () => {
    const markup = partial('purchase-thumbnail');
    const handler = markup.match(/onerror="([^"]+)"/)[1];
    const img = {hidden:false, previousElementSibling:{hidden:true}};
    vm.runInNewContext(`(function(){${handler}}).call(img)`, {img});
    assert.equal(img.hidden, true); assert.equal(img.previousElementSibling.hidden, false);
    assert.match(markup, /overflow-hidden/); assert.match(markup, /object-cover/);
    assert.match(markup, /\[hidden\]\{display:none!important\}/);
});
