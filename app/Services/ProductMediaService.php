<?php

namespace App\Services;

use App\Models\Product;
use App\Support\DemoProductImages;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves only media controlled by this installation.  Public catalog cards
 * must never turn an arbitrary URL or a missing storage object into an image.
 */
class ProductMediaService
{
    public function hasUsableImage(Product $product): bool
    {
        return $this->imagePath($product) !== null;
    }

    public function imagePath(Product $product): ?string
    {
        foreach ($product->images->sortBy(fn ($image) => [$image->position, $image->id]) as $image) {
            if ($this->usablePath($image->path)) return $image->path;
        }

        foreach ($product->variants->sortBy('id') as $variant) {
            if ($variant->is_active && $variant->stock > 0 && $this->usablePath($variant->image_path)) {
                return $variant->image_path;
            }
        }

        return null;
    }

    public function usablePath(?string $path): bool
    {
        if (! is_string($path) || $path === '' || str_contains($path, "\0")) return false;
        if (DemoProductImages::canonical($path)) return DemoProductImages::url($path) !== null;
        if (preg_match('~^(?:https?:)?//~i', $path) || str_starts_with($path, '/') || str_contains($path, '\\')) return false;

        $normalised = str_replace('\\', '/', $path);
        if (str_contains($normalised, '..') || ! preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]*$~D', $normalised)) return false;

        return Storage::disk('public')->exists($normalised);
    }

    public function url(?string $path): ?string
    {
        if (! $this->usablePath($path)) return null;
        if (DemoProductImages::canonical($path)) return DemoProductImages::url($path);

        return Storage::disk('public')->url($path);
    }
}
