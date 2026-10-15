<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        $endpoint = config('services.sms.endpoint');
        $token = config('services.sms.token');

        if (! $endpoint || ! $token) {
            throw new RuntimeException('SMS delivery is not configured.');
        }

        Http::asJson()->withToken($token)->post($endpoint, [
            'to' => '+63'.substr($phone, 1),
            'message' => $message,
            'sender' => config('services.sms.sender'),
        ])->throw();
    }
}
