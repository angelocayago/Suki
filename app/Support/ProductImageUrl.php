<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class ProductImageUrl
{
    public static function path(?string $reference): ?string
    {
        $path = trim($reference ?? '');
        if (preg_match('~^https?://~i', $path)) {
            $path = parse_url($path, PHP_URL_PATH) ?: '';
            // Legacy absolute references are interpreted only as local storage assets.
            if (! str_starts_with($path, '/storage/')) {
                return null;
            }
        }
        $path = rawurldecode($path);
        $path = preg_replace('~^/?storage/~', '', $path);
        if (! str_starts_with($path, 'products/') || preg_match('~[\\\\\x00-\x1f?#:]|(?:^|/)\.{1,2}(?:/|$)~', $path)) {
            return null;
        }

        return $path;
    }

    public static function url(?string $reference): ?string
    {
        $path = self::path($reference);
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/'.implode('/', array_map('rawurlencode', explode('/', $path))));
    }
}
