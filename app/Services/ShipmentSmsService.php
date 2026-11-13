<?php

namespace App\Services;

use App\Jobs\SendShipmentSms;
use App\Models\{Shipment, ShipmentSmsNotification, User};
use App\Support\PhilippineMobileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Transactional shipment SMS outbox. It never changes workflow state. */
class ShipmentSmsService
{
    public const DELIVERY_ACCEPTED = 'DELIVERY_ACCEPTED';
    public const OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    public const DELIVERY_ATTEMPT_FAILED = 'DELIVERY_ATTEMPT_FAILED';
    public const DELIVERED = 'DELIVERED';

    public function queue(Shipment $shipment, string $event, ?User $actor = null): ShipmentSmsNotification
    {
        // Allows a rolling deployment to keep fulfillment working until this
        // additive outbox migration has been applied everywhere.
        if (! Schema::hasTable('shipment_sms_notifications')) {
            return new ShipmentSmsNotification(['shipment_id' => $shipment->id, 'event' => $event, 'status' => 'CANCELLED', 'failure_reason' => 'SMS outbox is awaiting migration.']);
        }
        return DB::transaction(function () use ($shipment, $event, $actor) {
            $shipment = Shipment::query()->with(['sellerOrder.order.buyer', 'rider.user'])->lockForUpdate()->findOrFail($shipment->id);
            $order = $shipment->sellerOrder?->order;
            $buyer = $order?->buyer;
            $rawPhone = $order?->recipient_phone ?: $buyer?->phone;
            $phone = PhilippineMobileNumber::normalise($rawPhone);
            $key = $phone ?: 'missing-or-invalid:'.($buyer?->id ?? $order?->id ?? 'unknown');
            $attributes = [
                'order_id' => $order?->id, 'recipient_user_id' => $buyer?->id,
                'logistics_provider_id' => $shipment->logistics_provider_id, 'created_by' => $actor?->id,
                'recipient_phone' => $phone, 'recipient_masked' => PhilippineMobileNumber::mask($phone),
                'body' => $this->message($shipment, $event), 'driver' => (string) config('services.transactional_sms.driver', 'log'),
            ];
            $notification = ShipmentSmsNotification::firstOrCreate(
                ['shipment_id' => $shipment->id, 'event' => $event, 'recipient_key' => $key],
                $attributes + ['status' => $phone ? 'PENDING' : 'CANCELLED', 'queued_at' => now(), 'cancelled_at' => $phone ? null : now(), 'failure_reason' => $phone ? null : 'Missing or invalid Philippine mobile number.']
            );
            if ($notification->wasRecentlyCreated && $phone) {
                DB::afterCommit(function () use ($notification): void {
                    try { SendShipmentSms::dispatch($notification->id); } catch (\Throwable $error) { report($error); }
                });
            }
            return $notification;
        });
    }

    public function retry(ShipmentSmsNotification $notification, User $actor): ShipmentSmsNotification
    {
        return DB::transaction(function () use ($notification, $actor) {
            $locked = ShipmentSmsNotification::lockForUpdate()->findOrFail($notification->id);
            if ($locked->status !== 'FAILED') return $locked;
            $locked->update(['status' => 'PENDING', 'failure_reason' => null, 'failed_at' => null, 'retry_requested_by' => $actor->id, 'retried_at' => now(), 'queued_at' => now()]);
            DB::afterCommit(function () use ($locked): void {
                try { SendShipmentSms::dispatch($locked->id); } catch (\Throwable $error) { report($error); }
            });
            return $locked->fresh();
        });
    }

    private function message(Shipment $shipment, string $event): string
    {
        $reference = $shipment->tracking_code ?: ('Shipment #'.$shipment->id);
        $rider = trim((string) ($shipment->rider?->user?->full_name ?? ''));
        $cod = $shipment->cod_amount_minor > 0 ? ' COD due: ₱'.number_format($shipment->cod_amount_minor / 100, 2).'.' : '';
        $text = match ($event) {
            self::DELIVERY_ACCEPTED => "SUKI SHOP: Your delivery for {$reference} was accepted".($rider ? " by {$rider}" : '').'.',
            self::OUT_FOR_DELIVERY => "SUKI SHOP: {$reference} is out for delivery".($rider ? " with {$rider}" : '.').$cod,
            self::DELIVERY_ATTEMPT_FAILED => "SUKI SHOP: Delivery for {$reference} was unsuccessful. Check your order tracking for the next step.",
            self::DELIVERED => "SUKI SHOP: {$reference} has been delivered. Please review your order details.",
            default => throw new \InvalidArgumentException('Unsupported transactional SMS event.'),
        };
        return $text.' SUKI will never ask for your OTP or password.';
    }
}
