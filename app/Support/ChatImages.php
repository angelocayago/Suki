<?php

namespace App\Support;

use App\Models\SellerMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatImages
{
    public static function prepare(array $files): array
    {
        Validator::make(['images' => $files], ['images' => 'array|max:5', 'images.*' => 'required|file|max:5120'])->validate();
        if (! $files) {
            return [];
        }
        abort_unless(Schema::hasTable('seller_message_attachments'), 503, 'Chat image storage migration is required.');
        abort_unless(function_exists('imagecreatefromstring'), 503, 'Chat images require the PHP GD extension. Text chat is available.');
        $result = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                self::invalid();
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
            $allowed = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];
            $info = @getimagesize($file->getRealPath());
            if (! isset($allowed[$mime]) || ! $info || $info[2] !== $allowed[$mime] || $info[0] > 4096 || $info[1] > 4096 || $info[0] * $info[1] > 8000000) {
                self::invalid();
            }
            $bytes = file_get_contents($file->getRealPath());
            $image = false;
            set_error_handler(function ($severity, $message) {
                throw new \ErrorException($message, 0, $severity);
            });
            try {
                $image = imagecreatefromstring($bytes);
            } catch (\Throwable $exception) {
                self::invalid();
            } finally {
                restore_error_handler();
            }
            if (! $image) {
                self::invalid();
            }
            try {
                imagepalettetotruecolor($image);
                imagesavealpha($image, true);
                ob_start();
                try {
                    $ok = match ($mime) {
                        'image/jpeg' => imagejpeg($image, null, 85), 'image/png' => imagepng($image, null, 9), 'image/webp' => imagewebp($image, null, 85)
                    };
                    $clean = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            } finally {
                imagedestroy($image);
            }
            if (! $ok || ! $clean || strlen($clean) > 5 * 1024 * 1024) {
                self::invalid();
            }
            $result[] = ['content' => $clean, 'mime' => $mime, 'width' => $info[0], 'height' => $info[1], 'source_sha256' => hash('sha256', $bytes)];
        }

        return $result;
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['images' => 'Upload valid JPEG, PNG or WebP images, up to 5 MB each, 4096 pixels per edge and 8 megapixels.']);
    }

    public static function store(SellerMessage $message, array $images): void
    {
        foreach ($images as $position => $image) {
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$image['mime']];
            $path = (string) Str::uuid().'.'.$extension;
            // Track before writing, so a partial failed write is also removed on rollback.
            app(ChatImageWrites::class)->track($path);
            try {
                if (! Storage::disk('chat_images')->put($path, $image['content'])) {
                    throw new \RuntimeException('Private image upload failed.');
                }
            } catch (\Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages(['images' => 'Image storage failed. Nothing was sent; please retry.']);
            }
            $message->attachments()->create(['position' => $position, 'path' => $path, 'mime' => $image['mime'], 'bytes' => strlen($image['content']),
                'width' => $image['width'], 'height' => $image['height'], 'source_sha256' => $image['source_sha256']]);
        }
    }
}
