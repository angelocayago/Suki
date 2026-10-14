<?php

namespace App\Enums;

enum SellerViolationCategory: string
{
    case CategoryMismatch = 'CATEGORY_MISMATCH';
    case ProhibitedProduct = 'PROHIBITED_PRODUCT';
    case MisleadingListing = 'MISLEADING_LISTING';
    case CounterfeitProduct = 'COUNTERFEIT_PRODUCT';
    case Documentation = 'DOCUMENTATION';
    case RepeatedModerationFailure = 'REPEATED_MODERATION_FAILURE';
    case Other = 'OTHER';
}
