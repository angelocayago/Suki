<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'TEXT';
    case System = 'SYSTEM';
}
