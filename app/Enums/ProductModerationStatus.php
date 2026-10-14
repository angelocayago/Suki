<?php

namespace App\Enums;

enum ProductModerationStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Hidden = 'HIDDEN';
}
