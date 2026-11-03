const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/landing.js'), 'utf8');

function page(authenticated) {
    const handlers = {};
    const theme = {handlers: [], setAttribute(name, value) { this[name] = value; }, addEventListener(type, fn) { this.handlers.push(fn); }};
    const publicLinks = ['/product/notebook', '/shop', '/shop?category=food'].map(href => ({href, handlers: [], addEventListener(type, fn) { this.handlers.push(fn); }}));
    const root = {dataset: {authenticated, loginUrl: '/login'}};
    const saved = new Map();
    const location = {href: '/'};
    const document = {
        body: root,
        addEventListener(type, fn) { (handlers[type] ??= []).push(fn); },
        getElementById(id) { return id === 'landingThemeToggle' ? theme : null; },
        querySelector() { return null; },
        querySelectorAll(selector) {
            if (selector === '.hero-dot' || selector === 'a[href^="#"]') return [];
            return publicLinks;
        },
    };
    vm.runInNewContext(source, {document, window: {location, matchMedia: () => ({matches: false})}, localStorage: {getItem: key => saved.get(key), setItem: (key, value) => saved.set(key, value)}});
    assert.equal(handlers.DOMContentLoaded.length, 1);
    handlers.DOMContentLoaded[0]();
    return {theme, publicLinks, root, saved, location};
}

for (const authenticated of ['0', '1']) {
    test(`public product and shop links remain native navigation (authenticated=${authenticated})`, () => {
        const {publicLinks, location} = page(authenticated);
        for (const link of publicLinks) {
            let prevented = false;
            link.handlers.forEach(fn => fn({preventDefault() { prevented = true; }}));
            assert.equal(prevented, false, link.href);
            assert.equal(link.handlers.length, 0, 'no blanket auth click handler');
        }
        assert.equal(location.href, '/');
    });
}

test('redesigned dark mode initializes once and persists each toggle', () => {
    const {theme, root, saved} = page('0');
    assert.equal(root.dataset.theme, 'light');
    assert.equal(theme.handlers.length, 1);
    theme.handlers[0]();
    assert.equal(root.dataset.theme, 'dark');
    assert.equal(theme['aria-pressed'], 'true');
    assert.equal(saved.get('suki-landing-theme'), 'dark');
    theme.handlers[0]();
    assert.equal(root.dataset.theme, 'light');
});
