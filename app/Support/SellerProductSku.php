<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerProductSku
{
    public static function createVariant(Product $product, array $attributes, bool $additional = false): ProductVariant
    {
        // The product ID is already allocated by the database inside the save
        // transaction. No counts or guessed next IDs participate in allocation.
        $base = 'S'.$product->seller_id.'-P'.$product->id;
        if ($additional) {
            $base .= '-V'.Str::upper(Str::random(10));
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $sku = $base.($attempt === 0 ? '' : '-'.Str::upper(Str::random(8)));
            try {
                // A savepoint lets a unique-index collision roll back cleanly,
                // including on databases that abort a transaction on an error.
                return DB::transaction(fn () => $product->variants()->create(
                    array_replace($attributes, ['sku' => $sku])
                ));
            } catch (UniqueConstraintViolationException) {
                // The unique index is authoritative, including deleted rows.
            }
        }

        throw ValidationException::withMessages(['sku' => 'A unique SKU could not be assigned. Please try saving again.']);
    }
}
