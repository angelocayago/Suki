<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentSmsNotification extends Model
{
    protected $fillable = [
        'shipment_id', 'order_id', 'recipient_user_id', 'logistics_provider_id', 'created_by', 'retry_requested_by',
        'event', 'recipient_key', 'recipient_phone', 'recipient_masked', 'body', 'driver',
        'status', 'attempts', 'provider_message_id', 'failure_reason', 'queued_at', 'sent_at',
        'failed_at', 'cancelled_at', 'retried_at',
    ];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'cancelled_at' => 'datetime', 'retried_at' => 'datetime'];
    }

    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_user_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id'); }
}
