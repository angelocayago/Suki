const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/logistics/pickup-assignment-script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const make = () => ({dataset: {}, handlers: {}, textContent: '', disabled: false, focused: false,
        addEventListener(type, fn) {(this.handlers[type] ??= []).push(fn);},
        focus() {this.focused = true;}});
    const fields = Object.fromEntries(['pickupAssignmentDialog', 'pickupAssignmentConfirm', 'pickupAssignmentCancel', 'pickupAssignmentOrder', 'pickupAssignmentRider', 'pickupAssignmentLocation'].map(id => [id, make()]));
    const dialog = fields.pickupAssignmentDialog;
    dialog.open = false; dialog.opens = 0;
    dialog.showModal = () => {dialog.open = true; dialog.opens++;};
    dialog.close = () => {dialog.open = false; (dialog.handlers.close ?? []).forEach(fn => fn());};
    const forms = [41, 92].map(id => {
        const form = make(); form.dataset = {order: 'ORDER-' + id, pickupLocation: 'Verified pickup ' + id};
        form.rider = {value: String(id + 100), selectedOptions: [{textContent: 'Rider ' + id}]};
        form.elements = {namedItem: name => name === 'pickup_rider_id' ? form.rider : null};
        form.valid = true; form.reportValidity = () => form.valid && !!form.rider.value;
        form.button = make(); form.querySelectorAll = () => [form.button]; form.submissions = 0;
        form.action = '/logistics/erp-orders/' + id + '/pickup-rider'; form.csrf = 'existing-token';
        return form;
    });
    const context = {document: {getElementById: id => fields[id], querySelectorAll: () => forms, activeElement: forms[0].button},
        HTMLFormElement: {prototype: {submit() {this.submissions++; this.submittedRider = this.rider.value;}}}};
    const run = () => vm.runInNewContext(source, context); run();
    function fire(target, type, overrides = {}) {
        const event = {preventDefault() {this.prevented = true;}, ...overrides};
        for (const fn of target.handlers[type] ?? []) fn(event);
        return event;
    }
    return {fields, forms, dialog, fire, run};
}

test('opens with exact selected parcel, rider and pickup location using text content', () => {
    const h = harness(), form = h.forms[1];
    form.rider.selectedOptions[0].textContent = '<script>not markup</script>';
    assert.equal(h.fire(form, 'submit', {submitter: form.button}).prevented, true);
    assert.equal(h.dialog.open, true);
    assert.equal(h.fields.pickupAssignmentOrder.textContent, 'ORDER-92');
    assert.equal(h.fields.pickupAssignmentRider.textContent, '<script>not markup</script>');
    assert.equal(h.fields.pickupAssignmentLocation.textContent, 'Verified pickup 92');
    assert.equal(form.submissions, 0);
});

test('cancel and native Escape close restore focus and never submit', () => {
    const h = harness(), form = h.forms[0];
    h.fire(form, 'submit', {submitter: form.button});
    h.fire(h.fields.pickupAssignmentCancel, 'click');
    assert.equal(form.button.focused, true); assert.equal(form.submissions, 0);
    h.fire(form, 'submit', {submitter: form.button});
    h.dialog.close(); // Native dialog Escape default action.
    h.fire(h.fields.pickupAssignmentConfirm, 'click');
    assert.equal(form.submissions, 0);
});

test('missing rider or invalid form cannot open confirmation', () => {
    const h = harness(), form = h.forms[0];
    form.rider.value = ''; h.fire(form, 'submit');
    assert.equal(h.dialog.open, false);
    form.rider.value = '141'; form.valid = false; h.fire(form, 'submit');
    assert.equal(h.dialog.open, false); assert.equal(form.submissions, 0);
});

test('confirmation submits the existing form once, retaining action, token and Rider ID', () => {
    const h = harness(), form = h.forms[1]; h.run();
    h.fire(form, 'submit', {submitter: form.button});
    h.fire(h.fields.pickupAssignmentConfirm, 'click'); h.fire(h.fields.pickupAssignmentConfirm, 'click');
    h.fire(form, 'submit');
    assert.equal(form.submissions, 1); assert.equal(form.submittedRider, '192');
    assert.equal(form.action, '/logistics/erp-orders/92/pickup-rider'); assert.equal(form.csrf, 'existing-token');
    assert.equal(h.forms[0].submissions, 0); assert.equal(h.fields.pickupAssignmentConfirm.disabled, true);
    assert.equal(form.button.disabled, true); assert.equal(h.dialog.opens, 1);
});

test('reopening for another parcel replaces prior selection and missing location has an honest fallback', () => {
    const h = harness(); h.fire(h.forms[0], 'submit'); h.dialog.close();
    h.forms[1].dataset.pickupLocation = ''; h.fire(h.forms[1], 'submit');
    assert.equal(h.fields.pickupAssignmentLocation.textContent, 'Pickup address unavailable');
    h.fire(h.fields.pickupAssignmentConfirm, 'click');
    assert.equal(h.forms[0].submissions, 0); assert.equal(h.forms[1].submissions, 1);
});
