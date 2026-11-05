const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/app.js'), 'utf8').replace(/^import .*;\r?\n/gm, '');

function page({mobile = false, role = 'rider', saved = 'dark'} = {}) {
    const make = () => {
        const classes = new Set();
        return {dataset: {}, handlers: {}, attributes: {}, disabled: false, offsetParent: {},
            addEventListener(type, fn) {(this.handlers[type] ??= []).push(fn);},
            setAttribute(key, value) {this.attributes[key] = value;}, focus() {document.activeElement = this;},
            classList: {add(key) {classes.add(key);}, remove(key) {classes.delete(key);}, contains: key => classes.has(key),
                toggle(key, force) {const add = force === undefined ? !classes.has(key) : force; if (add) classes.add(key); else classes.delete(key); return add;}}};
    };
    const document = {handlers: {}, addEventListener(type, fn) {(this.handlers[type] ??= []).push(fn);}};
    const body = make(), root = make(), main = make(), sidebar = make(), overlay = make(), open = make(), close = make();
    const cancel = make(), confirm = make(), theme = make(), dialog = make(); dialog.open = false;
    const fire = (target, type, values = {}) => {
        const event = {type, defaultPrevented: false, preventDefault() {this.defaultPrevented = true;}, ...values};
        for (const fn of target.handlers[type] ?? []) fn(event);
        return event;
    };
    dialog.showModal = () => {dialog.open = true;}; dialog.close = () => {dialog.open = false;};
    dialog.querySelector = selector => selector.includes('cancel') ? cancel : confirm;
    sidebar.querySelectorAll = () => [open, close];
    const ids = role === 'rider'
        ? {riderSidebar: sidebar, riderSidebarOverlay: overlay, riderSidebarOpen: open, riderSidebarCollapse: close}
        : {logisticsSidebar: sidebar, logisticsSidebarOverlay: overlay, openLogisticsSidebar: open, closeLogisticsSidebar: close};
    Object.assign(document, {body, documentElement: root, activeElement: open,
        getElementById: id => ids[id] ?? null,
        querySelectorAll: selector => selector === '[data-suki-theme-toggle]' ? [theme] : [],
        querySelector: selector => selector === '[data-suki-logout-dialog]' ? dialog : selector === 'dialog[open]' ? (dialog.open ? dialog : null) : main});
    const window = make(), media = {matches: mobile}; window.matchMedia = () => media;
    const writes = []; const localStorage = {getItem: () => saved, setItem(key, value) {saved = value; writes.push({key, value});}};
    class Form {
        constructor(kind = 'buyer') {this.kind = kind; this.method = 'POST'; this.action = '/logout'; this.csrf = 'original-token'; this.button = make(); this.submissions = 0;}
        matches() {return this.kind !== 'delivery';} querySelector() {return this.button;}
        submit() {this.submissions++;}
    }
    vm.runInNewContext(source, {document, window, localStorage, HTMLFormElement: Form, Alpine: {start() {}}});
    fire(document, 'DOMContentLoaded');
    return {document, window, sidebar, main, overlay, open, close, dialog, cancel, confirm, theme, root, body, media, writes, Form, fire};
}

test('dedicated delivery or already-handled submit never also opens logout confirmation', () => {
    const h = page(), form = new h.Form('delivery');
    h.fire(h.document, 'submit', {target: form}); assert.equal(h.dialog.open, false);
    h.fire(h.document, 'submit', {target: new h.Form(), defaultPrevented: true}); assert.equal(h.dialog.open, false);
});

test('shared Buyer logout cancels with Escape, restores focus and retains original POST and token', () => {
    const h = page(), form = new h.Form();
    const event = h.fire(h.document, 'submit', {target: form, submitter: form.button});
    assert.equal(event.defaultPrevented, true); assert.equal(h.dialog.open, true);
    assert.equal(h.document.activeElement, h.cancel);
    const cancel = h.fire(h.dialog, 'cancel'); assert.equal(cancel.defaultPrevented, true);
    assert.equal(h.dialog.open, false); assert.equal(h.document.activeElement, form.button); assert.equal(form.submissions, 0);
    assert.equal(form.method, 'POST'); assert.equal(form.action, '/logout'); assert.equal(form.csrf, 'original-token');
});

test('Seller-compatible shared logout confirms exactly once and traps its own focus', () => {
    const h = page(), form = new h.Form('seller');
    h.fire(h.document, 'submit', {target: form, submitter: form.button});
    h.confirm.focus(); const tab = h.fire(h.dialog, 'keydown', {key: 'Tab'});
    assert.equal(tab.defaultPrevented, true); assert.equal(h.document.activeElement, h.cancel);
    h.fire(h.confirm, 'click'); h.fire(h.confirm, 'click'); h.fire(h.cancel, 'click');
    assert.equal(form.submissions, 1); assert.equal(h.confirm.disabled, true); assert.equal(h.cancel.disabled, true);
});

test('shared theme initializes persisted dark mode and toggles once with accessible labels', () => {
    const h = page(); assert.equal(h.root.dataset.theme, 'dark'); assert.equal(h.body.dataset.theme, 'dark');
    assert.equal(h.theme.attributes['aria-pressed'], 'true'); h.fire(h.theme, 'click');
    assert.equal(h.root.dataset.theme, 'light'); assert.equal(h.theme.attributes['aria-label'], 'Switch to dark mode');
    assert.deepEqual(h.writes, [{key: 'suki-landing-theme', value: 'light'}]);
});

for (const role of ['rider', 'logistics']) {
    test(`${role} mobile sidebar owns visibility/inert state and yields Escape to logout dialog`, () => {
        const h = page({mobile: true, role});
        assert.equal(h.sidebar.inert, true); assert.equal(h.sidebar.classList.contains('-translate-x-full'), true);
        assert.equal(h.open.handlers.click.length, 1);
        h.fire(h.open, 'click'); assert.equal(h.sidebar.inert, false); assert.equal(h.main.inert, true);
        assert.equal(h.sidebar.classList.contains('-translate-x-full'), false); assert.equal(h.overlay.hidden, false);
        const form = new h.Form(); h.fire(h.document, 'submit', {target: form, submitter: form.button});
        const escape = h.fire(h.document, 'keydown', {key: 'Escape'});
        assert.equal(escape.defaultPrevented, false); assert.equal(h.main.inert, true);
        h.fire(h.dialog, 'cancel'); assert.equal(h.dialog.open, false);
        h.fire(h.document, 'keydown', {key: 'Escape'});
        assert.equal(h.main.inert, false); assert.equal(h.sidebar.inert, true); assert.equal(h.overlay.hidden, true);
        h.media.matches = false; h.fire(h.window, 'resize'); assert.equal(h.sidebar.inert, false);
        h.fire(h.close, 'click'); assert.equal(h.body.classList.contains(`${role}-sidebar-collapsed`), true);
    });
}

test('all sidebar focus traps yield to native dialogs and legacy duplicate Rider/Logistics handlers are removed', () => {
    const base = path.join(__dirname, '../../resources/views');
    for (const file of ['layouts/seller.blade.php', 'admin/layout.blade.php']) {
        assert.match(fs.readFileSync(path.join(base, file), 'utf8'), /if \(document\.querySelector\('dialog\[open\]'\)\) return;/);
    }
    for (const role of ['rider', 'logistics']) {
        const layout = fs.readFileSync(path.join(base, `layouts/${role}.blade.php`), 'utf8');
        assert.doesNotMatch(layout, /function openSidebar\(/);
        assert.equal((layout.match(/@include\('components.logout-confirmation'\)/g) ?? []).length, 1);
    }
});
