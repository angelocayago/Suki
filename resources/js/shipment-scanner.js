import jsQR from 'jsqr';

const SCAN_INTERVAL_MS = 200;
const SUCCESS_HIGHLIGHT_MS = 650;

export function initializeShipmentScanner({
    documentRef = document,
    windowRef = window,
    navigatorRef = navigator,
    qrDecoder = jsQR,
    requestFrame = callback => requestAnimationFrame(callback),
    cancelFrame = id => cancelAnimationFrame(id),
    setTimer = (callback, delay) => setTimeout(callback, delay),
    clearTimer = id => clearTimeout(id),
} = {}) {
    const startButton = documentRef.getElementById('start-camera');
    const stopButton = documentRef.getElementById('stop-camera');
    const video = documentRef.getElementById('scanner-video');
    const preview = documentRef.getElementById('camera-preview');
    const target = documentRef.getElementById('scanner-target');
    const scanLine = documentRef.getElementById('scanner-scan-line');
    const status = documentRef.getElementById('camera-status');
    const statusIcon = documentRef.getElementById('camera-state-icon');
    const spinner = documentRef.getElementById('camera-spinner');
    const title = documentRef.getElementById('camera-status-title');
    const message = documentRef.getElementById('camera-message');
    const form = documentRef.getElementById('shipment-resolve-form');
    const resolveButton = documentRef.getElementById('resolve-shipment');
    const input = documentRef.getElementById('scan-payload');
    const payloadInput = documentRef.getElementById('qr-payload');
    const overlay = documentRef.getElementById('qr-detection-overlay');
    const polygon = documentRef.getElementById('qr-detection-polygon');

    if (!startButton || !stopButton || !video || !form || !input || !payloadInput) return null;

    let stream = null;
    let detector = null;
    let detectionMode = null;
    let animationId = null;
    let isStarting = false;
    let isScanning = false;
    let isDetecting = false;
    let isSubmitting = false;
    let qrDetected = false;
    let pageLeaving = false;
    let nextScanAt = 0;
    let detectDelay = null;
    let canvas = null;
    let canvasContext = null;

    const showIcon = (name) => {
        statusIcon.innerHTML = `<i data-lucide="${name}" class="h-4 w-4" aria-hidden="true"></i>`;
        windowRef.lucide?.createIcons();
    };

    const setState = (state, heading, detail, icon, loading = false) => {
        status.dataset.state = state;
        title.textContent = heading;
        message.textContent = detail;
        spinner.classList.toggle('hidden', !loading);
        statusIcon.classList.toggle('hidden', loading);
        if (icon) showIcon(icon);
    };

    const clearRecognition = () => {
        if (overlay) overlay.classList.add('hidden');
        polygon?.setAttribute('points', '');
        target.classList.remove('detected');
        scanLine.classList.remove('hidden');
    };

    const cancelDetection = () => {
        isScanning = false;
        if (animationId !== null) cancelFrame(animationId);
        animationId = null;
    };

    const stopTracks = ({ hidePreview = true } = {}) => {
        cancelDetection();
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        video.pause();
        if (hidePreview) {
            video.srcObject = null;
            video.classList.add('hidden');
            preview.classList.add('hidden');
            target.classList.add('hidden');
            target.classList.remove('flex');
            clearRecognition();
        }
        stopButton.classList.add('hidden');
        stopButton.disabled = false;
        startButton.classList.remove('hidden');
        startButton.disabled = isSubmitting;
    };

    const resetCamera = () => {
        if (isStarting || isSubmitting) return;
        qrDetected = false;
        payloadInput.value = '';
        detector = null;
        detectionMode = null;
        clearRecognition();
        stopTracks();
        startButton.disabled = false;
        startButton.textContent = 'Start Camera';
        setState('idle', 'Ready to scan', 'Start the camera or enter the tracking code manually below.', 'camera');
    };

    const cameraError = (error) => {
        const name = error?.name;
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') return 'No camera device was found.';
        if (windowRef.isSecureContext === false) return 'Camera access is unavailable in this browser context. Use manual tracking entry.';
        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') return 'Camera permission was denied. Allow camera access or use manual tracking entry.';
        if (name === 'NotReadableError' || name === 'AbortError' || name === 'TrackStartError') return 'Unable to start the camera. Close other apps using the camera or try again.';
        return 'Unable to start the camera. Close other apps using the camera or try again.';
    };

    const makeQrDecoder = async () => {
        const NativeDetector = windowRef.BarcodeDetector;
        if (typeof NativeDetector === 'function') {
            try {
                if (typeof NativeDetector.getSupportedFormats === 'function') {
                    const formats = await NativeDetector.getSupportedFormats();
                    if (formats.includes('qr_code')) return { mode: 'native', detector: new NativeDetector({ formats: ['qr_code'] }) };
                } else {
                    return { mode: 'native', detector: new NativeDetector({ formats: ['qr_code'] }) };
                }
            } catch (_) {
                // Fall back to the bundled decoder when native QR support is absent or unavailable.
            }
        }

        if (typeof qrDecoder !== 'function') throw new Error('QR decoder is not available.');
        if (!canvas) {
            canvas = documentRef.createElement('canvas');
            canvasContext = canvas.getContext('2d', { willReadFrequently: true });
        }
        if (!canvasContext) throw new Error('QR decoder canvas could not be initialized.');
        return { mode: 'fallback', detector: null };
    };

    const detectionPoints = (result) => {
        if (result?.location) {
            const { topLeftCorner, topRightCorner, bottomRightCorner, bottomLeftCorner } = result.location;
            if ([topLeftCorner, topRightCorner, bottomRightCorner, bottomLeftCorner].every(Boolean)) {
                return [topLeftCorner, topRightCorner, bottomRightCorner, bottomLeftCorner];
            }
        }
        if (Array.isArray(result?.cornerPoints) && result.cornerPoints.length >= 4) return result.cornerPoints.slice(0, 4);
        const box = result?.boundingBox;
        if (box && Number.isFinite(box.x) && Number.isFinite(box.y) && box.width > 0 && box.height > 0) {
            return [
                { x: box.x, y: box.y },
                { x: box.x + box.width, y: box.y },
                { x: box.x + box.width, y: box.y + box.height },
                { x: box.x, y: box.y + box.height },
            ];
        }
        return null;
    };

    const showRecognition = (result) => {
        scanLine.classList.add('hidden');
        target.classList.add('detected');
        const points = detectionPoints(result);
        if (points && overlay && polygon) {
            overlay.setAttribute('viewBox', `0 0 ${video.videoWidth} ${video.videoHeight}`);
            overlay.setAttribute('preserveAspectRatio', 'xMidYMid slice');
            polygon.setAttribute('points', points.map(point => `${point.x},${point.y}`).join(' '));
            overlay.classList.remove('hidden');
        }
    };

    const submitResolvedPayload = (value, result) => {
        if (qrDetected || isSubmitting || !value) return;
        qrDetected = true;
        payloadInput.value = value;
        cancelDetection();
        showRecognition(result);
        stopTracks({ hidePreview: false });
        startButton.disabled = true;
        startButton.classList.add('hidden');
        setState('detected', 'QR Code Detected', 'The QR code was read. Preparing to verify the shipment.', 'check');
        detectDelay = setTimer(() => {
            if (isSubmitting || pageLeaving) return;
            setState('processing', 'Verifying shipment...', 'The server is validating the shipment and your access.', null, true);
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else {
                isSubmitting = true;
                resolveButton.disabled = true;
                HTMLFormElement.prototype.submit.call(form);
            }
        }, SUCCESS_HIGHLIGHT_MS);
    };

    const scanFallbackFrame = () => {
        const width = video.videoWidth;
        const height = video.videoHeight;
        if (!width || !height) return null;
        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
        }
        canvasContext.drawImage(video, 0, 0, width, height);
        const pixels = canvasContext.getImageData(0, 0, width, height);
        return qrDecoder(pixels.data, width, height, { inversionAttempts: 'attemptBoth' });
    };

    const scanFrame = async (timestamp = 0) => {
        if (!isScanning || isDetecting) return;
        if (timestamp >= nextScanAt && video.readyState >= 2) {
            nextScanAt = timestamp + SCAN_INTERVAL_MS;
            isDetecting = true;
            try {
                const result = detectionMode === 'native'
                    ? (await detector.detect(video))[0]
                    : scanFallbackFrame();
                if (!isScanning) return;
                const value = detectionMode === 'native' ? result?.rawValue : result?.data;
                if (typeof value === 'string' && value.trim()) {
                    submitResolvedPayload(value, result);
                    return;
                }
            } catch (_) {
                if (!isScanning) return;
                cancelDetection();
                stopTracks();
                startButton.disabled = false;
                startButton.textContent = 'Retry Camera';
                setState('error', 'QR scanner could not start', 'Try again or use manual tracking entry.', 'triangle-alert');
                return;
            } finally {
                isDetecting = false;
            }
        }
        if (isScanning) animationId = requestFrame(scanFrame);
    };

    const startCamera = async () => {
        if (isStarting || stream || isSubmitting || pageLeaving) return;
        isStarting = true;
        qrDetected = false;
        startButton.disabled = true;
        stopButton.classList.add('hidden');
        clearRecognition();
        setState('starting', 'Opening camera...', 'Please allow camera access when your browser asks.', null, true);

        if (!navigatorRef.mediaDevices?.getUserMedia) {
            isStarting = false;
            startButton.disabled = false;
            startButton.textContent = 'Retry Camera';
            setState('error', 'Camera unavailable', windowRef.isSecureContext === false ? 'Camera access is unavailable in this browser context. Use manual tracking entry.' : 'Camera scanning is unavailable in this browser. Use manual tracking entry.', 'triangle-alert');
            return;
        }

        try {
            const cameraStream = await navigatorRef.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            if (pageLeaving || isSubmitting) {
                cameraStream.getTracks().forEach(track => track.stop());
                return;
            }
            stream = cameraStream;
            video.srcObject = stream;
            await video.play();
            if (pageLeaving || isSubmitting || stream !== cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                return;
            }
            preview.classList.remove('hidden');
            video.classList.remove('hidden');
            stopButton.classList.remove('hidden');
            startButton.disabled = true;

            const decoder = await makeQrDecoder();
            if (pageLeaving || isSubmitting || stream !== cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                return;
            }
            detector = decoder.detector;
            detectionMode = decoder.mode;
            target.classList.remove('hidden');
            target.classList.add('flex');
            startButton.classList.add('hidden');
            nextScanAt = 0;
            isScanning = true;
            setState('scanning', 'Scanning QR code...', 'Hold the waybill QR code inside the frame.', 'scan-line');
            animationId = requestFrame(scanFrame);
        } catch (error) {
            stopTracks();
            startButton.disabled = false;
            startButton.textContent = 'Retry Camera';
            if (error?.message?.includes('QR decoder')) {
                setState('error', 'QR scanner could not start', 'Try again or use manual tracking entry.', 'triangle-alert');
            } else {
                setState('error', 'Camera could not start', cameraError(error), 'triangle-alert');
            }
        } finally {
            isStarting = false;
        }
    };

    startButton.addEventListener('click', startCamera);
    stopButton.addEventListener('click', resetCamera);
    form.addEventListener('submit', (event) => {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }
        isSubmitting = true;
        if (!qrDetected) payloadInput.value = '';
        if (detectDelay !== null) clearTimer(detectDelay);
        stopTracks();
        startButton.disabled = true;
        stopButton.disabled = true;
        resolveButton.disabled = true;
        setState('processing', 'Verifying shipment...', 'The server is validating the shipment and your access.', null, true);
    });

    const cleanupOnLeave = () => {
        pageLeaving = true;
        if (detectDelay !== null) clearTimer(detectDelay);
        stopTracks();
    };
    windowRef.addEventListener('pagehide', cleanupOnLeave);
    windowRef.addEventListener('beforeunload', cleanupOnLeave);

    if (status.dataset.state === 'verified') {
        setState('verified', 'Shipment verified', 'QR verified successfully. Review the shipment details below.', 'check');
    }

    return { resetCamera, cleanupOnLeave };
}

if (typeof document !== 'undefined') initializeShipmentScanner();
