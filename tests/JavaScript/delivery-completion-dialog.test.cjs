const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const read = name => fs.readFileSync(path.join(__dirname, '../../resources/views/rider/' + name + '.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const make = () => ({dataset: {}, handlers: {}, attributes: {}, textContent: '', hidden: false, disabled: false,
        addEventListener(type, fn) {(this.handlers[type] ??= []).push(fn);},
        dispatchEvent(event) {(this.handlers[event.type] ?? []).forEach(fn => fn(event));},
        setAttribute(key, value) {this.attributes[key] = value;}, removeAttribute(key) {delete this[key];},
        focus() {this.focused = true;}, click() {}});
    const fields = Object.fromEntries(['Dialog', 'Confirm', 'Cancel', 'Order', 'Buyer', 'Recipient', 'Amount', 'Cod', 'Proof'].map(key => ['deliveryCompletion' + key, make()]));
    const forms = [41, 92].map(id => {
        const form = make(), panel = make();
        panel.fields = Object.fromEntries(['input', 'add', 'selected', 'preview', 'filename', 'error', 'replace', 'remove'].map(key => [key, make()]));
        panel.querySelector = selector => panel.fields[selector.match(/data-proof-(.+)\]/)[1]];
        const proof = panel.fields.input; proof.files = [];
        Object.defineProperty(proof, 'value', {set(value) {if (value === '') this.files = [];}});
        form.panel = panel;
        form.data = {proof_photo: proof, received_by: {value: 'Recipient ' + id}, delivery_note: {value: 'Note ' + id}, cod_collected: {checked: true}};
        form.dataset = {order: 'ORDER-' + id, buyer: 'Buyer ' + id, codRequired: 'true', codAmount: '₱950.00'};
        form.elements = {namedItem: name => form.data[name]};
        form.reportValidity = () => {
            if (!proof.files.length) {proof.dispatchEvent({type: 'invalid', preventDefault() {}}); return false;}
            return !!form.data.received_by.value && (form.dataset.codRequired !== 'true' || form.data.cod_collected.checked);
        };
        form.button = make(); form.querySelectorAll = () => [form.button]; form.submissions = 0;
        form.action = '/rider/erp-orders/' + id + '/status'; form.enctype = 'multipart/form-data'; form.token = 'existing-csrf';
        return form;
    });
    const dialog = fields.deliveryCompletionDialog; dialog.open = false;
    dialog.showModal = () => {dialog.open = true;};
    dialog.close = () => {dialog.open = false; dialog.dispatchEvent({type: 'close'});};
    const context = {document: {getElementById: id => fields[id], querySelectorAll: selector => selector === '[data-delivery-proof]' ? forms.map(f => f.panel) : forms, activeElement: forms[0].button},
        window: make(), Event: function(type) {this.type = type;}, URL: {createObjectURL: () => 'blob:preview', revokeObjectURL() {}},
        HTMLFormElement: {prototype: {submit() {this.submissions++; this.submittedFile = this.data.proof_photo.files[0];}}}};
    vm.runInNewContext(read('delivery-proof-upload-script'), context);
    const run = () => vm.runInNewContext(read('delivery-completion-script'), context); run();
    function fire(target, type) {
        const event = {type, submitter: target.button, preventDefault() {this.prevented = true;}};
        target.dispatchEvent(event); return event;
    }
    function choose(index, file = {name: 'proof.jpg', type: 'image/jpeg', size: 1024}) {
        forms[index].data.proof_photo.files = [file]; return file;
    }
    return {fields, forms, dialog, fire, choose, run};
}

test('validated delivery opens with canonical COD, recipient and photo; no native confirm is invoked', () => {
    const h = harness(); h.choose(0); h.fire(h.forms[0], 'submit');
    assert.equal(h.dialog.open, true); assert.equal(h.forms[0].submissions, 0);
    assert.equal(h.fields.deliveryCompletionOrder.textContent, 'ORDER-41');
    assert.equal(h.fields.deliveryCompletionRecipient.textContent, 'Recipient 41');
    assert.equal(h.fields.deliveryCompletionAmount.textContent, '₱950.00');
    assert.match(h.fields.deliveryCompletionCod.textContent, /Required.*confirmed collection/);
    assert.equal(h.fields.deliveryCompletionProof.textContent, 'Photo selected: proof.jpg');
});

test('existing proof validation rejects missing, invalid and oversized photos before modal opens', () => {
    for (const file of [null, {name: 'bad.jpg', type: 'text/html', size: 10}, {name: 'big.png', type: 'image/png', size: 5242881}]) {
        const h = harness(); if (file) h.choose(0, file); h.fire(h.forms[0], 'submit');
        assert.equal(h.dialog.open, false); assert.equal(h.forms[0].submissions, 0);
        assert.equal(h.forms[0].panel.fields.error.hidden, false);
        assert.equal(h.forms[0].data.proof_photo.files.length, 0);
    }
});

test('required COD collection and recipient validation prevent confirmation', () => {
    const h = harness(); h.choose(0); h.forms[0].data.cod_collected.checked = false; h.fire(h.forms[0], 'submit');
    assert.equal(h.dialog.open, false);
    h.forms[0].data.cod_collected.checked = true; h.forms[0].data.received_by.value = ''; h.fire(h.forms[0], 'submit');
    assert.equal(h.dialog.open, false);
});

test('Cancel and native Escape dismissal preserve file and restore trigger focus without submission', () => {
    const h = harness(), file = h.choose(0); h.fire(h.forms[0], 'submit'); h.fire(h.fields.deliveryCompletionCancel, 'click');
    assert.equal(h.forms[0].button.focused, true); assert.equal(h.forms[0].data.proof_photo.files[0], file);
    h.fire(h.forms[0], 'submit'); h.dialog.close(); // Native Escape closes dialog by default.
    h.fire(h.fields.deliveryCompletionConfirm, 'click'); assert.equal(h.forms[0].submissions, 0);
});

test('Confirm submits original multipart form once, retaining the exact File and all field values', () => {
    const h = harness(), form = h.forms[1], file = h.choose(1); h.run();
    h.fire(form, 'submit'); h.fire(h.fields.deliveryCompletionConfirm, 'click');
    h.fire(h.fields.deliveryCompletionConfirm, 'click'); h.fire(form, 'submit');
    assert.equal(form.submissions, 1); assert.equal(form.submittedFile, file);
    assert.equal(form.action, '/rider/erp-orders/92/status'); assert.equal(form.enctype, 'multipart/form-data'); assert.equal(form.token, 'existing-csrf');
    assert.equal(form.data.received_by.value, 'Recipient 92'); assert.equal(form.data.delivery_note.value, 'Note 92'); assert.equal(form.data.cod_collected.checked, true);
    assert.equal(form.button.disabled, true); assert.equal(h.fields.deliveryCompletionConfirm.disabled, true);
    assert.equal(h.forms[0].submissions, 0);
});

test('multiple cards stay independent and reopening shows the new selection', () => {
    const h = harness(); const first = h.choose(0); h.choose(1);
    h.fire(h.forms[0], 'submit'); h.dialog.close(); h.fire(h.forms[1], 'submit');
    assert.equal(h.fields.deliveryCompletionOrder.textContent, 'ORDER-92');
    h.fire(h.fields.deliveryCompletionConfirm, 'click');
    assert.equal(h.forms[0].data.proof_photo.files[0], first); assert.equal(h.forms[0].submissions, 0); assert.equal(h.forms[1].submissions, 1);
});

test('confirmation revalidates and refuses a file or COD confirmation removed after opening', () => {
    const h = harness(); h.choose(0); h.fire(h.forms[0], 'submit'); h.forms[0].data.cod_collected.checked = false;
    h.fire(h.fields.deliveryCompletionConfirm, 'click'); assert.equal(h.forms[0].submissions, 0); assert.equal(h.dialog.open, false);
});

test('non-COD tasks explicitly state cash collection is not required', () => {
    const h = harness(); h.choose(0); h.forms[0].dataset.codRequired = 'false'; h.forms[0].dataset.codAmount = '₱0.00'; h.forms[0].data.cod_collected.checked = false;
    h.fire(h.forms[0], 'submit'); assert.equal(h.dialog.open, true); assert.equal(h.fields.deliveryCompletionCod.textContent, 'Not required');
});
