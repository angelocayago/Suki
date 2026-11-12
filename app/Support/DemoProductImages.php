<?php

namespace App\Support;

/** Local assets for the fixed demo pack, not a general product media pipeline. */
class DemoProductImages
{
    public function __construct(private readonly ?string $directory = null) {}

    public function sources(array $keys): array
    {
        $directory = $this->directory ?? database_path('demo-products/images');
        $manifestPath = $directory.'/manifest.json';
        if (! is_file($manifestPath)) throw new DemoProductImageException('Demo image manifest is missing.');
        try {
            $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new DemoProductImageException('Demo image manifest is invalid.');
        }
        if (! is_array($manifest)) throw new DemoProductImageException('Demo image manifest is invalid.');
        $actualKeys = array_keys($manifest); sort($actualKeys); $expected = $keys; sort($expected);
        if ($actualKeys !== $expected) throw new DemoProductImageException('Demo image manifest must map every dataset key exactly once.');
        if (! function_exists('imagecreatefromwebp')) throw new DemoProductImageException('GD with WebP decoding is required to validate demo images.');
        $sources = [];
        foreach ($keys as $key) {
            $entry = $manifest[$key];
            $path = $directory.'/'.$key.'.webp';
            if (! is_array($entry) || ($entry['file'] ?? null) !== $key.'.webp' || is_link($path) || ! is_file($path)) {
                throw new DemoProductImageException('Missing or invalid demo image for '.$key.'.');
            }
            $size = filesize($path);
            $info = @getimagesize($path);
            if (! $size || $size > 4096 * 1024 || ! $info || $info[2] !== IMAGETYPE_WEBP
                || $info[0] !== 640 || $info[1] !== 640 || ($entry['width'] ?? null) !== 640 || ($entry['height'] ?? null) !== 640
                || ($entry['sha256'] ?? null) !== hash_file('sha256', $path)) {
                throw new DemoProductImageException('Corrupt, changed, or unsupported demo image for '.$key.'.');
            }
            $decoded = @imagecreatefromwebp($path);
            if (! $decoded) throw new DemoProductImageException('Undecodable demo image for '.$key.'.');
            unset($decoded);
            $sources[$key] = $path;
        }
        return $sources;
    }
}
