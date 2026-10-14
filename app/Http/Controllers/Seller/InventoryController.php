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

    public function set(
        Request $request,
        string $variant,
        SellerInventoryService $inventory
    ): RedirectResponse {
        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $inventory->setStock(
            $this->sellerFor($request),
            $this->variantId($variant),
            (int) $validated['stock']
        );

        return back()->with('success', 'Product stock set successfully.');
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
