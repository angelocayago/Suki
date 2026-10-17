    function bindImageUpload(inputId, previewId, placeholderId, buttonId, statusId, maxMegabytes, saveMessage = 'Image selected. Save store profile to upload.') {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const placeholder = document.getElementById(placeholderId);
        const button = document.getElementById(buttonId);
        const status = document.getElementById(statusId);
        const savedSource = preview.getAttribute('src');
        let previewUrl;

        function releasePreview() {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        function resetPreview() {
            releasePreview();
            if (savedSource) preview.src = savedSource;
            else preview.removeAttribute('src');
            preview.classList.toggle('hidden', !savedSource);
            placeholder.classList.toggle('hidden', Boolean(savedSource));
            button.textContent = savedSource ? 'Replace image' : 'Upload image';
        }
        button.addEventListener('click', () => input.click());
        input.addEventListener('change', function () {
            const file = input.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > maxMegabytes * 1024 * 1024) {
                input.value = '';
                resetPreview();
                status.textContent = 'Choose a JPG, PNG or WEBP image up to ' + maxMegabytes + ' MB.';
                return;
            }
            releasePreview();
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            button.textContent = 'Replace image';
            status.textContent = saveMessage;
        });
        window.addEventListener('pagehide', function (event) {
            if (!event.persisted) releasePreview();
        });
    }
