<?php

namespace App\Services;

use App\Models\ShipmentSmsNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShipmentSmsProvider
{
    /** @return string provider message identifier */
    public function send(ShipmentSmsNotification $notification): string
    {
        $driver = (string) config('services.transactional_sms.driver', 'log');
        if (! in_array($driver, ['log', 'test', 'semaphore'], true)) throw new RuntimeException('Unsupported SMS provider.');
        if (in_array($driver, ['log', 'test'], true)) {
            Log::info('Transactional SMS recorded.', ['sms_notification_id' => $notification->id, 'event' => $notification->event, 'recipient' => $notification->recipient_masked]);
            return $driver.'-'.$notification->id;
        }
        if (! config('services.transactional_sms.enabled') || ! config('services.transactional_sms.api_key') || ! config('services.transactional_sms.endpoint')) {
            throw new RuntimeException('SMS provider is not configured.');
        }
        $response = Http::timeout((int) config('services.transactional_sms.timeout', 4))->acceptJson()->asForm()->post(config('services.transactional_sms.endpoint'), [
            'apikey' => config('services.transactional_sms.api_key'), 'number' => $notification->recipient_phone,
            'message' => $notification->body, 'sendername' => config('services.transactional_sms.sender', 'SUKI SHOP'),
        ]);
        if (! $response->successful()) throw new RuntimeException('SMS provider rejected the request.');
        return (string) (data_get($response->json(), 'message_id') ?? data_get($response->json(), 'message_id.0') ?? 'semaphore-'.$notification->id);
    }
}
