<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Seller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerInventoryService
{
    // PostgreSQL stores Laravel's unsignedInteger as a signed INTEGER.
    public const MAX_STOCK = 2147483647;

    public function increase(Seller $seller, int $variantId, int $quantity = 1): ProductVariant
    {
        return $this->adjust($seller, $variantId, $quantity);
    }

    public function decrease(Seller $seller, int $variantId, int $quantity = 1): ProductVariant
    {
        return $this->adjust($seller, $variantId, -$quantity);
    }

    public function setStock(Seller $seller, int $variantId, int $stock, ?int $expectedStock = null): ProductVariant
    {
        if ($stock < 0 || $stock > self::MAX_STOCK) {
            throw ValidationException::withMessages([
                'stock' => 'Stock must be between 0 and '.self::MAX_STOCK.'.',
            ]);
        }

        return DB::transaction(function () use ($seller, $variantId, $stock, $expectedStock) {
            $variant = $this->lockedOwnedVariant($seller, $variantId);
            if ($expectedStock !== null && $variant->stock !== $expectedStock) {
                throw ValidationException::withMessages([
                    'stock' => 'Stock changed since this page was loaded. Reload Inventory and try again.',
                ]);
            }
            $variant->update(['stock' => $stock]);

            return $variant->fresh();
        }, 3);
    }

    private function adjust(Seller $seller, int $variantId, int $difference): ProductVariant
    {
        if ($difference === 0) {
            throw ValidationException::withMessages([
                'stock' => 'The stock adjustment must not be zero.',
            ]);
        }

        return DB::transaction(function () use ($seller, $variantId, $difference) {
            $variant = $this->lockedOwnedVariant($seller, $variantId);
            $newStock = $variant->stock + $difference;

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'stock' => 'Stock cannot be reduced below zero.',
                ]);
            }

            if ($newStock > self::MAX_STOCK) {
                throw ValidationException::withMessages([
                    'stock' => 'Stock exceeds the supported maximum.',
                ]);
            }

            $variant->update(['stock' => $newStock]);

            return $variant->fresh();
        }, 3);
    }

    private function lockedOwnedVariant(Seller $seller, int $variantId): ProductVariant
    {
        return $seller->products()
            ->whereHas('variants', fn ($query) => $query->whereKey($variantId))
            ->firstOrFail()
            ->variants()
            ->whereKey($variantId)
            ->lockForUpdate()
            ->firstOr(function () {
                throw (new ModelNotFoundException)->setModel(ProductVariant::class);
            });
    }
}
