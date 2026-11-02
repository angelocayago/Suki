            <hr style="margin:20px 0;border:0;border-top:1px solid #dde6e1">

            <h3>Product Video</h3>
            <p>Optional. Upload one MP4 or WebM video up to 25 MB.</p>

            @if($editing && !empty($item['video']))
                <video controls preload="metadata" style="width:100%;max-height:360px;border-radius:12px;background:#000">
                    <source src="{{ $item['video'] }}" type="{{ $item['video_mime'] ?? 'video/mp4' }}">
                </video>
                <label style="margin-top:10px">
                    <input type="checkbox" name="remove_video" value="1">
                    Remove saved product video
                </label>
            @endif

            <label for="video" class="spf-upload">
                Add or Replace Video
                <input id="video" type="file" name="video" accept="video/mp4,video/webm" aria-describedby="videoUploadStatus">
            </label>

            <div id="videoPreview"></div>
            <p id="videoUploadStatus" class="spf-error" role="status" aria-live="polite"></p>
