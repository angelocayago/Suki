<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\LogisticsProvider;
use App\Models\Shipment;

class LogisticsShipmentCounters
{
    public function forProvider(LogisticsProvider $provider): array
    {
        $shipments = Shipment::query()->with(['destinationHub', 'sortingZone.hub', 'sellerOrder.order'])
            ->where('logistics_provider_id', $provider->id)->get();
        $count = fn (OrderStatus ...$statuses) => $shipments->filter(fn (Shipment $shipment) => in_array($shipment->workflow_status, $statuses, true))->count();
        return [
            'ready_for_pickup' => $shipments->filter(fn (Shipment $s) => $s->workflow_status === OrderStatus::ReadyForPickup && ! $s->pickup_rider_id)->count(),
            'pickup_rider_assigned' => $shipments->filter(fn (Shipment $s) => $s->workflow_status === OrderStatus::ReadyForPickup && $s->pickup_rider_id)->count(),
            'picked_up_en_route' => $count(OrderStatus::PickedUp),
            'at_sorting_center' => $count(OrderStatus::AtSortingCenter),
            'ready_for_hub_transfer' => $count(OrderStatus::Sorted),
            'in_transit_to_hub' => $count(OrderStatus::InTransitToHub),
            'at_destination_hub' => $count(OrderStatus::AtDestinationHub),
            'ready_for_delivery_rider' => $shipments->filter(fn (Shipment $s) =>
                ($s->workflow_status === OrderStatus::AtDestinationHub && ! $s->rider_id)
                || ($s->workflow_status === OrderStatus::AssignedToRider && ! $s->rider_id)
            )->count(),
            'delivery_rider_assigned' => $count(OrderStatus::AssignedToRider),
            'out_for_delivery' => $count(OrderStatus::OutForDelivery),
            'delivered' => $count(OrderStatus::Delivered, OrderStatus::Completed),
            'routing_exceptions' => $shipments->filter(fn (Shipment $s) =>
                ($s->workflow_status === OrderStatus::AtSortingCenter && (! $s->sellerOrder?->order || ! app(SortingZoneResolver::class)->resolve($provider, $s->sellerOrder->order)['zone']))
                || (in_array($s->workflow_status, [OrderStatus::Sorted, OrderStatus::InTransitToHub, OrderStatus::AtDestinationHub, OrderStatus::AssignedToRider], true)
                    && (! $s->destinationHub?->is_active || (int) $s->destinationHub?->logistics_provider_id !== (int) $provider->id))
                || filled($s->routing_exception_reason)
            )->count(),
        ];
    }
}
