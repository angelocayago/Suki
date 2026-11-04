<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\ProductCatalogService;
use App\Support\ProductOptions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class CartController extends Controller
{
    public function buyAgain(Request $request, string $order, CheckoutService $checkout, ProductCatalogService $catalog): RedirectResponse
    {
        $previous = Order::with('items')->where('buyer_id', $request->user()->id)
            ->where('order_number', $order)->firstOrFail();
        abort_unless(in_array(strtolower($previous->status), ['cancelled', 'returned', 'completed'], true), 422, 'This order is not eligible for Buy Again.');

        try {
            // An ordinary Add to Cart can race the first insert. Retry the whole
            // transaction after a unique-key conflict; never retain partial writes.
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $result = DB::transaction(function () use ($request, $previous, $checkout, $catalog): array {
                        User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                        $cart = CartItem::where('user_id', $request->user()->id)->orderBy('id')
                            ->lockForUpdate()->get()->keyBy('product_variant_id');
                        $variants = ProductVariant::with(['product.category', 'product.seller.owner', 'product.images'])
                            ->whereIn('id', $previous->items->pluck('product_variant_id')->filter())
                            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                        $result = ['added_lines' => 0, 'added_units' => 0, 'items' => []];

                        foreach ($previous->items as $item) {
                            $variant = $variants->get($item->product_variant_id);
                            $product = $item->product_id ? Product::find($item->product_id) : null;
                            $public = $product ? $catalog->marketplaceProduct($product->slug) : null;
                            $public = $public && (int) $public['id'] === (int) $item->product_id ? $public : null;
                            $purchasedOptions = $this->optionIdentity($item->variation);
                            $entry = ['name' => $product?->name ?? $item->product_name, 'options' => $purchasedOptions !== null ? ($item->variation ?? []) : [],
                                'requested' => (int) $item->quantity, 'added' => 0, 'status' => 'skipped',
                                'url' => $public ? route('buyer.product', $product->slug) : null];
                            $reason = null;
                            if (! $item->product_variant_id || ! $item->product_id) {
                                $reason = 'The original product option identity is incomplete. Please select your options again.';
                            } elseif (! $variant) {
                                $reason = 'The original option is missing or archived. Please select your options again.';
                            } elseif ((int) $variant->product_id !== (int) $item->product_id) {
                                $reason = 'The original option no longer belongs to this product. Please select your options again.';
                            } elseif (! $public || ! collect($public['variants'])->contains('id', $variant->id)
                                || ($item->seller_id && (int) $item->seller_id !== (int) $variant->product?->seller?->user_id)) {
                                $reason = 'This product or option is no longer available for purchase.';
                            } elseif ($purchasedOptions === null || $purchasedOptions !== $this->optionIdentity($variant->options)) {
                                $reason = 'The purchased options have changed. Please select your options again.';
                            } elseif ($entry['requested'] < 1 || $entry['requested'] > 2147483647 || $variant->price_minor < 0) {
                                $reason = 'This item has an invalid quantity or current price and cannot be added.';
                            }

                            $existing = $variant ? $cart->get($variant->id) : null;
                            if (! $reason && $existing && ($existing->product_slug !== $product->slug || $existing->quantity < 1)) {
                                $reason = 'The existing Cart item needs review. Please remove it and select the product again.';
                            }
                            $capacity = $variant ? min(2147483647, (int) $variant->stock) - (int) ($existing?->quantity ?? 0) : 0;
                            if (! $reason && $capacity < 1) {
                                $reason = $variant->stock < 1 ? 'The original option is out of stock.' : 'No additional stock is available beyond the quantity already in your Cart.';
                            }
                            if ($reason) {
                                $entry['message'] = $reason;
                                $result['items'][] = $entry;
                                continue;
                            }

                            $added = min($entry['requested'], $capacity);
                            $quantity = (int) ($existing?->quantity ?? 0) + $added;
                            $row = $existing ?? CartItem::create(['user_id' => $request->user()->id,
                                'product_variant_id' => $variant->id, 'product_slug' => $product->slug,
                                'product_name' => $product->name, 'price' => number_format($variant->price_minor / 100, 2, '.', ''), 'quantity' => $quantity]);
                            $checkout->updateCartQuantity($row, $quantity);
                            $row->update(['product_name' => $product->name, 'price' => number_format($variant->price_minor / 100, 2, '.', ''),
                                'image' => collect($public['variants'])->firstWhere('id', $variant->id)['image'] ?? $catalog->thumbnail($product)]);
                            $cart->put($variant->id, $row);
                            $entry['added'] = $added;
                            $entry['status'] = $added < $entry['requested'] ? 'adjusted' : 'added';
                            $entry['message'] = $entry['status'] === 'adjusted'
                                ? 'Added '.$added.' of '.$entry['requested'].' requested because of current stock and existing Cart quantities.'
                                : 'Added '.$added.' to your Cart at the current price.';
                            $result['added_lines']++;
                            $result['added_units'] += $added;
                            $result['items'][] = $entry;
                        }
                        return $result;
                    }, 3);
                    break;
                } catch (UniqueConstraintViolationException $exception) {
                    if ($attempt === 2) throw $exception;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('buyer.cart')->with('error', 'Buy Again could not update your Cart. No items were added. Please try again.');
        }

        return redirect()->route('buyer.cart')->with('reorder_result', $result);
    }

    /** Match the canonical case-insensitive, order-independent option convention. */
    private function optionIdentity(mixed $options): ?array
    {
        if ($options !== null && ! is_array($options)) return null;
        foreach ($options ?? [] as $value) if (! is_string($value) && ! is_numeric($value)) return null;
        try {
            $identity = [];
            foreach (ProductOptions::options($options ?? []) as $name => $value) $identity[mb_strtolower($name)] = mb_strtolower($value);
            ksort($identity);
            return $identity;
        } catch (ValidationException) {
            return null;
        }
    }

    public function increase(Request $request, int $cartItem, CheckoutService $checkout): RedirectResponse
    {
        return $this->changeQuantity($request, $cartItem, $checkout, 1);
    }

    public function decrease(Request $request, int $cartItem, CheckoutService $checkout): RedirectResponse
    {
        return $this->changeQuantity($request, $cartItem, $checkout, -1);
    }

    public function update(Request $request, int $cartItem, CheckoutService $checkout): RedirectResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        return $this->changeQuantity($request, $cartItem, $checkout);
    }

    private function changeQuantity(Request $request, int $cartItem, CheckoutService $checkout, ?int $delta = null): RedirectResponse
    {
        DB::transaction(function () use ($request, $cartItem, $checkout, $delta) {
            $owned = CartItem::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($cartItem);
            if ($delta === -1 && $owned->quantity === 1) {
                $owned->delete();
                return;
            }
            $quantity = $delta === null ? (int) $request->input('quantity') : $owned->quantity + $delta;
            $checkout->updateCartQuantity($owned, $quantity);
        }, 3);
        return back()->with('success', 'Cart updated successfully.');
    }

    public function remove(Request $request, int $cartItem): RedirectResponse
    {
        CartItem::where('user_id', $request->user()->id)->findOrFail($cartItem)->delete();
        return back()->with('success', 'Item removed from cart.');
    }

    public function index(Request $request, CheckoutService $checkout): View
    {
        return view('buyer.cart', $checkout->cartSummary($request->user(), $request->session()->get('checkout_voucher_codes', [])));
    }

    public function applyVoucher(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->validate(['voucher_code'=>['required','string','max:64']])['voucher_code']));
        $direct=app(\App\Support\DirectCheckout::class);$token=$direct->token($request);
        $codes = $direct->codes($request,$token);
        $codes = array_values(array_unique([...$codes, $code]));
        $summary = $token ? $checkout->directSummary($request->user(),$direct->selection($request,$token),$codes) : $checkout->cartSummary($request->user(), $codes);
        if ($summary['voucher_error']) {
            return back()->withErrors(['voucher_code' => $summary['voucher_error']])->withInput();
        }
        $direct->putCodes($request,$token,$codes);
        $addsDiscount = collect($summary['applied_vouchers'])->contains('code', $code);
        return back()->with('success', $addsDiscount
            ? 'Voucher applied. It will be revalidated when you place the order.'
            : 'Voucher saved, but other vouchers already cover its eligible merchandise. No additional discount or usage will be applied.');
    }

    public function removeVoucher(Request $request, string $code): RedirectResponse
    {
        $direct=app(\App\Support\DirectCheckout::class);$token=$direct->token($request);
        $direct->putCodes($request,$token,array_values(array_filter($direct->codes($request,$token),fn($item)=>$item !== strtoupper($code))));
        return back()->with('success', 'Voucher removed.');
    }
}
