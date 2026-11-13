<?php

namespace App\Support;

/** Local assets for the fixed demo pack, not a general product media pipeline. */
class DemoProductImages
{
    public const PUBLIC_PREFIX = 'images/demo-products/lahat-meron/';

    public function __construct(private readonly ?string $directory = null) {}

    public static function path(string $key): string
    {
        if (! preg_match('/^lm(00[1-9]|0[1-5][0-9]|060)$/D', $key)) throw new DemoProductImageException('Unknown demo image key.');
        return self::PUBLIC_PREFIX.$key.'.webp';
    }

    /** Recognize only reserved demo assets, including historical host-specific URLs. */
    public static function canonical(?string $value): ?string
    {
        if (! $value || str_contains($value, '..') || str_contains($value, '\\')) return null;
        $path = preg_match('~^https?://~i', $value) ? parse_url($value, PHP_URL_PATH) : $value;
        if (! is_string($path) || ! preg_match('~(?:^|/)images/demo-products/lahat-meron/(lm(?:00[1-9]|0[1-5][0-9]|060))\.webp$~D', $path, $match)) return null;
        return self::path($match[1]);
    }

    /** Origin-relative, including the current installation's base path. */
    public static function url(?string $value): ?string
    {
        $path = self::canonical($value);
        if (! $path || ! is_file(public_path($path))) return null;
        // These committed files belong to this app, not an optional asset CDN.
        return parse_url(url($path), PHP_URL_PATH) ?: null;
    }

    public function published(array $keys): array
    {
        $sources = $this->sources($keys);
        foreach ($sources as $key => $source) {
            $copy = public_path(self::path($key));
            if (! is_file($copy) || is_link($copy) || hash_file('sha256', $copy) !== hash_file('sha256', $source)) {
                throw new DemoProductImageException('Missing or changed published demo image for '.$key.'.');
            }
        }
        return $sources;
    }

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
