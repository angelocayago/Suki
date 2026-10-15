<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use RuntimeException;

class NullSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        throw new RuntimeException('SMS delivery is not configured.');
    }
}
