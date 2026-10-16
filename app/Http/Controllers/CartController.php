<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = CartItem::where('user_id', Auth::id())
            ->with(['product', 'size', 'variation'])
            ->get();

        $totalMinor = $cartItems->sum(function ($item) {
            if (!$item->product) {
                return 0;
            }

            $priceMinor = $item->variation
                ? (int) $item->variation->effective_price_minor
                : (int) $item->product->effective_price_minor;

            return $priceMinor * (int) $item->quantity;
        });

        $total = $totalMinor / 100;

        return view('cart.index', compact('cartItems', 'total'));
    }

    /**
     * Validate product, variant, size and requested stock.
     *
     * Returns an error message or null when valid.
     */
    private function validatePurchase(
        Product $product,
        int $quantity,
        ?int $variantId,
        ?int $sizeId
    ): ?string {
        if (
            $product->status !== 'published' ||
            $product->compliance_status !== 'approved'
        ) {
            return 'This product is not currently available.';
        }

        if ($quantity < 1) {
            return 'Quantity must be at least 1.';
        }

        if ((int) $product->stock < $quantity) {
            return 'Only ' . (int) $product->stock
                . ' unit(s) of this product are available.';
        }

        if ($product->variations()->exists() && !$variantId) {
            return 'Please select a product variant before purchasing.';
        }

        if ($variantId) {
            $variation = $product->variations()
                ->whereKey($variantId)
                ->first();

            if (!$variation) {
                return 'Selected variant is not available for this product.';
            }

            if ((int) $variation->stock < $quantity) {
                return 'Only ' . (int) $variation->stock
                    . ' unit(s) of the selected variant are available.';
            }
        }

        $hasSizes = $product->sizes()->exists();

        if ($hasSizes && !$sizeId) {
            return 'Please select a size before purchasing.';
        }

        if ($sizeId) {
            $size = $product->sizes
                ->firstWhere('id', $sizeId);

            if (!$size) {
                return 'Selected size is not available for this product.';
            }

            $sizeStock = (int) ($size->pivot->stock ?? 0);

            if ($sizeStock < $quantity) {
                return 'Only ' . $sizeStock
                    . ' unit(s) of the selected size are available.';
            }
        }

        return null;
    }

    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'size_id' => 'nullable|integer|exists:sizes,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $quantity = (int) $validated['quantity'];
        $sizeId = isset($validated['size_id'])
            ? (int) $validated['size_id']
            : null;
        $variantId = isset($validated['variant_id'])
            ? (int) $validated['variant_id']
            : null;

        $error = $this->validatePurchase(
            $product,
            $quantity,
            $variantId,
            $sizeId
        );

        if ($error) {
            return back()->with('error', $error);
        }

        $cartItem = CartItem::where('user_id', Auth::id())
            ->where('product_id', $product->id)
            ->where('size_id', $sizeId)
            ->where('variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $newQuantity = (int) $cartItem->quantity + $quantity;

            // Validate the combined quantity, not just the new addition.
            $error = $this->validatePurchase(
                $product,
                $newQuantity,
                $variantId,
                $sizeId
            );

            if ($error) {
                return back()->with('error', $error);
            }

            $cartItem->update([
                'quantity' => $newQuantity,
            ]);
        } else {
            CartItem::create([
                'user_id' => Auth::id(),
                'product_id' => $product->id,
                'size_id' => $sizeId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
        }

        return redirect()->route('cart.index')
            ->with('success', 'Product added to cart.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        abort_unless(
            (int) $cartItem->user_id === (int) Auth::id(),
            403
        );

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $product = $cartItem->product;

        if (!$product) {
            return back()->with(
                'error',
                'This product is no longer available.'
            );
        }

        $error = $this->validatePurchase(
            $product,
            (int) $validated['quantity'],
            $cartItem->variant_id
                ? (int) $cartItem->variant_id
                : null,
            $cartItem->size_id
                ? (int) $cartItem->size_id
                : null
        );

        if ($error) {
            return back()->with('error', $error);
        }

        $cartItem->update([
            'quantity' => (int) $validated['quantity'],
        ]);

        return redirect()->route('cart.index')
            ->with('success', 'Cart updated.');
    }

    public function destroy(CartItem $cartItem)
    {
        abort_unless(
            (int) $cartItem->user_id === (int) Auth::id(),
            403
        );

        $cartItem->delete();

        return redirect()->route('cart.index')
            ->with('success', 'Item removed from cart.');
    }

    public function buyNow(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'size_id' => 'nullable|integer|exists:sizes,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        $quantity = (int) $validated['quantity'];
        $sizeId = isset($validated['size_id'])
            ? (int) $validated['size_id']
            : null;
        $variantId = isset($validated['variant_id'])
            ? (int) $validated['variant_id']
            : null;

        $error = $this->validatePurchase(
            $product,
            $quantity,
            $variantId,
            $sizeId
        );

        if ($error) {
            return back()->with('error', $error);
        }

        // Keep existing Buy Now session structure.
        // Regular cart items are not modified.
        $request->session()->put('checkout.buy_now', [
            'product_id' => $product->id,
            'size_id' => $sizeId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);

        return redirect()->route('checkout.index')
            ->with(
                'success',
                'Proceeding to checkout for ' . $product->name . '.'
            );
    }
}
