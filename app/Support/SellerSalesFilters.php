<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Read-only Sales queries. No accounting or workflow writes. */
class SellerSalesFilters
{
    private const STATUS_SQL = "CASE WHEN seller_orders.status = 'cancelled' OR EXISTS (SELECT 1 FROM orders WHERE orders.id = seller_orders.order_id AND UPPER(orders.status) = 'CANCELLED') THEN 'CANCELLED' ELSE seller_orders.workflow_status END";
    private const AMOUNT_SQL = '(COALESCE(seller_orders.subtotal_minor, 0) + COALESCE(seller_orders.shipping_fee_minor, 0) - COALESCE((SELECT SUM(discount_minor) FROM voucher_redemptions WHERE voucher_redemptions.seller_order_id = seller_orders.id), 0))';
    private const COMPLETED = ['DELIVERED', 'COMPLETED'];
    private const EXCLUDED = ['CANCELLED', 'RTS_COMPLETED', 'DELIVERY_FAILED'];
    private const PENDING = ['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP', 'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'RTS_INITIATED', 'RTS_RETURNING'];
    private const COLLECTED_SQL = "EXISTS (SELECT 1 FROM orders WHERE orders.id = seller_orders.order_id AND LOWER(orders.payment_method) = 'cod') AND EXISTS (SELECT 1 FROM shipments WHERE shipments.seller_order_id = seller_orders.id AND shipments.cod_collected = TRUE)";

    public static function groups(): array
    {
        return [
            'date' => ['any'=>'Any date', 'today'=>'Today', '7d'=>'Last 7 days', '30d'=>'Last 30 days', 'custom'=>'Custom range'],
            'status' => ['all'=>'All', 'completed'=>'Completed', 'pending'=>'Pending / To Fulfill', 'cancelled'=>'Cancelled', 'returned'=>'Returned', 'failed'=>'Failed Delivery'],
            'payment' => ['all'=>'All', 'paid'=>'Paid', 'unpaid'=>'Unpaid'],
            'fulfillment' => ['all'=>'All', 'to_ship'=>'To Ship', 'shipped'=>'Shipped', 'delivered'=>'Delivered', 'returned'=>'Returned'],
            'voucher' => ['all'=>'All', 'with'=>'With Voucher', 'without'=>'Without Voucher'],
        ];
    }

    public function listing(Seller $seller, Request $request): array
    {
        $rules = [
            'search'=>['nullable', 'string', 'max:100'],
            'sort'=>['nullable', Rule::in(['newest', 'oldest', 'highest', 'lowest'])],
            'min'=>['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'max'=>['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'product'=>['nullable', 'integer', Rule::exists('products', 'id')->where('seller_id', $seller->id)],
            'page'=>['nullable', 'integer', 'min:1'],
            'from'=>['exclude_unless:date,custom', 'required', 'date_format:Y-m-d'],
            'to'=>['exclude_unless:date,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
        foreach (self::groups() as $key => $options) $rules[$key] = ['nullable', Rule::in(array_keys($options))];
        $criteria = $request->validate($rules);
        $criteria = array_filter(Arr::except($criteria, 'page'), fn ($value) => $value !== null && $value !== '');
        foreach (['date'=>'any', 'status'=>'all', 'payment'=>'all', 'fulfillment'=>'all', 'voucher'=>'all', 'sort'=>'newest'] as $key=>$default) {
            if (($criteria[$key] ?? null) === $default) unset($criteria[$key]);
        }
        if (isset($criteria['search'])) {
            $criteria['search'] = trim($criteria['search']);
            if ($criteria['search'] === '') unset($criteria['search']);
        }
        if (isset($criteria['min'], $criteria['max']) && self::minor($criteria['min']) > self::minor($criteria['max'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['max'=>'Maximum amount must be at least the minimum amount.']);
        }

        $query = SellerOrder::query()->where('seller_orders.seller_id', $seller->id);
        $this->dates($query, $criteria);
        if (isset($criteria['status'])) {
            $states = match ($criteria['status']) {
                'completed'=>self::COMPLETED, 'pending'=>self::PENDING,
                'cancelled'=>['CANCELLED'], 'returned'=>['RTS_COMPLETED'], 'failed'=>['DELIVERY_FAILED'],
            };
            $this->states($query, $states);
        }
        if (isset($criteria['payment'])) {
            // Parent payment status is written by checkout/delivery. A shop's
            // own COD collection is also evidence even if sibling shops are unpaid.
            $query->where(function ($payment) use ($criteria) {
                if ($criteria['payment'] === 'paid') {
                    $payment->whereHas('order', fn ($order) => $order->whereRaw('UPPER(payment_status) = ?', ['PAID']))
                        ->orWhereRaw('('.self::COLLECTED_SQL.')');
                } else {
                    $payment->whereHas('order', fn ($order) => $order->whereRaw('UPPER(payment_status) = ?', ['UNPAID']))
                        ->whereRaw('NOT ('.self::COLLECTED_SQL.')');
                }
            });
        }
        if (isset($criteria['fulfillment'])) {
            $stages = match ($criteria['fulfillment']) {
                'to_ship'=>['PLACED', 'CONFIRMED', 'PREPARING', 'READY_FOR_PICKUP'],
                'shipped'=>['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY'],
                'delivered'=>self::COMPLETED, 'returned'=>['RTS_COMPLETED'],
            };
            $query->whereRaw(self::STATUS_SQL." != 'CANCELLED'")->where(function ($fulfillment) use ($stages, $criteria) {
                $fulfillment->whereHas('shipment', fn ($shipment) => $shipment->whereIn('workflow_status', $stages)->where(fn ($s) => $s->whereNull('status')->orWhere('status', '!=', 'cancelled')));
                if ($criteria['fulfillment'] === 'to_ship') {
                    $fulfillment->orWhere(function ($unshipped) use ($stages) {
                        $unshipped->whereDoesntHave('shipment');
                        $this->states($unshipped, $stages);
                    });
                }
            });
        }
        foreach (['min'=>'>=', 'max'=>'<='] as $key=>$operator) {
            if (isset($criteria[$key])) $query->whereRaw(self::AMOUNT_SQL.' '.$operator.' ?', [self::minor($criteria[$key])]);
        }
        if (isset($criteria['product'])) $query->whereHas('items', fn ($items) => $items->where('product_id', $criteria['product']));
        if (isset($criteria['voucher'])) {
            $criteria['voucher'] === 'with' ? $query->whereHas('voucherRedemptions') : $query->whereDoesntHave('voucherRedemptions');
        }
        if (isset($criteria['search'])) {
            $term = self::like($criteria['search']);
            $query->where(function ($search) use ($term, $criteria) {
                $search->whereHas('order', function ($order) use ($term) {
                    $order->whereRaw("LOWER(order_number) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("LOWER(reference) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereRaw("LOWER(recipient_name) LIKE ? ESCAPE '!'", [$term])
                        ->orWhereHas('buyer', fn ($buyer) => $buyer->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                            ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ? ESCAPE '!'", [$term]));
                })->orWhereHas('items', fn ($items) => $items->whereRaw("LOWER(product_name) LIKE ? ESCAPE '!'", [$term]));
                if (ctype_digit($criteria['search']) && strlen($criteria['search']) < 19) $search->orWhere('seller_orders.id', (int) $criteria['search'])->orWhere('seller_orders.order_id', (int) $criteria['search']);
            });
        }

        // Preserve this page's existing total-based metrics (including delivered
        // orders). These are order totals, not SellerSalesService's net earnings.
        $completed = (clone $query); $this->states($completed, self::COMPLETED);
        $pending = (clone $query); $this->states($pending, self::PENDING);
        $excluded = (clone $query); $this->states($excluded, self::EXCLUDED);
        $summary = [
            'completed_minor'=>(int) (clone $completed)->sum(\Illuminate\Support\Facades\DB::raw(self::AMOUNT_SQL)),
            'completed_count'=>$completed->count(),
            'pending_minor'=>(int) $pending->sum(\Illuminate\Support\Facades\DB::raw(self::AMOUNT_SQL)),
            'cancelled_count'=>$excluded->count(),
        ];
        $summary['average_minor'] = $summary['completed_count'] ? intdiv($summary['completed_minor'] + intdiv($summary['completed_count'], 2), $summary['completed_count']) : 0;
        $query->select('seller_orders.*')->selectRaw(self::STATUS_SQL.' AS sales_status')->selectRaw(self::AMOUNT_SQL.' AS sales_amount_minor');
        $sort = $criteria['sort'] ?? 'newest';
        if (in_array($sort, ['highest', 'lowest'], true)) $query->orderBy('sales_amount_minor', $sort === 'highest' ? 'desc' : 'asc');
        $direction = $sort === 'oldest' ? 'asc' : 'desc';
        $paginator = $query->with(['order', 'items'])->orderBy('seller_orders.created_at', $direction)->orderBy('seller_orders.id', $direction)->paginate(15)->appends($criteria);
        $selectedProduct = isset($criteria['product']) ? Product::withTrashed()->where('seller_id', $seller->id)->find($criteria['product']) : null;
        $chips = $this->chips($criteria, $selectedProduct?->name);

        return compact('criteria', 'summary', 'paginator', 'selectedProduct', 'chips') + ['groups'=>self::groups()];
    }

    public function products(Seller $seller, string $term): array
    {
        return Product::withTrashed()->where('seller_id', $seller->id)
            ->when($term !== '', fn ($query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [self::like($term)]))
            ->orderBy('name')->orderBy('id')->limit(15)->get(['id', 'name'])->toArray();
    }

    private function dates($query, array $criteria): void
    {
        if (!isset($criteria['date'])) return;
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        [$from, $until] = match ($criteria['date']) {
            'today'=>[$today, $today->addDay()],
            '7d'=>[$today->subDays(6), $today->addDay()],
            '30d'=>[$today->subDays(29), $today->addDay()],
            'custom'=>[CarbonImmutable::parse($criteria['from'], 'Asia/Manila')->startOfDay(), CarbonImmutable::parse($criteria['to'], 'Asia/Manila')->startOfDay()->addDay()],
        };
        $query->where('seller_orders.created_at', '>=', $from->setTimezone(config('app.timezone')))
            ->where('seller_orders.created_at', '<', $until->setTimezone(config('app.timezone')));
    }

    private function states($query, array $states): void
    {
        $query->whereRaw(self::STATUS_SQL.' IN ('.implode(',', array_fill(0, count($states), '?')).')', $states);
    }

    private static function like(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($value)).'%';
    }

    private static function minor(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function money(int $minor): string
    {
        $absolute = abs($minor);
        return ($minor < 0 ? '-' : '').number_format(intdiv($absolute, 100)).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function chips(array $criteria, ?string $productName): array
    {
        $chips = [];
        foreach ($criteria as $key=>$value) {
            if (in_array($key, ['from', 'to', 'max'], true) || ($key === 'min' && isset($chips['amount']))) continue;
            $label = match ($key) {
                'date'=>$value === 'custom' ? $criteria['from'].' to '.$criteria['to'] : self::groups()['date'][$value],
                'status', 'payment', 'fulfillment', 'voucher'=>ucfirst($key).': '.self::groups()[$key][$value],
                'min'=>'Amount: '.self::money(self::minor($value)).' PHP'.(isset($criteria['max']) ? ' – '.self::money(self::minor($criteria['max'])).' PHP' : '+'),
                'product'=>'Product: '.$productName,
                'search'=>'Search: '.$value,
                'sort'=>'Sort: '.['oldest'=>'Oldest', 'highest'=>'Highest Amount', 'lowest'=>'Lowest Amount'][$value],
            };
            $remove = match ($key) { 'date'=>['date', 'from', 'to'], 'min'=>['min', 'max'], default=>[$key] };
            $chips[$key === 'min' ? 'amount' : $key] = ['label'=>$label, 'url'=>route('seller.sales', Arr::except($criteria, $remove))];
        }
        if (isset($criteria['max']) && !isset($criteria['min'])) {
            $chips['amount'] = ['label'=>'Maximum '.self::money(self::minor($criteria['max'])).' PHP', 'url'=>route('seller.sales', Arr::except($criteria, ['min', 'max']))];
        }
        return $chips;
    }
}
