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
    public function action(Request $request, callable $operation, string $message, ?string $redirect = null)
    {
        if (!$request->user()) {
            $message = str_contains((string) $request->route()?->getName(), 'wishlist') ? 'Please log in to save items to your wishlist.' : 'Please log in to add items to your cart.';
            if ($request->expectsJson()) return response()->json(['success' => false, 'message' => $message, 'errors' => (object) [], 'code' => 'auth_required'], 401);
            $intended = url()->previous();
            $request->session()->put('url.intended', str_starts_with($intended, url('/')) ? $intended : route('buyer.home'));
            return redirect()->route('login')->with('error', $message);
        }
        abort_unless(($request->user()->role === 'buyer' || (!$request->routeIs('buyer.cart.add', 'buyer.cart.clear', 'buyer.wishlist*') && $request->user()->hasAnyRole(['buyer']))) && $request->user()->status === 'active' && !$request->user()->is_suspended, 403);
        try {
            $result = $operation();
            if ($result instanceof \Symfony\Component\HttpFoundation\Response) return $result;
            if (is_string($result)) $message = $result;
            if ($request->expectsJson()) return response()->json(['success' => true, 'message' => $message, 'errors' => (object) [], 'code' => 'ok']);
            return ($redirect ? redirect()->route($redirect) : $this->actionBack($request))->with('success', $message);
        } catch (ValidationException $error) {
            $errors = $error->errors(); $message = collect($errors)->flatten()->first();
            $code = $error->status === 409 ? (str_contains(strtolower($message), 'out of stock') ? 'out_of_stock' : 'insufficient_stock') : (isset($errors['quantity']) ? 'invalid_quantity' : (isset($errors['variant_id']) && str_contains($message, 'select') ? 'variant_required' : 'unavailable'));
            foreach (['variant_already_in_cart', 'stale_cart_item', 'stale_variant_offer', 'draft_revision'] as $key) {
                if (isset($errors[$key])) $code = $key === 'draft_revision' ? 'stale_selection' : $key;
            }
            if ($request->expectsJson()) return response()->json(['success' => false, 'message' => $message, 'errors' => $errors, 'code' => $code], $error->status);
            return $this->actionBack($request)->withErrors($errors)->withInput()->with($request->routeIs('buyer.cart.change-variant') ? 'cart_variant_line' : 'cart_error_line', $request->route('cartItem'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error); $message = 'Something went wrong. Please try again.';
            if ($request->expectsJson()) return response()->json(['success' => false, 'message' => $message, 'errors' => (object) [], 'code' => 'server_error'], 500);
            return back()->with('error', $message)->withInput();
        }
    }

    private function actionBack(Request $request): RedirectResponse
    {
        // A newly hidden product must not send its own Buyer action back to a 404 page.
        $slug = $request->route('slug');
        if (is_string($slug) && str_contains((string) parse_url(url()->previous(), PHP_URL_PATH), '/product/')
            && !app(ProductCatalogService::class)->marketplaceProduct($slug)) return redirect()->route('buyer.shop');
        return back();
    }

    public function quantityData(Request $request, bool $default = false): array
    {
        if ($default && !$request->exists('quantity')) $request->merge(['quantity' => 1]);
        return $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:2147483647'], 'variant_id' => ['nullable', 'integer', 'min:1']], [
            'quantity.required' => 'Please enter a quantity.', 'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Please enter a quantity of at least 1.', 'quantity.max' => 'Quantity is too large.',
        ]);
    }

    public function add(Request $request, string $slug, CheckoutService $checkout)
    {
        return $this->action($request, function () use ($request, $slug, $checkout) {
            Product::withTrashed()->where('slug', $slug)->firstOrFail();
            $data = $this->quantityData($request, true);
            $row = $checkout->addCartItem($request->user(), $slug, !empty($data['variant_id']) ? (int) $data['variant_id'] : null, (int) $data['quantity']);
            $variant = $row->productVariant;
            return 'Added '.$data['quantity'].' × '.$row->product_name.($variant?->name ? ' — '.$variant->name : '').' to your Cart.';
        }, 'Item added to cart!');
    }

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

    public function increase(Request $request, int $cartItem, CheckoutService $checkout)
    {
        return $this->changeQuantity($request, $cartItem, $checkout, 1);
    }

    public function decrease(Request $request, int $cartItem, CheckoutService $checkout): RedirectResponse
    {
        return $this->changeQuantity($request, $cartItem, $checkout, -1);
    }

    public function update(Request $request, int $cartItem, CheckoutService $checkout)
    {
        return $this->changeQuantity($request, $cartItem, $checkout);
    }

    private function changeQuantity(Request $request, int $cartItem, CheckoutService $checkout, ?int $delta = null)
    {
        return $this->action($request, function () use ($request, $cartItem, $checkout, $delta) {
            // Check ownership before validating user input; never disclose foreign rows.
            CartItem::where('user_id', $request->user()->id)->findOrFail($cartItem);
            if ($delta === null) $this->quantityData($request);
            DB::transaction(function () use ($request, $cartItem, $checkout, $delta) {
                $owned = CartItem::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($cartItem);
                if ($delta === -1 && $owned->quantity === 1) { $owned->delete(); return; }
                $quantity = $delta === null ? (int) $request->input('quantity') : $owned->quantity + $delta;
                $checkout->updateBuyerCartQuantity($owned, $quantity);
            }, 3);
        }, 'Cart updated successfully.');
    }

    public function remove(Request $request, int $cartItem)
    {
        return $this->action($request, fn () => CartItem::where('user_id', $request->user()->id)->findOrFail($cartItem)->delete(), 'Item removed from cart.');
    }

    public function clear(Request $request)
    {
        return $this->action($request, fn () => CartItem::where('user_id', $request->user()->id)->delete(), 'Cart cleared.', 'buyer.cart');
    }

    public function index(Request $request, CheckoutService $checkout)
    {
        $data = $this->selectionData($request, $checkout);
        $data['variant_choices'] = $checkout->variantSwitchChoices($request->user());
        return $request->expectsJson() ? response()->json(['success' => true, 'message' => '', 'errors' => (object) [], 'code' => 'ok', 'data' => $data]) : view('buyer.cart', $data);
    }

    public function changeVariant(Request $request, int $cartItem, CheckoutService $checkout)
    {
        return $this->action($request, function () use ($request, $cartItem, $checkout) {
            CartItem::where('user_id', $request->user()->id)->findOrFail($cartItem);
            $data = $request->validate(['variant_id' => ['required', 'integer', 'min:1'], 'draft_revision' => ['required', 'integer', 'min:0'], 'variant_proof' => ['required', 'array'], 'variant_proof.*' => ['required', 'string', 'size:64']]);
            $selection = app(\App\Support\CartSelection::class);
            $draft = $selection->draft($request);
            if ((int) $data['draft_revision'] !== $draft['revision']) throw ValidationException::withMessages(['draft_revision' => 'Your selection changed in another tab. Refresh the Cart and review the latest selection before changing a variant.'])->status(409);
            $proof = $data['variant_proof'][$data['variant_id']] ?? '';
            if ($checkout->changeCartVariant($request->user(), $cartItem, (int) $data['variant_id'], $proof)) $selection->advanceRevision($request, $draft);
            if (!$request->expectsJson()) return redirect()->to(route('buyer.cart').'#cart-line-'.$cartItem)->with('success', 'Variant updated. Your quantity and selection were kept.');
            return 'Variant updated. Your quantity and selection were kept.';
        }, 'Variant updated.');
    }

    private function selectionData(Request $request, CheckoutService $checkout): array
    {
        $selection = app(\App\Support\CartSelection::class);
        $draft = $selection->draft($request);
        $all = $checkout->cartSummary($request->user(), $request->session()->get('checkout_voucher_codes', []));
        $selected = $checkout->cartSummary($request->user(), $request->session()->get('checkout_voucher_codes', []), $draft['ids']);
        $data = $all + ['selection' => $draft, 'selected_summary' => $selected,
            'selected_invalid_message' => $selected['has_unavailable_items'] ? $selection->invalidMessage($selected) : null];
        if ($request->expectsJson()) {
            $data['summary_html'] = view('buyer.partials.cart-summary', $data)->render();
        }
        return $data;
    }

    public function selection(Request $request, CheckoutService $checkout)
    {
        $selection = app(\App\Support\CartSelection::class);
        try {
            $request->validate(['intent' => ['sometimes', 'in:update,checkout']]);
            $draft = $selection->save($request, $checkout);
            if ($request->input('intent') === 'checkout') {
                $summary = $checkout->cartSummary($request->user(), $request->session()->get('checkout_voucher_codes', []), $draft['ids']);
                $token = $selection->start($request, $summary, $draft['ids']);
                return redirect()->route('buyer.checkout', ['cart_selection' => $token]);
            }
            if ($request->expectsJson()) return response()->json(['success' => true, 'message' => 'Selection updated.', 'errors' => (object) [], 'code' => 'ok', 'data' => $this->selectionData($request, $checkout)]);
            return redirect()->route('buyer.cart')->with('info', 'Selection updated.');
        } catch (ValidationException $error) {
            if ($request->expectsJson()) return response()->json(['success' => false, 'message' => collect($error->errors())->flatten()->first(), 'errors' => $error->errors(),
                'code' => isset($error->errors()['draft_revision']) ? 'stale_selection' : 'invalid_selection', 'data' => $this->selectionData($request, $checkout)], $error->status);
            return redirect()->route('buyer.cart')->withErrors($error->errors());
        }
    }

    public function applyVoucher(Request $request, CheckoutService $checkout): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->validate(['voucher_code'=>['required','string','max:64']])['voucher_code']));
        $direct=app(\App\Support\DirectCheckout::class);$token=$direct->token($request);
        $cartSelection = app(\App\Support\CartSelection::class);
        $cartToken = !$token && $request->filled('cart_selection') ? $cartSelection->token($request) : null;
        $codes = $cartToken ? $cartSelection->state($request, $cartToken)['voucher_codes'] : $direct->codes($request,$token);
        $codes = array_values(array_unique([...$codes, $code]));
        $summary = $token ? $checkout->directSummary($request->user(),$direct->selection($request,$token),$codes) : $checkout->cartSummary($request->user(), $codes, $cartToken ? $cartSelection->state($request, $cartToken)['ids'] : $cartSelection->draft($request)['ids']);
        if ($summary['voucher_error']) {
            return back()->withErrors(['voucher_code' => $summary['voucher_error']])->withInput();
        }
        if ($cartToken) $cartSelection->putCodes($request, $cartToken, $codes, $checkout);
        else $direct->putCodes($request,$token,$codes);
        $addsDiscount = collect($summary['applied_vouchers'])->contains('code', $code);
        return back()->with('success', $addsDiscount
            ? 'Voucher applied. It will be revalidated when you place the order.'
            : 'Voucher saved, but other vouchers already cover its eligible merchandise. No additional discount or usage will be applied.');
    }

    public function removeVoucher(Request $request, string $code, CheckoutService $checkout): RedirectResponse
    {
        $direct=app(\App\Support\DirectCheckout::class);$token=$direct->token($request);
        $selection = app(\App\Support\CartSelection::class);
        $cartToken = !$token && $request->filled('cart_selection') ? $selection->token($request) : null;
        $codes = $cartToken ? $selection->state($request,$cartToken)['voucher_codes'] : $direct->codes($request,$token);
        $codes = array_values(array_filter($codes,fn($item)=>$item !== strtoupper($code)));
        if ($cartToken) $selection->putCodes($request,$cartToken,$codes,$checkout); else $direct->putCodes($request,$token,$codes);
        return back()->with('success', 'Voucher removed.');
    }
}
