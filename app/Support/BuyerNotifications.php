<?php

namespace App\Support;

use App\Models\{Order, SellerOrder, Shipment, User};
use Illuminate\Support\Collection;

/** Resolve stored notification context using the recipient's existing order ownership. */
class BuyerNotifications
{
    public function recent(User $buyer): Collection
    {
        return $buyer->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
    }

    public function present(User $buyer, Collection $notifications): array
    {
        return $notifications->map(function ($notification) use ($buyer) {
            $data = is_array($notification->data) ? $notification->data : [];
            $context = is_array($data['context'] ?? null) ? $data['context'] : [];
            [$destination, $reference] = $this->destination($buyer, $context);
            return [
                'id' => $notification->id,
                'title' => is_string($data['title'] ?? null) ? $data['title'] : 'Notification',
                'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
                'reference' => $reference, 'destination' => $destination,
                'unread' => $notification->read_at === null,
                'date' => ManilaTime::format($notification->created_at),
                'iso_date' => $notification->created_at?->toIso8601String(),
                'open_url' => route('notifications.open', $notification->id),
            ];
        })->all();
    }

    private function destination(User $buyer, array $context): array
    {
        $parcel = null;
        if (isset($context['seller_order_id'])) {
            $parcel = SellerOrder::with('order')->whereKey($this->id($context['seller_order_id']))
                ->whereHas('order', fn ($query) => $query->where('buyer_id', $buyer->id))->first();
            if (! $parcel) return [null, null];
        } elseif (isset($context['shipment_id'])) {
            $shipment = Shipment::with('sellerOrder.order')->whereKey($this->id($context['shipment_id']))
                ->whereHas('sellerOrder.order', fn ($query) => $query->where('buyer_id', $buyer->id))->first();
            $parcel = $shipment?->sellerOrder;
            if (! $parcel) return [null, null];
        }
        $order = $parcel?->order;
        if (isset($context['order_id'])) {
            $orderId = $this->id($context['order_id']);
            if ($parcel && $parcel->order_id !== $orderId) return [null, null];
            $order ??= Order::where('buyer_id', $buyer->id)->whereKey($orderId)->first();
        }
        if ($order) {
            $status = is_string($context['status'] ?? null) ? strtoupper($context['status']) : '';
            $tracking = in_array($status, ['PICKED_UP', 'AT_SORTING_CENTER', 'SORTED', 'ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'DELIVERED'], true);
            return [$parcel && $tracking
                ? route('buyer.seller-order.track', [$order->id, $parcel->id])
                : route('buyer.order-details', $order->order_number), $order->order_number];
        }
        if (isset($context['order_id'])) return [null, null];
        return isset($context['voucher_id']) ? [route('buyer.vouchers.index'), null] : [null, null];
    }

    private function id(mixed $value): ?int
    {
        return is_int($value) || is_string($value)
            ? (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null) : null;
    }
}
