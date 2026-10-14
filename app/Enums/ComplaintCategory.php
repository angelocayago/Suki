<?php

namespace App\Enums;

enum ComplaintCategory: string
{
    case OrderNotReceived = 'ORDER_NOT_RECEIVED';
    case DamagedItem = 'DAMAGED_ITEM';
    case WrongItem = 'WRONG_ITEM';
    case ProductQuality = 'PRODUCT_QUALITY';
    case PaymentConcern = 'PAYMENT_CONCERN';
    case DeliveryConduct = 'DELIVERY_CONDUCT';
    case SellerConduct = 'SELLER_CONDUCT';
    case BuyerConduct = 'BUYER_CONDUCT';
    case Other = 'OTHER';
}
