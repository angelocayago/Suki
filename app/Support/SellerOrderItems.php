<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerOrderItems
{
    public const STATUS_SQL = 'LOWER(COALESCE(seller_orders.status, orders.status))';

    public const GROSS_SQL = 'COALESCE(order_items.unit_price_minor / 100.0, order_items.unit_price) * order_items.quantity';

    public static function owned(Seller $seller)
    {
        return OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
            ->where(function ($q) use ($seller) {
                $q->where(fn ($q) => $q->where('seller_orders.seller_id', $seller->id)->whereColumn('seller_orders.order_id', 'orders.id'))
                    ->orWhere(fn ($q) => $q->whereNull('order_items.seller_order_id')->where('order_items.seller_id', $seller->user_id)->where('orders.seller_id', $seller->user_id)
                        // Once an order has normalized seller records, do not also count legacy copies.
                        ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('seller_orders as normalized')->whereColumn('normalized.order_id', 'orders.id')));
            });
    }

    public static function filtered(Seller $seller, Request $request, bool $sales = false): array
    {
        $products = $seller->products()->orderBy('name')->orderBy('id')->get();
        $categories = SellerCategories::children($seller, SellerCategories::main($seller));
        $base = self::owned($seller);
        $paymentMethods = (clone $base)->whereNotNull('orders.payment_method')->distinct()->orderBy('orders.payment_method')->pluck('orders.payment_method')->filter()->values();
        $sorts = $sales ? ['newest', 'oldest', 'amount_desc', 'amount_asc'] : ['recent', 'sales_desc', 'sales_asc', 'units_desc'];
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', Rule::in($products->pluck('id')->all())],
            'category' => ['nullable', 'integer', Rule::in([SellerCategories::main($seller)?->id])],
            'subcategory' => ['nullable', 'integer', Rule::in($categories->pluck('id')->all())],
            'order_status' => 'nullable|string|in:'.implode(',', SellerReports::STATUSES),
            'payment_method' => ['nullable', 'string', Rule::in($paymentMethods->all())],
            'sort' => 'nullable|string|in:'.implode(',', $sorts),
            'page' => 'nullable|integer|min:1',
        ]);
        $query = clone $base;
        if (! empty($data['order_status'])) {
            $query->whereRaw(self::STATUS_SQL.' = ?', [$data['order_status']]);
        }
        if (! empty($data['payment_method'])) {
            $query->where('orders.payment_method', $data['payment_method']);
        }
        if (! empty($data['product_id']) || ! empty($data['subcategory']) || ! empty($data['category'])) {
            $selected = $products->filter(fn ($p) => (empty($data['product_id']) || $p->id == $data['product_id'])
                && (empty($data['subcategory']) || $p->category_id == $data['subcategory'])
                && (empty($data['category']) || $p->category_id == $data['category'] || $categories->contains('id', $p->category_id)));
            $query->where(fn ($q) => $q->whereIn('order_items.product_id', $selected->pluck('id'))
                ->orWhere(fn ($q) => $q->whereNull('order_items.product_id')->whereIn('order_items.product_slug', $selected->pluck('slug'))));
        }
        SellerDateFilter::apply($query, $request, 'orders.created_at');

        return ['query' => $query, 'products' => $products, 'filterCategories' => $categories, 'mainCategory' => SellerCategories::main($seller), 'paymentMethods' => $paymentMethods, 'criteria' => $data];
    }
}
