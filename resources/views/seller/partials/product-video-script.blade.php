@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const videoPicker = document.getElementById('video');
    const videoPreview = document.getElementById('videoPreview');
    const videoStatus = document.getElementById('videoUploadStatus');
    if (!videoPicker || !videoPreview || !videoStatus) return;
    let videoUrl = null;

    videoPicker?.addEventListener('change', () => {
        if (videoUrl) URL.revokeObjectURL(videoUrl);
        videoUrl = null;
        videoPreview.replaceChildren();

        const file = videoPicker.files[0];
        if (!file) {
            videoPicker.setCustomValidity('');
            videoStatus.textContent = '';
            return;
        }

        const invalid = !['video/mp4', 'video/webm'].includes(file.type) || file.size > 25 * 1024 * 1024;
        const message = invalid ? 'Choose one MP4 or WebM video no larger than 25 MB.' : '';

        videoPicker.setCustomValidity(message);
        videoStatus.textContent = message;

        if (invalid) return;

        const video = document.createElement('video');
        videoUrl = URL.createObjectURL(file);
        video.src = videoUrl;
        video.controls = true;
        video.preload = 'metadata';
        video.style.width = '100%';
        video.style.maxHeight = '360px';
        video.style.marginTop = '12px';
        video.style.borderRadius = '12px';

        videoPreview.appendChild(video);
    });

    window.addEventListener('pagehide', () => { if (videoUrl) URL.revokeObjectURL(videoUrl); });
});
</script>
@endpush
