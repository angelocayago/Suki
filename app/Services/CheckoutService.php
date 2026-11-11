<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductModerationStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VoucherRedemption;
use App\Services\VoucherService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /** The project-wide COD shipping fee charged once for each seller shipment. */
    public const SHIPPING_FEE_PER_SELLER_MINOR = 5000;
    public function cartSummary(User $buyer, array $voucherCodes = [], ?array $cartIds = null): array
    {
        $query = CartItem::where('user_id',$buyer->id)->orderBy('id')
            ->with(['productVariant.product.category', 'productVariant.product.seller.owner', 'productVariant.product.images']);
        if ($cartIds !== null) $query->whereIn('id', $cartIds);
        $rows = $query->get();
        if ($cartIds !== null && $rows->count() !== count($cartIds)) throw ValidationException::withMessages(['cart_selection' => 'Some selected items are no longer in your Cart. Review your selection.']);
        return $this->selectionSummary($buyer, $rows, $voucherCodes);
    }

    public function fingerprints(array $cart): array
    {
        $out = [];
        foreach ($cart as $line) {
            $options = $line['variation'] ?? []; ksort($options);
            $out[(int) $line['id']] = [(int) $line['variant_id'], (int) $line['product_id'], (int) $line['seller_id'], (int) $line['quantity'], (int) $line['price_minor'], $options];
        }
        ksort($out);
        return $out;
    }

    public function directSummary(User $buyer, array $selection, array $voucherCodes = []): array
    {
        return $this->selectionSummary($buyer, collect([$this->directItem($buyer,$selection)]), $voucherCodes, true);
    }

    private function directItem(User $buyer, array $selection): CartItem
    {
        $variant = ProductVariant::with(['product.seller'])->find($selection['variant_id']);
        if (! $variant?->product) throw ValidationException::withMessages(['buy_now'=>'This product option is no longer available. Return to the product to choose another.']);
        $image = $variant->image_path;
        if ($image && !Str::startsWith($image,['http://','https://']) && !Storage::disk('public')->exists($image)) $image = null;
        return (new CartItem())->forceFill(['id'=>0,'user_id'=>$buyer->id,'product_variant_id'=>$variant->id,'product_slug'=>$variant->product->slug,'product_name'=>$variant->product->name,'quantity'=>$selection['quantity'],'image'=>$image ?? app(ProductCatalogService::class)->thumbnail($variant->product)]);
    }

    private function selectionSummary(User $buyer, Collection $selection, array $voucherCodes, bool $directOffer = false): array
    {
        $items = $selection
            ->mapWithKeys(function (CartItem $cartItem) {
                $variant = $this->displayVariant($cartItem);
                $product = $variant?->product;
                $available = $variant
                    && $product
                    && $this->isMarketplaceEligible($variant)
                    && (int) $variant->price_minor >= 0
                    && (int) $cartItem->quantity > 0
                    && (int) $cartItem->quantity <= (int) $variant->stock;

                $image = $variant?->image_path;
                if ($image && ! Str::startsWith($image, ['http://', 'https://']) && ! Storage::disk('public')->exists($image)) $image = null;
                if ($image && ! Str::startsWith($image, ['http://', 'https://'])) {
                    $image = Storage::disk('public')->url($image);
                }
                // thumbnail() already returns a resolved URL, including fake-disk relative URLs.
                $image ??= $product ? app(ProductCatalogService::class)->thumbnail($product)
                    : ($cartItem->image && ! Str::startsWith($cartItem->image, ['http://', 'https://']) ? Storage::disk('public')->url($cartItem->image) : $cartItem->image);

                return [
                    $cartItem->id => [
                        'id' => $cartItem->id,
                        'variant_id' => $variant?->id,
                        'product_id' => $product?->id,
                        'slug' => $cartItem->product_slug,
                        'name' => $product?->name ?? $cartItem->product_name,
                        'price' => $variant ? $variant->price_minor / 100 : (float) $cartItem->price,
                        'price_minor' => $variant ? (int) $variant->price_minor : (int) round((float) $cartItem->price * 100),
                        'image' => $image,
                        'quantity' => (int) $cartItem->quantity,
                        'variant' => $variant?->name,
                        'variation' => $variant?->options,
                        'seller_id' => $product?->seller_id,
                        'seller_name' => $product?->seller?->name,
                        'seller_slug' => $product?->seller?->slug,
                        'stock' => (int) ($variant?->stock ?? 0),
                        'available' => (bool) $available,
                        'availability_issue' => $this->cartIssue($variant, (int) $cartItem->quantity, $cartItem->product_slug),
                    ],
                ];
            })
            ->all();

        $subtotalMinor = collect($items)->sum(
            fn (array $item) => $item['price_minor'] * $item['quantity']
        );
        $sellerCount = collect($items)
            ->filter(fn (array $item) => $item['available'] && $item['seller_id'])
            ->pluck('seller_id')
            ->unique()
            ->count();
        $shippingMinor = $sellerCount * self::SHIPPING_FEE_PER_SELLER_MINOR;
        $sellerSubtotals = collect($items)
            ->filter(fn (array $item) => $item['available'] && $item['seller_id'])
            ->groupBy('seller_id')
            ->map(fn (Collection $sellerItems) => $sellerItems->sum(fn (array $item) => $item['price_minor'] * $item['quantity']));
        $voucherPreview = app(VoucherService::class)->preview($voucherCodes, $sellerSubtotals, $buyer);
        $discountMinor = collect($voucherPreview['redemptions'])->sum('amount');

        $summary = [
            'cart' => $items,
            'subtotal' => $subtotalMinor / 100,
            'seller_count' => $sellerCount,
            'shipping_per_seller' => self::SHIPPING_FEE_PER_SELLER_MINOR / 100,
            'shipping' => $shippingMinor / 100,
            'voucher_codes' => array_values(array_unique(array_filter(array_map(fn ($code) => strtoupper(trim((string) $code)), $voucherCodes)))),
            'voucher_error' => $voucherPreview['error'],
            'applied_vouchers' => collect($voucherPreview['redemptions'])->map(fn (array $redemption) => ['code' => $redemption['voucher']->code, 'discount' => $redemption['amount'] / 100])->values()->all(),
            'discount' => $discountMinor / 100,
            'total' => ($subtotalMinor + $shippingMinor - $discountMinor) / 100,
            'has_unavailable_items' => collect($items)->contains(
                fn (array $item) => ! $item['available']
            ),
        ];
        if ($directOffer) $summary['buy_now_terms'] = $this->buyNowTerms($items, $subtotalMinor, $shippingMinor, $voucherCodes, $voucherPreview['redemptions']);
        return $summary;
    }

    /** The same material terms from preview data and final locked records. No client money. */
    private function buyNowTerms(array $items, int $subtotalMinor, int $shippingMinor, array $codes, array $redemptions): array
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($code) => strtoupper(trim((string) $code)), $codes))));
        sort($codes);
        $discounts = [];
        foreach ($redemptions as $redemption) {
            $voucher = $redemption['voucher'];
            $allocation = $redemption['allocation']; ksort($allocation);
            $discounts[$voucher->code] = [(int) $voucher->id, $voucher->scope, $voucher->seller_id === null ? null : (int) $voucher->seller_id,
                $voucher->discount_type, (int) ($voucher->discount_type === 'PERCENTAGE' ? $voucher->discount_bps : $voucher->discount_minor),
                (int) $voucher->minimum_spend_minor, $voucher->max_discount_minor === null ? null : (int) $voucher->max_discount_minor,
                (int) $redemption['amount'], array_map('intval', $allocation)];
        }
        ksort($discounts);
        $names = []; foreach ($items as $item) $names[(int) $item['variant_id']] = $item['variant']; ksort($names);
        $discountMinor = (int) collect($redemptions)->sum('amount');
        return ['items' => $this->fingerprints($items), 'variant_names' => $names, 'subtotal_minor' => $subtotalMinor,
            'shipping_minor' => $shippingMinor, 'voucher_codes' => $codes, 'discounts' => $discounts,
            'discount_minor' => $discountMinor, 'total_minor' => $subtotalMinor + $shippingMinor - $discountMinor];
    }

    public function checkout(
        User $buyer,
        array $address,
        string $paymentMethod,
        array $voucherCodes = [],
        ?array $directSelection = null,
        ?string $reference = null,
        ?array $cartSnapshot = null,
        ?array $directOffer = null
    ): Order {
        return DB::transaction(function () use ($buyer, $address, $paymentMethod, $voucherCodes, $directSelection, $reference, $cartSnapshot, $directOffer) {
            if ($cartSnapshot !== null) {
                abort_unless((int) $cartSnapshot['buyer_id'] === (int) $buyer->id && !$directSelection, 403);
                User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            }
            if ($reference && ($existing = Order::where('buyer_id',$buyer->id)->where('order_number',$reference)->first())) return $existing;
            if ($directSelection !== null) {
                if (!$directOffer || ($directOffer['expires_at'] ?? 0) <= now()->timestamp) {
                    throw ValidationException::withMessages(['buy_now_offer' => \App\Support\DirectCheckout::REVIEW_MESSAGE]);
                }
                abort_unless((int) $directOffer['buyer_id'] === (int) $buyer->id, 403);
            }
            if ($cartSnapshot !== null && $cartSnapshot['expires_at'] <= now()->timestamp) throw ValidationException::withMessages(['cart_selection' => 'Your Checkout selection expired. Return to Cart and choose Checkout again.']);
            $cartItems = $directSelection ? collect([$this->directItem($buyer,$directSelection)]) : CartItem::query()
                ->where('user_id', $buyer->id)
                ->when($cartSnapshot !== null, fn ($query) => $query->whereIn('id', $cartSnapshot['ids']))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($cartSnapshot !== null && $cartItems->count() !== count($cartSnapshot['ids'])) throw ValidationException::withMessages(['cart_selection' => 'Some selected items were removed. Return to Cart to review your selection.']);

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $lines = $cartItems->map(
                fn (CartItem $cartItem) => $this->resolveLine($cartItem)
            );

            if ($cartSnapshot !== null || $directSelection !== null) {
                $current = $lines->map(fn ($line) => ['id' => $line['cart_item']->id, 'variant_id' => $line['variant']->id,
                    'product_id' => $line['product']->id, 'seller_id' => $line['seller']->id,
                    'quantity' => $line['quantity'], 'price_minor' => $line['variant']->price_minor, 'variation' => $line['variant']->options, 'variant' => $line['variant']->name])->all();
                if ($cartSnapshot !== null && $this->fingerprints($current) !== $cartSnapshot['fingerprints']) throw ValidationException::withMessages(['cart_selection' => 'A selected item changed in quantity, price, variant or store. Return to Cart and confirm it again.']);
            }

            if ($reference && ($existing = Order::where('buyer_id',$buyer->id)->where('order_number',$reference)->first())) return $existing;

            foreach ($lines->groupBy(fn (array $line) => $line['variant']->id) as $variantLines) {
                $variant = $variantLines->first()['variant'];
                $requested = $variantLines->sum('quantity');

                if ($variant->stock < $requested) {
                    throw ValidationException::withMessages([
                        'cart' => "Insufficient inventory for {$variant->product->name}.",
                    ]);
                }
            }

            $sellerGroups = $lines->groupBy(fn (array $line) => $line['seller']->id);
            $subtotalMinor = $lines->sum('line_total_minor');
            $sellerSubtotals = $sellerGroups->map(fn ($group) => $group->sum('line_total_minor'));
            $redemptions = app(VoucherService::class)->resolve($voucherCodes, $sellerSubtotals, $buyer);
            $sellerDiscounts = $sellerSubtotals->map(fn () => 0);
            foreach ($redemptions as $redemption) foreach ($redemption['allocation'] as $sellerId => $amount) $sellerDiscounts[$sellerId] += $amount;
            $shippingMinor = $sellerGroups->count() * self::SHIPPING_FEE_PER_SELLER_MINOR;
            $discountMinor = collect($redemptions)->sum('amount');
            $totalMinor = $subtotalMinor + $shippingMinor - $discountMinor;
            if ($directSelection !== null && ($directOffer['expires_at'] <= now()->timestamp
                || $this->buyNowTerms($current, $subtotalMinor, $shippingMinor, $voucherCodes, $redemptions) !== $directOffer['terms'])) {
                throw ValidationException::withMessages(['buy_now_offer' => \App\Support\DirectCheckout::REVIEW_MESSAGE]);
            }
            if ($cartSnapshot !== null && $totalMinor !== $cartSnapshot['total_minor']) throw ValidationException::withMessages(['cart_selection' => 'Your selected total changed. Return to Cart and confirm it again.']);
            if ($discountMinor < 0 || $discountMinor > $subtotalMinor || $totalMinor < 0
                || $sellerDiscounts->contains(fn ($discount, $sellerId) => $discount < 0 || $discount > $sellerSubtotals[$sellerId])
                || $sellerDiscounts->sum() !== $discountMinor) {
                throw ValidationException::withMessages(['voucher' => 'Discount allocations could not be reconciled.']);
            }
            $orderNumber = $reference ?? $this->orderNumber();
            $now = now();

            $order = Order::create([
                'order_number' => $orderNumber,
                'reference' => $orderNumber,
                'buyer_id' => $buyer->id,
                'seller_id' => $this->legacySellerUserId($lines),
                'status' => OrderStatus::Placed->value,
                'payment_method' => $paymentMethod,
                'payment_status' => 'UNPAID',
                'shipping_method' => 'suki_logistics',
                'subtotal' => $this->decimal($subtotalMinor),
                'shipping_fee' => $this->decimal($shippingMinor),
                'discount_amount' => $this->decimal($discountMinor),
                'voucher_code' => collect($redemptions)->pluck('voucher.code')->implode(',') ?: null,
                'total_amount' => $this->decimal($totalMinor),
                'total_minor' => $totalMinor,
                'recipient_name' => $address['recipient_name'],
                'recipient_phone' => $address['recipient_phone'],
                'province' => $address['province'],
                'region_code' => $address['region_code'] ?? null,
                'province_code' => $address['province_code'] ?? null,
                'municipality' => $address['municipality'],
                'city_municipality_code' => $address['city_municipality_code'] ?? null,
                'barangay' => $address['barangay'],
                'barangay_code' => $address['barangay_code'] ?? null,
                'street_address' => $address['street_address'],
                'house_number' => $address['house_number'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'address_label' => $address['address_label'] ?? null,
                'delivery_notes' => $address['delivery_notes'] ?? null,
                'shipping_address' => $address,
                'placed_at' => $now,
            ]);

            foreach ($sellerGroups as $sellerLines) {
                $seller = $sellerLines->first()['seller'];
                $sellerSubtotalMinor = $sellerLines->sum('line_total_minor');
                $sellerShippingMinor = self::SHIPPING_FEE_PER_SELLER_MINOR;
                $sellerDiscountMinor = (int) $sellerDiscounts->get($seller->id, 0);
                $sellerCommissionMinor = $sellerLines->sum('commission_minor');
                $sellerFundedDiscount = (int) collect($redemptions)->filter(fn ($r) => $r['voucher']->scope === 'SELLER')->sum(fn ($r) => (int) ($r['allocation'][$seller->id] ?? 0));
                $platformFundedDiscount = $sellerDiscountMinor - $sellerFundedDiscount;

                $sellerOrder = SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $seller->id,
                    'logistics_provider_id' => null,
                    'subtotal_minor' => $sellerSubtotalMinor,
                    'shipping_fee_minor' => $sellerShippingMinor,
                    'commission_minor' => $sellerCommissionMinor,
                    'status' => OrderStatus::Placed->legacySellerStatus(),
                    'workflow_status' => OrderStatus::Placed->value,
                    'status_changed_at' => $now,
                ]);

                Shipment::create([
                    'seller_order_id' => $sellerOrder->id,
                    'logistics_provider_id' => null,
                    'pickup_rider_id' => null,
                    'rider_id' => null,
                    'tracking_code' => $this->trackingCode(),
                    'status' => OrderStatus::Placed->legacyShipmentStatus(),
                    'workflow_status' => OrderStatus::Placed->value,
                    'fee_minor' => 0,
                    'cod_amount_minor' => $paymentMethod === 'cod'
                        ? $sellerSubtotalMinor - $sellerDiscountMinor + $sellerShippingMinor
                        : 0,
                    'cod_collected' => false,
                    'attempts' => 0,
                    'status_changed_at' => $now,
                ]);

                $createdItems = [];
                foreach ($sellerLines as $line) {
                    $variant = $line['variant'];
                    $product = $line['product'];
                    $cartItem = $line['cart_item'];

                    $createdItems[] = $order->items()->create([
                        'seller_id' => $seller->user_id,
                        'seller_order_id' => $sellerOrder->id,
                        'product_id' => $product->id,
                        'category_id_snapshot' => $product->category_id,
                        'category_name_snapshot' => $product->category?->name,
                        'product_variant_id' => $variant->id,
                        'product_slug' => $product->slug,
                        'product_name' => $product->name,
                        'image' => $directSelection ? $cartItem->image : ($product->images->first()?->path ?? $cartItem->image),
                        'variation' => $variant->options,
                        'variant_name' => $variant->name,
                        'unit_price' => $this->decimal($variant->price_minor),
                        'unit_price_minor' => $variant->price_minor,
                        'commission_bps_snapshot' => $line['commission_bps'],
                        'commission_minor' => $line['commission_minor'],
                        'seller_funded_discount_minor' => 0,
                        'platform_funded_discount_minor' => 0,
                        'quantity' => $line['quantity'],
                        'line_total' => $this->decimal($line['line_total_minor']),
                    ]);

                    $variant->decrement('stock', $line['quantity']);
                }
                // Both funding sources share the same remaining item capacity.
                // The stable cart-ID order assigns every minor unit exactly once.
                foreach ([['seller_funded_discount_minor', $sellerFundedDiscount], ['platform_funded_discount_minor', $platformFundedDiscount]] as [$field, $discount]) {
                    $remaining = $discount;
                    foreach ($createdItems as $item) {
                        $capacity = (int) $item->unit_price_minor * (int) $item->quantity
                            - (int) $item->seller_funded_discount_minor - (int) $item->platform_funded_discount_minor;
                        $share = min($capacity, $remaining);
                        $item->update([$field => $share]);
                        $remaining -= $share;
                    }
                    if ($remaining !== 0) {
                        throw ValidationException::withMessages(['voucher' => 'Item discounts could not be reconciled.']);
                    }
                }
                foreach ($redemptions as $redemption) {
                    $amount = (int) ($redemption['allocation'][$seller->id] ?? 0);
                    if (!$amount) continue;
                    $voucher=$redemption['voucher'];
                    VoucherRedemption::create(['voucher_id'=>$voucher->id,'order_id'=>$order->id,'seller_order_id'=>$sellerOrder->id,'buyer_id'=>$buyer->id,'code_snapshot'=>$voucher->code,'scope_snapshot'=>$voucher->scope,'seller_id_snapshot'=>$voucher->seller_id,'discount_type_snapshot'=>$voucher->discount_type,'discount_value_snapshot'=>$voucher->discount_type==='PERCENTAGE'?$voucher->discount_bps:$voucher->discount_minor,'discount_minor'=>$amount]);
                }
            }
            foreach ($redemptions as $redemption) $redemption['voucher']->increment('used_count');

            if (! $directSelection) CartItem::query()
                ->whereIn('id', $cartItems->pluck('id'))
                ->delete();

            $notifications = app(DomainNotificationService::class);
            $notifications->afterCommit($buyer->id, 'Order placed', 'Your order was placed successfully.', ['order_id' => $order->id]);
            foreach ($order->sellerOrders()->with('seller')->get() as $placedSellerOrder) {
                if ($placedSellerOrder->seller?->user_id) {
                    $notifications->afterCommit($placedSellerOrder->seller->user_id, 'New order', 'Your store received a new order.', ['seller_order_id' => $placedSellerOrder->id]);
                }
            }
            return $order->fresh(['sellerOrders.items', 'sellerOrders.shipment', 'items']);
        }, 3);
    }

    private function resolveLine(CartItem $cartItem): array
    {
        $variant = $this->lockedVariant($cartItem);
        $product = $variant->product;
        $quantity = (int) $cartItem->quantity;

        if (
            ! $product
            || ($cartItem->product_slug && $product->slug !== $cartItem->product_slug)
            || ! $this->isMarketplaceEligible($variant)
        ) {
            throw ValidationException::withMessages([
                'cart' => "{$cartItem->product_name} is no longer available.",
            ]);
        }

        $seller = $product->seller;

        if ($quantity < 1 || $variant->stock < $quantity || $variant->price_minor < 0) {
            throw ValidationException::withMessages([
                'cart' => "Insufficient inventory for {$product->name}.",
            ]);
        }

        if (! $cartItem->product_variant_id) {
            $cartItem->update(['product_variant_id' => $variant->id]);
        }

        return [
            'cart_item' => $cartItem,
            'product' => $product,
            'variant' => $variant,
            'seller' => $seller,
            'quantity' => $quantity,
            'line_total_minor' => $variant->price_minor * $quantity,
            'commission_bps' => (int) $product->commission_bps,
            'commission_minor' => $this->commissionMinor(
                $variant->price_minor * $quantity,
                (int) $product->commission_bps
            ),
        ];
    }

    private function lockedVariant(CartItem $cartItem): ProductVariant
    {
        if ($cartItem->product_variant_id) {
            $variant = ProductVariant::query()
                ->with(['product.category', 'product.seller.owner', 'product.images'])
                ->lockForUpdate()
                ->find($cartItem->product_variant_id);

            if (! $variant) {
                throw ValidationException::withMessages(['cart' => "{$cartItem->product_name} is no longer available. Please remove it from your cart."]);
            }
            return $variant;
        }

        $product = Product::query()
            ->where('slug', $cartItem->product_slug)
            ->with(['seller.owner', 'images'])
            ->first();

        $variants = $product?->variants()
            ->where('is_active', true)
            ->lockForUpdate()
            ->get() ?? collect();

        if ($variants->count() !== 1) {
            throw ValidationException::withMessages([
                'cart' => "{$cartItem->product_name} must be reselected before checkout.",
            ]);
        }

        return $variants->first()->setRelation('product', $product);
    }

    private function displayVariant(CartItem $cartItem): ?ProductVariant
    {
        if ($cartItem->product_variant_id) {
            $variant = $cartItem->relationLoaded('productVariant') ? $cartItem->getRelation('productVariant') : ProductVariant::query()
                ->with(['product.category', 'product.seller.owner', 'product.images'])
                ->find($cartItem->product_variant_id);

            if ($variant?->product?->slug === $cartItem->product_slug) {
                return $variant;
            }

            return null;
        }

        $product = Product::query()
            ->where('slug', $cartItem->product_slug)
            ->with(['category', 'seller.owner', 'images'])
            ->first();
        $variants = $product?->variants()->where('is_active', true)->get() ?? collect();

        return $variants->count() === 1
            ? $variants->first()->setRelation('product', $product)
            : null;
    }

    /** Buyer Cart feedback only; checkout/order creation remains authoritative and unchanged. */
    public function cartIssue(?ProductVariant $variant, int $quantity, string $slug): ?string
    {
        if (!$variant || $variant->trashed() || !$variant->product || $variant->product->trashed() || $variant->product->slug !== $slug) return 'This product is no longer available.';
        $product = $variant->product;
        if (!$product->seller || $product->seller->status !== 'approved' || !$product->seller->owner || $product->seller->owner->status !== 'active' || $product->seller->owner->is_suspended) return 'This shop is currently unavailable.';
        if (!$this->isMarketplaceEligible($variant) || $variant->price_minor < 0) return 'This product is no longer available.';
        if ($variant->stock < 1) return 'This item is out of stock.';
        if ($quantity > $variant->stock) return 'Only '.$variant->stock.' available; your cart contains '.$quantity.'. Please lower the quantity or remove this item.';
        if ($quantity < 1) return 'Please enter a quantity of at least 1.';
        return null;
    }

    public function assertCartQuantity(?ProductVariant $variant, int $quantity, string $slug, bool $selected = false): void
    {
        if ($issue = $this->cartIssue($variant, $quantity, $slug)) {
            if ($variant && $variant->stock < 1 && $issue === 'This item is out of stock.') $issue = $selected ? 'The selected variation is out of stock.' : $issue;
            elseif ($variant && $quantity > $variant->stock && str_starts_with($issue, 'Only ')) $issue = 'Only '.$variant->stock.' available. Please lower your quantity.';
            $key = str_contains($issue, 'shop') ? 'shop' : (str_contains($issue, 'no longer') ? 'product' : 'quantity');
            throw ValidationException::withMessages([$key => $issue])->status($key === 'quantity' && $quantity > 0 ? 409 : 422);
        }
    }

    /** Buyer-only picker data; batch-load options without changing Checkout/API summaries. */
    public function variantSwitchChoices(User $buyer): array
    {
        $rows = CartItem::where('user_id', $buyer->id)->orderBy('id')->get();
        $products = Product::whereIn('slug', $rows->pluck('product_slug'))->with(['category', 'seller.owner', 'images', 'variants' => fn ($q) => $q->withTrashed()->orderBy('id')])->get()->keyBy('slug');
        $choices = [];
        foreach ($rows as $row) {
            $product = $products->get($row->product_slug);
            if (!$product) continue;
            $variants = $product->variants;
            $active = $variants->filter(fn ($v) => !$v->trashed() && $v->is_active);
            $source = $row->product_variant_id ? $variants->firstWhere('id', $row->product_variant_id) : null;
            if (!$row->product_variant_id) {
                if ($active->count() !== 1) continue; // Never guess a legacy row's identity.
                $source = $active->first();
            }
            if (!$source) continue;
            foreach ($variants as $variant) {
                if ($variant->trashed() || $variant->id === $source->id) continue;
                $variant->setRelation('product', $product);
                $reason = $this->cartIssue($variant, $row->quantity, $row->product_slug);
                $duplicate = $rows->contains(fn ($other) => $other->id !== $row->id && ($other->product_variant_id === $variant->id || (!$other->product_variant_id && $other->product_slug === $row->product_slug && $active->count() === 1 && $active->first()->id === $variant->id)));
                if ($duplicate) $reason = 'This variant is already in your Cart. Update that row or remove it before switching.';
                $options = $variant->options ?? []; ksort($options);
                $choices[$row->id][] = ['id' => $variant->id, 'label' => $variant->name ?: implode(' / ', array_map(fn ($key, $value) => $key.': '.$value, array_keys($options), $options)),
                    'price_minor' => $variant->price_minor, 'stock' => $variant->stock, 'reason' => $reason, 'proof' => $this->variantSwitchProof($row, $variant, $source)];
            }
        }
        return $choices;
    }

    /** Bind reviewed material terms, not a timestamp or client-supplied price. Stock is checked live. */
    private function variantSwitchProof(CartItem $row, ProductVariant $target, ProductVariant $source): string
    {
        $options = $target->options ?? []; ksort($options);
        $sourceOptions = $source->options ?? []; ksort($sourceOptions);
        $product = $target->product;
        $terms = [$row->user_id, $row->id, $row->product_slug, $row->product_variant_id, $row->quantity, $row->price, $row->product_name, $row->image,
            $source->id, $source->name, $sourceOptions, $source->price_minor, $source->image_path,
            $product->id, $product->slug, $product->name, $product->seller_id, $target->id, $target->name, $options, $target->price_minor, $target->image_path,
            $target->is_active, $target->trashed(), $product->is_active, $product->trashed(), $product->moderation_status,
            $product->category_id, $product->category?->is_active, $product->seller?->status, $product->seller?->owner?->status, $product->seller?->owner?->is_suspended];
        return hash_hmac('sha256', json_encode($terms, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /** Replace intent only: no merge, quantity adjustment, inventory mutation or snapshot rewrite. */
    public function changeCartVariant(User $buyer, int $rowId, int $targetId, string $proof): bool
    {
        try {
            return DB::transaction(function () use ($buyer, $rowId, $targetId, $proof) {
                User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
                $rows = CartItem::where('user_id', $buyer->id)->orderBy('id')->lockForUpdate()->get();
                $row = $rows->firstWhere('id', $rowId);
                if (!$row) throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(CartItem::class);
                $product = Product::withTrashed()->where('slug', $row->product_slug)->with(['category', 'seller.owner', 'images'])->lockForUpdate()->firstOrFail();
                $variants = ProductVariant::withTrashed()->where('product_id', $product->id)->orderBy('id')->lockForUpdate()->get();
                $source = $row->product_variant_id ? $variants->firstWhere('id', $row->product_variant_id) : null;
                $active = $variants->filter(fn ($v) => !$v->trashed() && $v->is_active);
                if (!$row->product_variant_id && $active->count() === 1) $source = $active->first();
                if (!$source) throw ValidationException::withMessages(['stale_cart_item' => 'This Cart item must be reselected before its variant can be changed.'])->status(409);
                $target = $variants->firstWhere('id', $targetId);
                if (!$target) throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(ProductVariant::class);
                $target->setRelation('product', $product);
                $this->assertCartQuantity($target, $row->quantity, $row->product_slug);
                if (!hash_equals($this->variantSwitchProof($row, $target, $source), $proof)) {
                    throw ValidationException::withMessages(['stale_variant_offer' => 'This Cart item or option changed. Review its current quantity, price and availability, then confirm again.'])->status(409);
                }
                if ($rows->contains(fn ($other) => $other->id !== $row->id && ($other->product_variant_id === $targetId || (!$other->product_variant_id && $other->product_slug === $row->product_slug && $active->count() === 1 && $active->first()->id === $targetId)))) {
                    throw ValidationException::withMessages(['variant_already_in_cart' => 'This variant is already in your Cart. Update that row or remove it before switching.'])->status(409);
                }
                if ($row->product_variant_id === $targetId) return false;
                $image = $target->image_path;
                if ($image && !Str::startsWith($image, ['http://', 'https://']) && !Storage::disk('public')->exists($image)) $image = null;
                $row->update(['product_variant_id' => $targetId, 'product_name' => $product->name, 'price' => number_format($target->price_minor / 100, 2, '.', ''),
                    'image' => $image ?? app(ProductCatalogService::class)->thumbnail($product)]);
                return true;
            }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $error) {
            throw ValidationException::withMessages(['variant_already_in_cart' => 'This variant is already in your Cart. Update that row or remove it before switching.'])->status(409);
        }
    }

    public function addCartItem(User $buyer, string $slug, ?int $variantId, int $quantity): CartItem
    {
        return DB::transaction(function () use ($buyer, $slug, $variantId, $quantity) {
            User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $product = Product::withTrashed()->where('slug', $slug)->firstOrFail();
            $rows = CartItem::where('user_id', $buyer->id)->where('product_slug', $slug)->orderBy('id')->lockForUpdate()->get();
            $query = ProductVariant::withTrashed()->with(['product' => fn ($q) => $q->withTrashed()->with(['category', 'seller.owner', 'images'])])->where('product_id', $product->id);
            if ($variantId) $variant = $query->whereKey($variantId)->lockForUpdate()->firstOrFail();
            else $variant = $query->whereNull('deleted_at')->where('is_active', true)->orderBy('id')->lockForUpdate()->first();
            if (!$variant) throw ValidationException::withMessages(['product' => 'This product is no longer available.']);
            $row = $rows->firstWhere('product_variant_id', $variant->id);
            $this->assertCartQuantity($variant, $row && $quantity > 0 ? 1 : $quantity, $slug, (bool) $variantId);
            $existing = (int) ($row?->quantity ?? 0);
            if ($existing + $quantity > $variant->stock) {
                $remaining = max(0, $variant->stock - $existing);
                $message = $remaining ? 'Your cart already has '.$existing.' of this item. Only '.$remaining.' more can be added.' : 'Your cart already contains all '.$variant->stock.' available items.';
                throw ValidationException::withMessages(['quantity' => $message])->status(409);
            }
            $values = ['product_name' => $product->name, 'price' => number_format($variant->price_minor / 100, 2, '.', ''), 'quantity' => $existing + $quantity];
            if ($row) { $row->update($values); return $row; }
            return CartItem::create($values + ['user_id' => $buyer->id, 'product_slug' => $slug, 'product_variant_id' => $variant->id, 'image' => app(ProductCatalogService::class)->thumbnail($product)]);
        }, 3);
    }

    /** Buyer web feedback; preserve the existing API and Buy Again quantity contract below. */
    public function updateBuyerCartQuantity(CartItem $cartItem, int $quantity): void
    {
        try { $variant = $this->lockedVariant($cartItem); }
        catch (ValidationException) { throw ValidationException::withMessages(['product' => 'This product is no longer available.']); }
        $this->assertCartQuantity($variant, $quantity, $cartItem->product_slug, true);
        $cartItem->update(['quantity' => $quantity]);
    }

    /** Called inside an owned cart-row transaction by the Buyer controller. */
    public function updateCartQuantity(CartItem $cartItem, int $quantity): void
    {
        $variant = $this->lockedVariant($cartItem);
        if (! $this->isMarketplaceEligible($variant) || $variant->product?->slug !== $cartItem->product_slug) {
            throw ValidationException::withMessages(['quantity' => 'This product option is no longer available. Please remove it from your cart.']);
        }
        if ($quantity < 1 || $quantity > (int) $variant->stock) {
            throw ValidationException::withMessages(['quantity' => 'Choose a quantity between 1 and the available stock ('.$variant->stock.').']);
        }
        $cartItem->update(['quantity' => $quantity]);
    }

    private function isMarketplaceEligible(ProductVariant $variant): bool
    {
        $product = $variant->product;
        $seller = $product?->seller;

        return (bool) (
            $variant->is_active
            && $product
            && $product->is_active
            && $product->moderation_status === ProductModerationStatus::Approved
            && $product->category?->is_active
            && $seller
            && $seller->status === 'approved'
            && $seller->owner
            && $seller->owner->status === 'active'
            && ! $seller->owner->is_suspended
        );
    }

    private function legacySellerUserId(Collection $lines): ?int
    {
        $sellerUserIds = $lines
            ->pluck('seller.user_id')
            ->unique()
            ->values();

        return $sellerUserIds->count() === 1 ? $sellerUserIds->first() : null;
    }

    private function decimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    private function commissionMinor(int $merchandiseMinor, int $commissionBps): int
    {
        return intdiv(($merchandiseMinor * $commissionBps) + 5000, 10000);
    }

    private function orderNumber(): string
    {
        do {
            $number = 'SKI-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function trackingCode(): string
    {
        do {
            $code = 'SUKI-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
        } while (Shipment::query()->where('tracking_code', $code)->exists());

        return $code;
    }
}
