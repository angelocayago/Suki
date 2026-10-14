<?php

namespace App\Enums;

enum ComplaintWaitingOn: string
{
    case Buyer = 'BUYER';
    case Seller = 'SELLER';
    case PickupRider = 'PICKUP_RIDER';
    case DeliveryRider = 'DELIVERY_RIDER';
    case Logistics = 'LOGISTICS';
    case Other = 'OTHER';
}
