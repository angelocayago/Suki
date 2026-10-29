
import { Html5Qrcode } from 'html5-qrcode';

let scanner = null;
let scannerStarting = false;
let scannerStopping = false;
let activePickupType = 'seller';

const getElements = () => ({
    reader: document.getElementById('qrCameraReader'),
    start: document.getElementById('startQrCamera'),
    stop: document.getElementById('stopQrCamera'),
    message: document.getElementById('qrCameraMessage'),
    token: document.getElementById('qr_token'),
    modal: document.getElementById('scanModal'),
});

function setMessage(text) {
    const { message } = getElements();
    if (message) message.textContent = text;
}

function updateButtons(running = false) {
    const { start, stop } = getElements();

    if (start) start.disabled = running;
    if (stop) stop.disabled = !running;
}

async function stopQrCamera() {
    if (scannerStopping) return;
    scannerStopping = true;

    try {
        if (scanner) {
            if (scanner.isScanning) {
                await scanner.stop();
            }

            scanner.clear();
            scanner = null;
        }
    } catch (error) {
        console.warn('Unable to stop QR scanner:', error);
    } finally {
        const { reader } = getElements();

        if (reader) {
            reader.classList.add('d-none');
        }

        updateButtons(false);
        scannerStarting = false;
        scannerStopping = false;
    }
}

async function startQrCamera() {
    const { reader, modal } = getElements();

    if (!reader || !modal || scannerStarting || scanner) return;

    if (
        !window.isSecureContext ||
        !navigator.mediaDevices?.getUserMedia
    ) {
        setMessage(
            'Camera requires HTTPS or localhost. You can enter the QR manually.'
        );
        return;
    }

    scannerStarting = true;
    updateButtons(true);
    setMessage('Starting camera...');

    try {
        reader.classList.remove('d-none');

        const instance = new Html5Qrcode('qrCameraReader', {
            verbose: false,
        });

        scanner = instance;

        await instance.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: (width, height) => {
                    const size = Math.min(width, height, 240);

                    return {
                        width: size,
                        height: size,
                    };
                },
            },
            async (decodedText) => {
                const { token } = getElements();
                if (!token) return;

                let value = decodedText.trim();

                // Existing seller endpoint expects the secret token.
                // Hub pickup endpoint supports the full JSON QR payload.
                if (activePickupType === 'seller') {
                    try {
                        const payload = JSON.parse(value);

                        if (typeof payload?.q === 'string') {
                            value = payload.q;
                        }
                    } catch {
                        // Plain QR token is also supported.
                    }
                }

                token.value = value;

                await stopQrCamera();

                setMessage(
                    'QR detected! Review the code, then click Verify & Pick Up.'
                );

                token.focus();
            },
            () => {
                // Normal scan misses do not need an error message.
            }
        );

        if (!modal.classList.contains('show')) {
            await stopQrCamera();
            return;
        }

        setMessage(
            'Point the camera at the parcel QR code.'
        );
    } catch (error) {
        console.error('QR camera error:', error);

        setMessage(
            'Unable to start camera. Check camera permissions or enter the QR manually.'
        );

        await stopQrCamera();
    } finally {
        scannerStarting = false;
    }
}

const openScanModal = (
    shipmentId,
    expectedToken,
    orderNumber,
    pickupType = 'seller'
) => {
    const form = document.getElementById('scanForm');
    const tokenInput = document.getElementById('qr_token');
    const orderInput = document.getElementById('scanOrderNumber');
    const modal = document.getElementById('scanModal');

    if (
        !form ||
        !tokenInput ||
        !orderInput ||
        !modal ||
        !window.bootstrap?.Modal
    ) {
        return;
    }

    void stopQrCamera();

    activePickupType = pickupType === 'hub' ? 'hub' : 'seller';

    form.action =
        `/rider/shipments/${encodeURIComponent(shipmentId)}/` +
        (activePickupType === 'hub'
            ? 'scan-hub-pickup'
            : 'scan');

    orderInput.value = orderNumber ?? '';
    tokenInput.value = '';

    const title = modal.querySelector('.modal-title');
    const description = modal.querySelector('.modal-body p');

    if (title) {
        title.textContent = activePickupType === 'hub'
            ? 'Verify QR — Hub Pickup'
            : 'Verify QR — Seller Pickup';
    }

    if (description) {
        description.textContent = activePickupType === 'hub'
            ? 'Scan the parcel QR code at the sorting hub to confirm final-mile handover.'
            : 'Scan the seller parcel QR code to confirm seller pickup.';
    }

    setMessage('Camera scanning is optional. You can enter the QR manually.');

    window.bootstrap.Modal
        .getOrCreateInstance(modal)
        .show();
};

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('startQrCamera')
        ?.addEventListener('click', () => {
            void startQrCamera();
        });

    document.getElementById('stopQrCamera')
        ?.addEventListener('click', () => {
            void stopQrCamera();
        });

    document.getElementById('scanModal')
        ?.addEventListener('hide.bs.modal', () => {
            void stopQrCamera();
        });

    document.getElementById('scanForm')
        ?.addEventListener('submit', () => {
            void stopQrCamera();
        });

    window.addEventListener('pagehide', () => {
        void stopQrCamera();
    });
});

window.openScanModal = openScanModal;

export { openScanModal };
