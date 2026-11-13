<?php

namespace App\Jobs;

use App\Models\ShipmentSmsNotification;
use App\Services\ShipmentSmsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendShipmentSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public function __construct(public readonly int $notificationId) {}
    public function handle(ShipmentSmsProvider $provider): void
    {
        $notification = DB::transaction(function () {
            $locked = ShipmentSmsNotification::lockForUpdate()->find($this->notificationId);
            if (! $locked || ! in_array($locked->status, ['PENDING', 'FAILED'], true)) return null;
            $locked->increment('attempts'); $locked->update(['status' => 'PROCESSING']);
            return $locked->fresh();
        });
        if (! $notification) return;
        try {
            $id = $provider->send($notification);
            ShipmentSmsNotification::whereKey($notification->id)->where('status', 'PROCESSING')->update(['status' => 'SENT', 'provider_message_id' => $id, 'sent_at' => now(), 'failure_reason' => null]);
        } catch (\Throwable $error) {
            ShipmentSmsNotification::whereKey($notification->id)->where('status', 'PROCESSING')->update(['status' => 'FAILED', 'failed_at' => now(), 'failure_reason' => 'Provider delivery failed.']);
            throw $error;
        }
    }
}
