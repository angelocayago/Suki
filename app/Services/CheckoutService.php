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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /** The project-wide COD shipping fee charged once for each seller shipment. */
    public const SHIPPING_FEE_PER_SELLER_MINOR = 5000;
    public function cartSummary(User $buyer): array
    {
        $items = CartItem::query()
            ->where('user_id', $buyer->id)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (CartItem $cartItem) {
                $variant = $this->displayVariant($cartItem);
                $product = $variant?->product;
                $available = $variant
                    && $product
                    && $this->isMarketplaceEligible($variant)
                    && (int) $cartItem->quantity <= (int) $variant->stock;

                $image = $product?->images->first()?->path ?? $cartItem->image;
                if ($image && ! Str::startsWith($image, ['http://', 'https://'])) {
                    $image = Storage::disk('public')->url($image);
                }

                return [
                    $cartItem->product_slug => [
                        'slug' => $cartItem->product_slug,
                        'name' => $product?->name ?? $cartItem->product_name,
                        'price' => $variant ? $variant->price_minor / 100 : (float) $cartItem->price,
                        'image' => $image,
                        'quantity' => (int) $cartItem->quantity,
                        'variant' => $variant?->name,
                        'variation' => $variant?->options,
                        'seller_id' => $product?->seller_id,
                        'stock' => (int) ($variant?->stock ?? 0),
                        'available' => (bool) $available,
                    ],
                ];
            })
            ->all();

        $subtotalMinor = collect($items)->sum(
            fn (array $item) => (int) round($item['price'] * 100) * $item['quantity']
        );
        $sellerCount = collect($items)
            ->filter(fn (array $item) => $item['available'] && $item['seller_id'])
            ->pluck('seller_id')
            ->unique()
            ->count();
        $shippingMinor = $sellerCount * self::SHIPPING_FEE_PER_SELLER_MINOR;

        return [
            'cart' => $items,
            'subtotal' => $subtotalMinor / 100,
            'seller_count' => $sellerCount,
            'shipping_per_seller' => self::SHIPPING_FEE_PER_SELLER_MINOR / 100,
            'shipping' => $shippingMinor / 100,
            'total' => ($subtotalMinor + $shippingMinor) / 100,
            'has_unavailable_items' => collect($items)->contains(
                fn (array $item) => ! $item['available']
            ),
        ];
    }

    public function checkout(
        User $buyer,
        array $address,
        string $paymentMethod
    ): Order {
        return DB::transaction(function () use ($buyer, $address, $paymentMethod) {
            $cartItems = CartItem::query()
                ->where('user_id', $buyer->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $lines = $cartItems->map(
                fn (CartItem $cartItem) => $this->resolveLine($cartItem)
            );

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
            $shippingMinor = $sellerGroups->count() * self::SHIPPING_FEE_PER_SELLER_MINOR;
            $discountMinor = 0;
            $totalMinor = $subtotalMinor + $shippingMinor - $discountMinor;
            $orderNumber = $this->orderNumber();
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
                'total_amount' => $this->decimal($totalMinor),
                'total_minor' => $totalMinor,
                'recipient_name' => $address['recipient_name'],
                'recipient_phone' => $address['recipient_phone'],
                'province' => $address['province'],
                'municipality' => $address['municipality'],
                'barangay' => $address['barangay'],
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
                $sellerCommissionMinor = $sellerLines->sum('commission_minor');

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
                        ? $sellerSubtotalMinor + $sellerShippingMinor
                        : 0,
                    'cod_collected' => false,
                    'attempts' => 0,
                    'status_changed_at' => $now,
                ]);

                foreach ($sellerLines as $line) {
                    $variant = $line['variant'];
                    $product = $line['product'];
                    $cartItem = $line['cart_item'];

                    $order->items()->create([
                        'seller_id' => $seller->user_id,
                        'seller_order_id' => $sellerOrder->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'product_slug' => $product->slug,
                        'product_name' => $product->name,
                        'image' => $product->images->first()?->path ?? $cartItem->image,
                        'variation' => $variant->options,
                        'variant_name' => $variant->name,
                        'unit_price' => $this->decimal($variant->price_minor),
                        'unit_price_minor' => $variant->price_minor,
                        'commission_bps_snapshot' => $line['commission_bps'],
                        'commission_minor' => $line['commission_minor'],
                        'quantity' => $line['quantity'],
                        'line_total' => $this->decimal($line['line_total_minor']),
                    ]);

                    $variant->decrement('stock', $line['quantity']);
                }
            }

            CartItem::query()
                ->whereIn('id', $cartItems->pluck('id'))
                ->delete();

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

        if ($quantity < 1 || $variant->stock < $quantity) {
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

            if ($variant) {
                return $variant;
            }
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
            $variant = ProductVariant::query()
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
