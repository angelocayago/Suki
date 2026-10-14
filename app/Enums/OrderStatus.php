<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'PLACED';
    case Confirmed = 'CONFIRMED';
    case Preparing = 'PREPARING';
    case ReadyForPickup = 'READY_FOR_PICKUP';
    case PickedUp = 'PICKED_UP';
    case AtSortingCenter = 'AT_SORTING_CENTER';
    case Sorted = 'SORTED';
    case AssignedToRider = 'ASSIGNED_TO_RIDER';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case Delivered = 'DELIVERED';
    case Completed = 'COMPLETED';

    public function next(): ?self
    {
        return match ($this) {
            self::Placed => self::Confirmed,
            self::Confirmed => self::Preparing,
            self::Preparing => self::ReadyForPickup,
            self::ReadyForPickup => self::PickedUp,
            self::PickedUp => self::AtSortingCenter,
            self::AtSortingCenter => self::Sorted,
            self::Sorted => self::AssignedToRider,
            self::AssignedToRider => self::OutForDelivery,
            self::OutForDelivery => self::Delivered,
            self::Delivered => self::Completed,
            self::Completed => null,
        };
    }

    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Placed => null,
            self::Confirmed => 'confirmed_at',
            self::Preparing => 'preparing_at',
            self::ReadyForPickup => 'ready_for_pickup_at',
            self::PickedUp => 'picked_up_at',
            self::AtSortingCenter => 'at_sorting_center_at',
            self::Sorted => 'sorted_at',
            self::AssignedToRider => 'assigned_to_rider_at',
            self::OutForDelivery => 'out_for_delivery_at',
            self::Delivered => 'delivered_at',
            self::Completed => 'completed_at',
        };
    }

    public function legacySellerStatus(): string
    {
        return match ($this) {
            self::Placed => 'pending',
            self::Confirmed => 'accepted',
            self::Preparing => 'packed',
            self::ReadyForPickup => 'ready_to_ship',
            self::PickedUp,
            self::AtSortingCenter,
            self::Sorted,
            self::AssignedToRider,
            self::OutForDelivery => 'shipped',
            self::Delivered => 'delivered',
            self::Completed => 'completed',
        };
    }

    public function legacyShipmentStatus(): string
    {
        return match ($this) {
            self::Placed,
            self::Confirmed,
            self::Preparing,
            self::ReadyForPickup => 'unassigned',
            self::PickedUp => 'picked_up',
            self::AtSortingCenter,
            self::Sorted => 'in_transit',
            self::AssignedToRider => 'assigned',
            self::OutForDelivery => 'out_for_delivery',
            self::Delivered,
            self::Completed => 'delivered',
        };
    }

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }
}
