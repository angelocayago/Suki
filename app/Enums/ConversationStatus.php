<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Active = 'ACTIVE';
    case Closed = 'CLOSED';
    case Archived = 'ARCHIVED';
}
