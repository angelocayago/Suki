import { Html5Qrcode } from 'html5-qrcode';

document.addEventListener('DOMContentLoaded', () => {
    const normalize = (value) =>
        String(value || '').trim().toLowerCase();

    const selectZoneFromAddress = (modal) => {
        const address = normalize(modal.dataset.destination);
        const select = modal.querySelector('.js-delivery-zone');

        if (!select || !address) {
            return;
        }

        const match = [...select.options].find((option) => {
            const value = normalize(option.value);
            return value && address.includes(value);
        });

        if (match) {
            select.value = match.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    document.querySelectorAll('.js-scan-tag-modal').forEach((modal) => {
        modal.addEventListener('shown.bs.modal', () => {
            selectZoneFromAddress(modal);

            const type = modal.querySelector('.js-delivery-type');
            if (type && !type.value) {
                type.value = 'standard';
            }
        });
    });

    document.querySelectorAll('.js-logistics-qr-scan').forEach((button) => {
        button.addEventListener('click', async () => {
            const modal = button.closest('.js-scan-tag-modal');
            const reader = modal?.querySelector('.js-logistics-qr-reader');
            const result = modal?.querySelector('.js-logistics-qr-result');

            if (!modal || !reader || !result) {
                return;
            }

            const tracking = String(modal.dataset.tracking || '').trim();
            const readerId = reader.id;

            reader.classList.remove('d-none');
            result.textContent = 'Point the camera at the parcel QR code.';
            result.className = 'small text-muted mt-2 js-logistics-qr-result';

            const scanner = new Html5Qrcode(readerId);

            try {
                const cameras = await Html5Qrcode.getCameras();

                if (!cameras.length) {
                    throw new Error('No camera was found.');
                }

                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 240, height: 240 } },
                    async (decodedText) => {
                        let scannedTracking = decodedText;

                        try {
                            const payload = JSON.parse(decodedText);
                            scannedTracking = payload?.t ?? decodedText;
                        } catch (_) {
                            // Plain tracking numbers are also accepted.
                        }

                        if (
                            String(scannedTracking).trim().toUpperCase()
                            !== tracking.toUpperCase()
                        ) {
                            result.textContent =
                                'Wrong parcel QR. Please scan the QR for ' + tracking + '.';
                            result.className =
                                'small text-danger mt-2 js-logistics-qr-result';
                            return;
                        }

                        await scanner.stop();
                        reader.classList.add('d-none');

                        selectZoneFromAddress(modal);

                        const type = modal.querySelector('.js-delivery-type');
                        if (type) {
                            type.value = 'standard';
                        }

                        result.textContent =
                            'QR verified. Zone detected when possible and Standard Delivery selected.';
                        result.className =
                            'small text-success mt-2 js-logistics-qr-result';
                    }
                );
            } catch (error) {
                result.textContent =
                    error?.message || 'Unable to start the camera scanner.';
                result.className =
                    'small text-danger mt-2 js-logistics-qr-result';
            }
        });
    });
});
