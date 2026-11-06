const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/shipments/scanner.blade.php'), 'utf8');
const source = blade.match(/<script>([\s\S]*?)<\/script>/)[1];

function element() {
    const classes = new Set();
    return {
        dataset: {}, handlers: {}, attributes: {}, textContent: '', innerHTML: '', value: '', disabled: false,
        readyState: 4, srcObject: null, hidden: false, submissions: 0, tracks: [],
        classList: {
            add(...names) { names.forEach(name => classes.add(name)); },
            remove(...names) { names.forEach(name => classes.delete(name)); },
            contains(name) { return classes.has(name); },
            toggle(name, force) { if (force === undefined ? !classes.has(name) : force) classes.add(name); else classes.delete(name); return classes.has(name); },
        },
        addEventListener(type, callback) { (this.handlers[type] ??= []).push(callback); },
        setAttribute(name, value) { this.attributes[name] = value; },
        pause() { this.paused = true; },
        async play() { this.played = true; },
    };
}

function harness({barcodeDetector = true, getUserMedia} = {}) {
    const ids = ['start-camera', 'stop-camera', 'scanner-video', 'camera-preview', 'scanner-target', 'camera-status', 'camera-state-icon', 'camera-spinner', 'camera-status-title', 'camera-message', 'shipment-resolve-form', 'resolve-shipment', 'scan-payload'];
    const fields = Object.fromEntries(ids.map(id => [id, element()]));
    const frames = new Map();
    const timers = new Map();
    const windowHandlers = {};
    let frameId = 0, timerId = 0;
    const track = {stops: 0, stop() { this.stops++; }};
    fields['scanner-video'].tracks = [track];
    fields['shipment-resolve-form'].requestSubmit = () => {
        const event = fireSync(fields['shipment-resolve-form'], 'submit');
        if (!event.defaultPrevented) fields['shipment-resolve-form'].submissions++;
    };

    class Detector {
        static result = [];
        constructor(options) { this.options = options; }
        async detect() { return Detector.result; }
    }
    const document = {getElementById: id => fields[id] ?? null};
    const context = {
        document,
        navigator: {mediaDevices: {getUserMedia: getUserMedia ?? (async () => ({getTracks: () => [track]}))}},
        window: {isSecureContext: true, addEventListener(type, callback) { (windowHandlers[type] ??= []).push(callback); }, lucide: {createIcons() {}}, ...(barcodeDetector ? {BarcodeDetector: Detector} : {})},
        requestAnimationFrame(callback) { const id = ++frameId; frames.set(id, callback); return id; },
        cancelAnimationFrame(id) { frames.delete(id); },
        setTimeout(callback) { const id = ++timerId; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
        HTMLFormElement: {prototype: {submit() { fields['shipment-resolve-form'].submissions++; }}},
    };

    function fireSync(target, type) {
        const event = {defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }};
        for (const callback of target.handlers[type] ?? []) callback(event);
        return event;
    }
    async function fire(target, type) {
        const event = {defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }};
        for (const callback of target.handlers[type] ?? []) await callback(event);
        return event;
    }
    vm.runInNewContext(source, context);

    return {
        fields, context, track, frames, timers, fire, fireSync,
        async runFrame() { const next = frames.entries().next().value; assert.ok(next, 'expected a scheduled animation frame'); frames.delete(next[0]); await next[1](); },
        runTimer() { const next = timers.entries().next().value; assert.ok(next, 'expected a scheduled timer'); timers.delete(next[0]); next[1](); },
        fireWindow(type) { for (const callback of windowHandlers[type] ?? []) callback(); },
    };
}

test('scanner renders camera controls and accessible live state for shared roles', () => {
    for (const hook of ['id="start-camera"', 'id="stop-camera"', 'id="scanner-video"', 'id="camera-message"', 'id="shipment-resolve-form"', 'aria-live="polite"']) {
        assert.ok(blade.includes(hook), `missing ${hook}`);
    }
    assert.ok(blade.includes("$actorType === 'logistics' ? 'layouts.logistics' : 'layouts.rider'"));
});

test('start immediately shows loading, then live preview and scanning state', async () => {
    let allowCamera;
    const h = harness({getUserMedia: () => new Promise(resolve => { allowCamera = resolve; })});
    const starting = h.fire(h.fields['start-camera'], 'click');
    assert.equal(h.fields['start-camera'].disabled, true);
    assert.equal(h.fields['camera-status-title'].textContent, 'Opening camera...');
    assert.equal(h.fields['camera-spinner'].classList.contains('hidden'), false);
    allowCamera({getTracks: () => [h.track]});
    await starting;
    assert.equal(h.fields['scanner-video'].played, true);
    assert.equal(h.fields['scanner-video'].classList.contains('hidden'), false);
    assert.equal(h.fields['camera-preview'].classList.contains('hidden'), false);
    assert.equal(h.fields['camera-status-title'].textContent, 'Scanning QR code...');
    assert.equal(h.fields['stop-camera'].classList.contains('hidden'), false);
});

test('a detected QR shows check and verification states, populates input, and submits once', async () => {
    const h = harness();
    await h.fire(h.fields['start-camera'], 'click');
    h.context.window.BarcodeDetector.result = [{rawValue: 'SIGNED-SUKI-QR-PAYLOAD'}];
    await h.runFrame();
    assert.equal(h.fields['scan-payload'].value, 'SIGNED-SUKI-QR-PAYLOAD');
    assert.equal(h.fields['camera-status-title'].textContent, 'QR Code Detected');
    assert.match(h.fields['camera-state-icon'].innerHTML, /data-lucide="check"/);
    assert.equal(h.track.stops, 1);
    h.runTimer();
    assert.equal(h.fields['camera-status-title'].textContent, 'Verifying shipment...');
    h.runTimer();
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
    h.fields['shipment-resolve-form'].requestSubmit();
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
});

test('Stop Camera stops tracks, hides preview, preserves manual value, and allows retry', async () => {
    const h = harness();
    h.fields['scan-payload'].value = 'KEEP-THIS-MANUAL-CODE';
    await h.fire(h.fields['start-camera'], 'click');
    await h.fire(h.fields['stop-camera'], 'click');
    assert.equal(h.track.stops, 1);
    assert.equal(h.fields['scanner-video'].srcObject, null);
    assert.equal(h.fields['camera-preview'].classList.contains('hidden'), true);
    assert.equal(h.fields['camera-status-title'].textContent, 'Ready to scan');
    assert.equal(h.fields['start-camera'].disabled, false);
    assert.equal(h.fields['scan-payload'].value, 'KEEP-THIS-MANUAL-CODE');
});

test('permission denied is visible and restores Start Camera for retry', async () => {
    const denied = Object.assign(new Error('denied'), {name: 'NotAllowedError'});
    const h = harness({getUserMedia: async () => { throw denied; }});
    await h.fire(h.fields['start-camera'], 'click');
    assert.match(h.fields['camera-message'].textContent, /Camera permission was denied/);
    assert.equal(h.fields['camera-status-title'].textContent, 'Camera could not start');
    assert.equal(h.fields['start-camera'].disabled, false);
});

test('preview remains usable for manual entry without BarcodeDetector', async () => {
    const h = harness({barcodeDetector: false});
    await h.fire(h.fields['start-camera'], 'click');
    assert.equal(h.fields['scanner-video'].classList.contains('hidden'), false);
    assert.match(h.fields['camera-message'].textContent, /Automatic QR recognition is not supported/);
    assert.equal(h.fields['stop-camera'].classList.contains('hidden'), false);
    assert.equal(h.fields['start-camera'].classList.contains('hidden'), true);
    h.fields['scan-payload'].value = 'MANUAL-CODE';
    h.fields['shipment-resolve-form'].requestSubmit();
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
    assert.equal(h.track.stops, 1);
});

test('leaving the page stops all camera tracks', async () => {
    const h = harness();
    await h.fire(h.fields['start-camera'], 'click');
    h.fireWindow('pagehide');
    assert.equal(h.track.stops, 1);
    assert.equal(h.fields['scanner-video'].srcObject, null);
});
