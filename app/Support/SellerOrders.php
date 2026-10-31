<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Seller;
use App\Models\SellerOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Seller-only queries and presentation. All writes stay in OrderWorkflowService. */
class SellerOrders
{
    private const STATUS_SQL = "CASE WHEN seller_orders.status = 'cancelled' OR EXISTS (SELECT 1 FROM orders WHERE orders.id = seller_orders.order_id AND UPPER(orders.status) = 'CANCELLED') THEN 'CANCELLED' ELSE seller_orders.workflow_status END";
    private const SHIPMENT_STATUS_SQL = "CASE WHEN shipments.status = 'cancelled' THEN 'CANCELLED' ELSE shipments.workflow_status END";

    public static function statuses(): array
    {
        return collect(OrderStatus::cases())->mapWithKeys(fn ($status) => [
            $status->value => match ($status) {
                OrderStatus::Placed => 'To confirm',
                OrderStatus::RtsInitiated => 'Return initiated',
                OrderStatus::RtsReturning => 'Returning to Seller',
                OrderStatus::RtsCompleted => 'Returned to Seller',
                default => ucwords(str_replace('_', ' ', strtolower($status->value))),
            },
        ])->all() + ['CANCELLED' => 'Cancelled'];
    }

    public static function shipmentStatuses(): array
    {
        return array_replace(self::statuses(), [
            'PLACED' => 'Shipment created',
            'CONFIRMED' => 'Order confirmed',
            'READY_FOR_PICKUP' => 'Awaiting pickup',
        ]);
    }

    private function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(self::statuses()))],
            'shipment_status' => ['nullable', Rule::in([...array_keys(self::shipmentStatuses()), 'NO_SHIPMENT'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function criteria(array $input): array
    {
        $rules = $this->rules();
        if (filled($input['from'] ?? null)) {
            $rules['to'][] = 'after_or_equal:from';
        }

        return Validator::make($input, $rules)->validate();
    }

    public function listing(Seller $seller, Request $request): array
    {
        $criteria = $this->criteria($request->query());
        $query = $seller->orders();
        if (filled($criteria['search'] ?? null)) {
            $search = trim($criteria['search']);
            $query->where(function ($query) use ($search) {
                $query->whereHas('order', fn ($parent) => $parent->whereRaw('LOWER(order_number) LIKE ?', ['%'.mb_strtolower($search).'%']));
                if (ctype_digit($search) && strlen($search) < 19) {
                    $query->orWhere('seller_orders.id', (int) $search);
                }
            });
        }
        if (filled($criteria['status'] ?? null)) {
            $query->whereRaw(self::STATUS_SQL.' = ?', [$criteria['status']]);
        }
        if (filled($criteria['shipment_status'] ?? null)) {
            $criteria['shipment_status'] === 'NO_SHIPMENT'
                ? $query->whereDoesntHave('shipment')
                : $query->whereHas('shipment', fn ($shipment) => $shipment->whereRaw(self::SHIPMENT_STATUS_SQL.' = ?', [$criteria['shipment_status']]));
        }
        if (filled($criteria['from'] ?? null)) {
            $query->where('seller_orders.created_at', '>=', CarbonImmutable::parse($criteria['from'], 'Asia/Manila')->startOfDay()->setTimezone(config('app.timezone')));
        }
        if (filled($criteria['to'] ?? null)) {
            $query->where('seller_orders.created_at', '<', CarbonImmutable::parse($criteria['to'], 'Asia/Manila')->addDay()->startOfDay()->setTimezone(config('app.timezone')));
        }
        $summaryQuery = (clone $query)->selectRaw('COUNT(*) AS total');
        foreach (['pending' => ['PLACED'], 'processing' => ['CONFIRMED', 'PREPARING'], 'ready' => ['READY_FOR_PICKUP'], 'completed' => ['COMPLETED'], 'attention' => ['CANCELLED', 'DELIVERY_FAILED', 'RTS_INITIATED', 'RTS_RETURNING', 'RTS_COMPLETED']] as $key => $values) {
            $placeholders = implode(',', array_fill(0, count($values), '?'));
            $summaryQuery->selectRaw('COALESCE(SUM(CASE WHEN '.self::STATUS_SQL.' IN ('.$placeholders.') THEN 1 ELSE 0 END), 0) AS '.$key, $values);
        }
        $summary = $summaryQuery->first();
        $direction = ($criteria['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $paginator = $query->with(['order', 'items', 'shipment', 'voucherRedemptions'])
            ->orderBy('seller_orders.created_at', $direction)->orderBy('seller_orders.id', $direction)
            ->paginate(15)->appends(Arr::except($criteria, 'page'));
        $paginator->through(fn ($order) => $this->present($order));

        return compact('criteria', 'summary', 'paginator') + ['statuses' => self::statuses(), 'shipmentStatuses' => self::shipmentStatuses()];
    }

    public function backUrl(Request $request): string
    {
        try {
            $input = $request->query('list', []);
            $criteria = $this->criteria(is_array($input) ? $input : []);
        } catch (\Illuminate\Validation\ValidationException) {
            $criteria = [];
        }

        return route('seller.orders', $criteria);
    }

    public function present(SellerOrder $record, bool $details = false): array
    {
        $parent = $record->order;
        $status = $record->status === 'cancelled' || strtoupper((string) $parent?->status) === 'CANCELLED'
            ? 'CANCELLED' : (string) $record->getRawOriginal('workflow_status');
        $next = ($record->status !== 'cancelled' && strtoupper((string) $parent?->status) !== 'CANCELLED') ? match ($status) {
            'PLACED' => ['CONFIRMED', 'Confirm order'],
            'CONFIRMED' => ['PREPARING', 'Start preparing'],
            'PREPARING' => ['READY_FOR_PICKUP', 'Ready for pickup'],
            default => null,
        } : null;
        $discount = (int) $record->voucherRedemptions->sum('discount_minor');
        $address = is_array($parent?->shipping_address) ? $parent->shipping_address : [];

        return [
            'id' => $record->id, 'reference' => $parent?->order_number ?: 'Seller order #'.$record->id,
            'date' => $record->created_at?->setTimezone('Asia/Manila'),
            'status' => $status, 'status_label' => self::statuses()[$status] ?? 'Unknown workflow',
            'recipient' => $parent?->recipient_name ?: 'Not recorded',
            'payment_method' => $parent?->payment_method ?: 'Not recorded',
            'payment_status' => $parent?->payment_status ?: 'Not recorded',
            'subtotal_minor' => $record->subtotal_minor, 'shipping_minor' => $record->shipping_fee_minor,
            'discount_minor' => $discount, 'total_minor' => $record->subtotal_minor + $record->shipping_fee_minor - $discount,
            'next_action' => $next,
            // Item records belong to this SellerOrder, never the parent's other shops.
            'items' => $record->items->map(fn ($item) => [
                'name' => $item->product_name ?: 'Not recorded', 'variant' => $item->variant_name,
                'options' => is_array($item->variation) ? Arr::dot($item->variation) : [],
                'quantity' => $item->quantity, 'unit_price_minor' => $item->unit_price_minor,
                'recorded_line_total' => $item->line_total,
            ]),
            'shipment_id' => $record->shipment?->id, 'tracking' => $record->shipment?->tracking_code,
            'shipment_status' => self::shipmentStatuses()[$record->shipment?->status === 'cancelled' ? 'CANCELLED' : $record->shipment?->getRawOriginal('workflow_status')] ?? ($record->shipment ? 'Not recorded' : 'No shipment yet'),
            'phone' => $details ? $parent?->recipient_phone : null,
            'address' => $details ? collect([
                $parent?->house_number ?? ($address['house_number'] ?? null),
                $parent?->street_address ?? ($address['street'] ?? null), $parent?->barangay ?? ($address['barangay'] ?? null),
                $parent?->municipality ?? ($address['municipality'] ?? null), $parent?->province ?? ($address['province'] ?? null),
                $parent?->postal_code ?? ($address['postal_code'] ?? null),
            ])->filter()->implode(', ') : null,
            'events' => $details && $record->shipment ? $record->shipment->events : collect(),
        ];
    }
}
