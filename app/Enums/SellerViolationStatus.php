<?php

namespace App\Enums;

enum SellerViolationStatus: string
{
    case Open = 'OPEN';
    case Resolved = 'RESOLVED';
    case Dismissed = 'DISMISSED';
}
