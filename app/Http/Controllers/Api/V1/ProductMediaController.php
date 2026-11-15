<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ProductImage;
use App\Services\ProductCatalogService;
use App\Services\ProductMediaService;
use Illuminate\Support\Facades\Storage;

/** Deliberately narrow public proxy for Flutter's product-image URL rewrite. */
class ProductMediaController
{
    public function show(string $path, ProductMediaService $media, ProductCatalogService $catalog)
    {
        $storagePath = 'products/'.ltrim($path, '/');
        abort_unless($media->usablePath($storagePath), 404);

        $image = ProductImage::query()->with('product')->where('path', $storagePath)->first();
        abort_unless($image?->product && $catalog->marketplaceProduct($image->product->slug), 404);

        return Storage::disk('public')->response($storagePath, null, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
