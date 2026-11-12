const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/shipments/scanner.blade.php'), 'utf8');
let initializeShipmentScanner;
const scannerModule = import('../../resources/js/shipment-scanner.js').then(module => { initializeShipmentScanner = module.initializeShipmentScanner; });

function element() {
    const classes = new Set();
    return {
        dataset: {}, handlers: {}, attributes: {}, textContent: '', innerHTML: '', value: '', disabled: false,
        readyState: 4, videoWidth: 640, videoHeight: 480, srcObject: null, hidden: false, submissions: 0, tracks: [],
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

function harness({barcodeDetector = true, supportedFormats = ['qr_code'], getUserMedia, qrDecoder, canvasSupported = true, initialState = 'idle'} = {}) {
    const ids = ['start-camera', 'stop-camera', 'scanner-video', 'camera-preview', 'scanner-target', 'scanner-scan-line', 'qr-detection-overlay', 'qr-detection-polygon', 'camera-status', 'camera-state-icon', 'camera-spinner', 'camera-status-title', 'camera-message', 'shipment-resolve-form', 'resolve-shipment', 'scan-payload', 'qr-payload'];
    const fields = Object.fromEntries(ids.map(id => [id, element()]));
    fields['camera-status'].dataset.state = initialState;
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
        static supportedFormats = supportedFormats;
        static async getSupportedFormats() { return this.supportedFormats; }
        constructor(options) { this.options = options; }
        async detect() { return Detector.result; }
    }
    const documentRef = {
        getElementById: id => fields[id] ?? null,
        createElement(name) {
            assert.equal(name, 'canvas');
            return {width: 0, height: 0, getContext: () => canvasSupported ? {
                drawImage() {}, getImageData() { return {data: new Uint8ClampedArray(640 * 480 * 4)}; },
            } : null};
        },
    };
    const windowRef = {isSecureContext: true, addEventListener(type, callback) { (windowHandlers[type] ??= []).push(callback); }, lucide: {createIcons() {}}, ...(barcodeDetector ? {BarcodeDetector: Detector} : {})};
    const navigatorRef = {mediaDevices: {getUserMedia: getUserMedia ?? (async () => ({getTracks: () => [track]}))}};
    const requestFrame = callback => { const id = ++frameId; frames.set(id, callback); return id; };
    const cancelFrame = id => frames.delete(id);
    const setTimer = callback => { const id = ++timerId; timers.set(id, callback); return id; };
    const clearTimer = id => timers.delete(id);

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
    return scannerModule.then(() => {
        initializeShipmentScanner({documentRef, windowRef, navigatorRef, qrDecoder, requestFrame, cancelFrame, setTimer, clearTimer});
        return {
        fields, windowRef, track, frames, timers, fire, fireSync,
        async runFrame(timestamp = 1000) { const next = frames.entries().next().value; assert.ok(next, 'expected a scheduled animation frame'); frames.delete(next[0]); await next[1](timestamp); return next[1]; },
        runTimer() { const next = timers.entries().next().value; assert.ok(next, 'expected a scheduled timer'); timers.delete(next[0]); next[1](); },
        fireWindow(type) { for (const callback of windowHandlers[type] ?? []) callback(); },
        };
    });
}

test('scanner renders camera controls and accessible live state for shared roles', async () => {
    await scannerModule;
    for (const hook of ['id="start-camera"', 'id="stop-camera"', 'id="scanner-video"', 'id="camera-message"', 'id="shipment-resolve-form"', 'id="qr-detection-overlay"', 'id="qr-detection-polygon"', 'id="scanner-scan-line"', 'id="qr-payload" type="hidden" name="payload"', 'id="scan-payload" name="tracking_code"', "@vite('resources/js/shipment-scanner.js')", 'aria-live="polite"']) {
        assert.ok(blade.includes(hook), `missing ${hook}`);
    }
    assert.ok(blade.includes("$actorType === 'logistics' ? 'layouts.logistics' : 'layouts.rider'"));
    assert.ok(blade.includes("$shipmentVerified ? 'Shipment verified' : 'Ready to scan'"));
});

test('a server-rendered verified result initializes with success and no stale verifying spinner', async () => {
    const h = await harness({initialState: 'verified'});
    assert.equal(h.fields['camera-status-title'].textContent, 'Shipment verified');
    assert.equal(h.fields['camera-message'].textContent, 'QR verified successfully. Review the shipment details below.');
    assert.equal(h.fields['camera-spinner'].classList.contains('hidden'), true);
    assert.equal(h.fields['camera-state-icon'].classList.contains('hidden'), false);
    assert.match(h.fields['camera-state-icon'].innerHTML, /data-lucide="check"/);
});

test('start immediately shows loading, then live preview and scanning state', async () => {
    let allowCamera;
    const h = await harness({getUserMedia: () => new Promise(resolve => { allowCamera = resolve; })});
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

test('a detected QR fills only payload, shows check and verification states, and submits once', async () => {
    const h = await harness();
    await h.fire(h.fields['start-camera'], 'click');
    h.windowRef.BarcodeDetector.result = [{rawValue: 'SIGNED-SUKI-QR-PAYLOAD', boundingBox: {x: 10, y: 20, width: 100, height: 80}}];
    const staleFrame = await h.runFrame();
    assert.equal(h.fields['camera-status-title'].textContent, 'QR Code Detected');
    assert.equal(h.fields['qr-payload'].value, 'SIGNED-SUKI-QR-PAYLOAD');
    assert.equal(h.fields['scan-payload'].value, '', 'signed QR data must not appear in the manual tracking textbox');
    assert.match(h.fields['camera-state-icon'].innerHTML, /data-lucide="check"/);
    assert.equal(h.fields['qr-detection-overlay'].attributes.viewBox, '0 0 640 480');
    assert.equal(h.fields['qr-detection-polygon'].attributes.points, '10,20 110,20 110,100 10,100');
    assert.equal(h.track.stops, 1, 'the camera track stops immediately once a QR is detected');
    await staleFrame(1500);
    h.runTimer();
    assert.equal(h.fields['camera-status-title'].textContent, 'Verifying shipment...');
    assert.equal(h.fields['camera-spinner'].classList.contains('hidden'), false);
    assert.equal(h.track.stops, 1);
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
    h.fields['shipment-resolve-form'].requestSubmit();
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
});

test('Stop Camera stops tracks, hides preview, preserves manual value, and allows retry', async () => {
    const h = await harness();
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
    let attempts = 0;
    const h = await harness({getUserMedia: async () => {
        attempts++;
        if (attempts === 1) throw denied;
        return {getTracks: () => [h.track]};
    }});
    await h.fire(h.fields['start-camera'], 'click');
    assert.match(h.fields['camera-message'].textContent, /Camera permission was denied/);
    assert.equal(h.fields['camera-status-title'].textContent, 'Camera could not start');
    assert.equal(h.fields['start-camera'].disabled, false);
    await h.fire(h.fields['start-camera'], 'click');
    assert.equal(h.fields['camera-status-title'].textContent, 'Scanning QR code...');
});

test('jsQR fallback decodes camera frames and draws the detected QR location', async () => {
    let calls = 0;
    let returnQr = false;
    const h = await harness({barcodeDetector: false, qrDecoder: () => {
        calls++;
        if (!returnQr) return null;
        return {data: 'JSQR-PAYLOAD', location: {
            topLeftCorner: {x: 30, y: 40}, topRightCorner: {x: 130, y: 40},
            bottomRightCorner: {x: 130, y: 140}, bottomLeftCorner: {x: 30, y: 140},
        }};
    }});
    await h.fire(h.fields['start-camera'], 'click');
    assert.equal(h.fields['scanner-video'].classList.contains('hidden'), false);
    assert.equal(h.fields['camera-status-title'].textContent, 'Scanning QR code...');
    assert.equal(h.fields['stop-camera'].classList.contains('hidden'), false);
    await h.runFrame();
    assert.equal(calls, 1);
    await h.runFrame(1100);
    assert.equal(calls, 1, 'fallback decoding is throttled between scan intervals');
    returnQr = true;
    await h.runFrame(1200);
    assert.equal(calls, 2);
    assert.equal(h.fields['qr-payload'].value, 'JSQR-PAYLOAD');
    assert.equal(h.fields['scan-payload'].value, '', 'signed QR data must not appear in the manual tracking textbox');
    assert.equal(h.fields['camera-status-title'].textContent, 'QR Code Detected');
    assert.equal(h.fields['qr-detection-polygon'].attributes.points, '30,40 130,40 130,140 30,140');
    h.runTimer();
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
    assert.equal(h.track.stops, 1);
});

test('native BarcodeDetector without QR support falls back to local decoder', async () => {
    let fallbackCalls = 0;
    const h = await harness({supportedFormats: ['code_128'], qrDecoder: () => { fallbackCalls++; return null; }});
    await h.fire(h.fields['start-camera'], 'click');
    assert.equal(h.fields['camera-status-title'].textContent, 'Scanning QR code...');
    await h.runFrame();
    assert.equal(fallbackCalls, 1);
});

test('fallback decoder initialization errors show retry and preserve manual lookup', async () => {
    const h = await harness({barcodeDetector: false, canvasSupported: false});
    await h.fire(h.fields['start-camera'], 'click');
    assert.equal(h.fields['camera-status-title'].textContent, 'QR scanner could not start');
    assert.equal(h.fields['start-camera'].textContent, 'Retry Camera');
    assert.equal(h.fields['start-camera'].disabled, false);
    h.fields['scan-payload'].value = 'MANUAL-CODE';
    h.fields['shipment-resolve-form'].requestSubmit();
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
});

test('leaving the page stops all camera tracks', async () => {
    const h = await harness();
    await h.fire(h.fields['start-camera'], 'click');
    h.fireWindow('pagehide');
    assert.equal(h.track.stops, 1);
    assert.equal(h.fields['scanner-video'].srcObject, null);
});

test('manual Find Shipment stops an active camera before submitting', async () => {
    const h = await harness();
    await h.fire(h.fields['start-camera'], 'click');
    h.fields['scan-payload'].value = 'MANUAL-TRACKING-CODE';
    h.fields['shipment-resolve-form'].requestSubmit();
    assert.equal(h.track.stops, 1);
    assert.equal(h.fields['scanner-video'].srcObject, null);
    assert.equal(h.fields['camera-status-title'].textContent, 'Verifying shipment...');
    assert.equal(h.fields['shipment-resolve-form'].submissions, 1);
});
