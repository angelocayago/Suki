<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\{Order, SellerOrder};
use Illuminate\Database\Eloquent\Model;

/** Presentation only. All relationships must be loaded by the caller. */
final class BuyerOrderTracking
{
    private const LABELS = [
        'PLACED' => 'Order placed', 'CONFIRMED' => 'Seller confirmed',
        'PREPARING' => 'Seller preparing', 'READY_FOR_PICKUP' => 'Ready for pickup',
        'PICKED_UP' => 'Picked up', 'AT_SORTING_CENTER' => 'At sorting center',
        'SORTED' => 'Sorting completed', 'ASSIGNED_TO_RIDER' => 'Delivery rider assigned',
        'OUT_FOR_DELIVERY' => 'Out for delivery', 'DELIVERED' => 'Delivered',
        'COMPLETED' => 'Completed', 'DELIVERY_FAILED' => 'Delivery unsuccessful',
        'RTS_INITIATED' => 'Return to sender initiated', 'RTS_RETURNING' => 'Returning to sender',
        'RTS_COMPLETED' => 'Returned to sender', 'CANCELLED' => 'Cancelled',
        'PICKUP_RIDER_ASSIGNED' => 'Pickup rider assigned', 'PICKUP_RIDER_REASSIGNED' => 'Pickup rider reassigned',
        'PICKUP_ACCEPTED' => 'Pickup accepted', 'PICKUP_DECLINED' => 'Pickup assignment declined',
        'DELIVERY_ACCEPTED' => 'Delivery accepted', 'DELIVERY_DECLINED' => 'Delivery assignment declined',
        'DELIVERY_RIDER_REASSIGNED' => 'Delivery rider reassigned',
        'RECEIVED_AT_SORTING_CENTER' => 'Received at sorting center',
        'DELIVERY_ATTEMPT_FAILED' => 'Delivery attempt unsuccessful',
        'DELIVERY_RESCHEDULED' => 'Delivery rescheduled', 'DELIVERY_ISSUE_REPORTED' => 'Delivery issue reported',
        'PROOF_RECORDED' => 'Delivery proof recorded',
    ];

    private const OPERATIONAL = ['SORTED', 'ASSIGNED_TO_RIDER', 'PICKUP_RIDER_ASSIGNED',
        'PICKUP_RIDER_REASSIGNED', 'PICKUP_ACCEPTED', 'PICKUP_DECLINED', 'DELIVERY_ACCEPTED',
        'DELIVERY_DECLINED', 'DELIVERY_RIDER_REASSIGNED', 'RECEIVED_AT_SORTING_CENTER', 'PROOF_RECORDED'];

    private const REASONS = ['Buyer unavailable', 'Buyer unreachable', 'Incorrect or incomplete address',
        'Delivery access issue', 'Buyer requested reschedule', 'Buyer refused delivery',
        'Wrong or incomplete address', 'Damaged parcel', 'Other', 'Other delivery issue'];

    private const CANCELLATION_REASONS = [
        'changed_mind' => 'I changed my mind', 'ordered_by_mistake' => 'I ordered by mistake',
        'found_better_price' => 'I found a better price', 'wrong_product' => 'Wrong product or variation',
        'seller_requested' => 'Seller requested cancellation', 'other' => 'Other',
    ];

    /** Raw reads avoid enum exceptions for historical, unsupported values. */
    public static function status(Model $model): ?string
    {
        if (strtoupper((string) $model->getRawOriginal('status')) === 'CANCELLED') return 'CANCELLED';
        $value = strtoupper((string) $model->getRawOriginal($model instanceof Order ? 'status' : 'workflow_status'));
        return OrderStatus::tryFrom($value)?->value;
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status ?? ''] ?? 'Status unavailable';
    }

    public function present(Order $order): array
    {
        $status = self::status($order);
        $placed = $this->time($order->getRawOriginal('placed_at'));
        $created = $placed ?? $this->time($order->getRawOriginal('created_at'));
        $placement = $created ? $this->entry('PLACED', $created, 0, $placed ? 'Order placed' : 'Order record created') : null;
        $packages = [];
        // getRelation(), rather than relationship properties, prevents lazy queries.
        foreach ($order->getRelation('sellerOrders')->sortBy('id') as $sellerOrder) {
            $packages[] = $this->package($order, $sellerOrder);
        }
        return ['number' => $order->order_number, 'status' => self::label($status),
            'placed' => $placement, 'cancelled' => $status === 'CANCELLED',
            'packages' => $packages, 'legacy' => $packages ? [] : $this->legacy($order, $placement)];
    }

    private function package(Order $order, SellerOrder $sellerOrder): array
    {
        $shipment = $sellerOrder->getRelation('shipment');
        $cancelled = self::status($order) === 'CANCELLED' || self::status($sellerOrder) === 'CANCELLED'
            || ($shipment && self::status($shipment) === 'CANCELLED');
        $sellerStatus = $cancelled ? 'CANCELLED' : self::status($sellerOrder);
        $deliveryStatus = $cancelled ? 'CANCELLED' : ($shipment ? self::status($shipment) : null);
        $status = $shipment ? $deliveryStatus : $sellerStatus;
        $entries = [];
        $events = $shipment ? $shipment->getRelation('events') : collect();
        foreach ($events as $event) {
            $code = (string) $event->getRawOriginal('status');
            // Financial records, unknown event names and all raw notes are excluded.
            if (!isset(self::LABELS[$code]) || $code === 'CANCELLED') continue;
            $time = $this->time($event->getRawOriginal('occurred_at'));
            if (!$time) continue;
            $entry = $this->entry($code, $time, (int) $event->id);
            if (in_array($code, ['DELIVERY_ATTEMPT_FAILED', 'DELIVERY_ISSUE_REPORTED'], true)) {
                $prefix = explode(':', (string) $event->getRawOriginal('note'), 2)[0];
                $entry['reason'] = in_array($prefix, self::REASONS, true) ? $prefix : null;
                if ($code === 'DELIVERY_ATTEMPT_FAILED' && (int) $event->attempt > 0) $entry['label'] .= ' · Attempt '.(int) $event->attempt;
            }
            $entries[] = $entry;
        }
        foreach (OrderStatus::cases() as $stage) {
            $column = $stage->timestampColumn();
            if (!$column) continue;
            $this->addMilestone($entries, $stage->value, $sellerOrder->getRawOriginal($column));
        }
        if ($shipment) {
            $this->addMilestone($entries, 'PICKUP_ACCEPTED', $shipment->getRawOriginal('pickup_accepted_at'));
            $this->addMilestone($entries, 'DELIVERY_ACCEPTED', $shipment->getRawOriginal('delivery_accepted_at'));
            $this->addMilestone($entries, 'PROOF_RECORDED', $shipment->getRawOriginal('proof_delivered_at'));
        }
        if ($cancelled) {
            $this->addMilestone($entries, 'CANCELLED', $order->getRawOriginal('cancelled_at'));
        }
        $this->sort($entries);
        $lastUpdate = $this->time($sellerOrder->getRawOriginal('status_changed_at'));
        $shipmentUpdate = $shipment ? $this->time($shipment->getRawOriginal('status_changed_at')) : null;
        if ($shipmentUpdate && (!$lastUpdate || $shipmentUpdate['sort'] > $lastUpdate['sort'])) $lastUpdate = $shipmentUpdate;
        $exception = in_array($status, ['DELIVERY_FAILED', 'RTS_INITIATED', 'RTS_RETURNING', 'RTS_COMPLETED', 'CANCELLED'], true);
        $inconsistent = !$cancelled && $shipment && $sellerStatus !== $deliveryStatus;
        return [
            'store' => $sellerOrder->getRelation('seller')?->name ?? 'Store unavailable',
            'tracking_code' => $shipment?->tracking_code,
            'seller_status' => self::label($sellerStatus),
            'delivery_status' => $shipment ? self::label($deliveryStatus) : 'Shipment not available',
            'current' => self::label($status), 'exception' => $exception,
            'message' => $inconsistent ? 'The recorded seller and delivery statuses differ. Please check the updates below.' : $this->message($status, (bool) $shipment),
            'inconsistent' => $inconsistent,
            'updated' => $lastUpdate,
            'scheduled' => $shipment ? $this->time($shipment->getRawOriginal('rescheduled_at')) : null,
            'cancel_reason' => $cancelled ? (self::CANCELLATION_REASONS[$order->cancel_reason ?? ''] ?? null) : null,
            'main' => array_values(array_filter($entries, fn ($entry) => !$entry['operational'])),
            'activity' => array_values(array_filter($entries, fn ($entry) => $entry['operational'])),
        ];
    }

    private function addMilestone(array &$entries, string $code, mixed $value): void
    {
        $time = $this->time($value);
        if (!$time) return;
        // Only matching status AND instant represent the same milestone. Keep retries.
        foreach ($entries as $entry) {
            if ($entry['code'] === $code && $entry['time']['sort'] === $time['sort']) return;
        }
        $entries[] = $this->entry($code, $time, 0);
    }

    private function entry(string $code, array $time, int $id, ?string $label = null): array
    {
        return ['code' => $code, 'label' => $label ?? self::label($code), 'time' => $time,
            'id' => $id, 'reason' => null, 'operational' => in_array($code, self::OPERATIONAL, true),
            'exception' => in_array($code, ['DELIVERY_ATTEMPT_FAILED', 'DELIVERY_ISSUE_REPORTED', 'DELIVERY_RESCHEDULED', 'DELIVERY_FAILED', 'RTS_INITIATED', 'RTS_RETURNING', 'RTS_COMPLETED', 'CANCELLED'], true)];
    }

    private function sort(array &$entries): void
    {
        usort($entries, fn ($a, $b) => [$a['time']['sort'], $a['id'], $a['code']] <=> [$b['time']['sort'], $b['id'], $b['code']]);
    }

    private function legacy(Order $order, ?array $placement): array
    {
        $entries = $placement ? [$placement] : [];
        // No package graph: do not substitute aggregate fulfillment dates.
        if (self::status($order) === 'CANCELLED') $this->addMilestone($entries, 'CANCELLED', $order->getRawOriginal('cancelled_at'));
        $this->sort($entries);
        return $entries;
    }

    private function time(mixed $value): ?array
    {
        try { $date = ManilaTime::local($value); } catch (\Throwable) { return null; }
        return $date ? ['iso' => $date->toIso8601String(), 'label' => $date->format('M j, Y · g:i A').' PHT',
            'sort' => $date->utc()->format('Y-m-d H:i:s.u')] : null;
    }

    private function message(?string $status, bool $shipment): string
    {
        if ($status === 'CANCELLED') return 'This package is cancelled. Delivery will not continue.';
        if (!$shipment) return 'Your seller is still preparing this package. Tracking will appear once it ships.';
        return match ($status) {
            'PLACED' => 'Your order is awaiting seller confirmation.',
            'CONFIRMED', 'PREPARING' => 'Your seller is preparing this package.',
            'READY_FOR_PICKUP' => 'This package is ready for logistics pickup.',
            'PICKED_UP', 'AT_SORTING_CENTER', 'SORTED' => 'This package is moving through logistics.',
            'ASSIGNED_TO_RIDER' => 'This package is awaiting a delivery update.',
            'OUT_FOR_DELIVERY' => 'This package is out for delivery.',
            'DELIVERED' => 'Delivery is recorded. Receipt controls remain in Order Details.',
            'COMPLETED' => 'This package is complete.',
            'DELIVERY_FAILED' => 'Delivery was unsuccessful. No new delivery time is confirmed by this status.',
            'RTS_INITIATED', 'RTS_RETURNING' => 'This package is being returned to the seller.',
            'RTS_COMPLETED' => 'This package has been returned to the seller.',
            default => 'Tracking details are incomplete. Please check again later.',
        };
    }
}
