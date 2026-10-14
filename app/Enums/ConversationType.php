<?php

namespace App\Enums;

enum ConversationType: string
{
    case StoreInquiry = 'STORE_INQUIRY';
    case Order = 'ORDER';
    case Complaint = 'COMPLAINT';
    case RiderSupport = 'RIDER_SUPPORT';
    case LogisticsSupport = 'LOGISTICS_SUPPORT';
    case AdminSupport = 'ADMIN_SUPPORT';
}
