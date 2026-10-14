<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\SellerInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function increase(
        Request $request,
        string $variant,
        SellerInventoryService $inventory
    ): RedirectResponse {
        $inventory->increase($this->sellerFor($request), $this->variantId($variant));

        return back()->with('success', 'Product stock increased successfully.');
    }

    public function decrease(
        Request $request,
        string $variant,
        SellerInventoryService $inventory
    ): RedirectResponse {
        $inventory->decrease($this->sellerFor($request), $this->variantId($variant));

        return back()->with('success', 'Product stock updated successfully.');
    }

    private function sellerFor(Request $request): Seller
    {
        return $request->user()
            ->sellers()
            ->where('status', 'approved')
            ->firstOrFail();
    }

    private function variantId(string $variant): int
    {
        abort_unless(ctype_digit($variant) && (int) $variant > 0, 404);

        return (int) $variant;
    }
}
